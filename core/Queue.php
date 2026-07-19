<?php
declare(strict_types=1);

/**
 * Robust Cron-driven Email Queue Processor for Merlin V2.
 * Processes pending emails in batches and handles templating, open tracking, and click tracking.
 */
class Queue {
    public static function process(int $limit = 50): array {
        $db = Database::getConnection();
        $appUrl = rtrim(getSetting('app_url', 'http://localhost/merlin-spellcaster'), '/');
        
        $jobs = [];
        
        // Grab and lock pending email queue items using FOR UPDATE SKIP LOCKED
        try {
            $db->beginTransaction();
            $st = $db->prepare("
                SELECT eq.id as queue_id, eq.campaign_id, eq.subscriber_id, eq.ab_subject,
                       c.subject, c.body_html, c.body_text, c.include_unsubscribe, c.max_per_hour,
                       s.email, s.first_name, s.last_name
                FROM email_queue eq
                JOIN campaigns c ON c.id = eq.campaign_id
                JOIN subscribers s ON s.id = eq.subscriber_id
                WHERE eq.status = 'pending' AND eq.send_at <= NOW() AND c.status IN ('sending', 'queued') AND s.status = 'active'
                ORDER BY eq.send_at ASC
                LIMIT :limit
                FOR UPDATE SKIP LOCKED
            ");
            $st->bindValue(':limit', $limit, PDO::PARAM_INT);
            $st->execute();
            $jobs = $st->fetchAll();

            if (!empty($jobs)) {
                $ids = array_column($jobs, 'queue_id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $db->prepare("UPDATE email_queue SET status='sending', attempts = attempts + 1 WHERE id IN ($placeholders)")
                   ->execute($ids);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Failed to lock email queue: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }

        if (empty($jobs)) {
            return ['success' => true, 'sent' => 0, 'failed' => 0, 'message' => 'Queue is empty'];
        }

        $mailer = new Mailer();
        $sentCount = 0;
        $failedCount = 0;

        $campaignSentCounts = [];

        foreach ($jobs as $job) {
            $subId = (int)$job['subscriber_id'];
            $camId = (int)$job['campaign_id'];
            $queueId = (int)$job['queue_id'];
            $maxPerHour = (int)($job['max_per_hour'] ?? 0);

            // Throttle limit check
            if ($maxPerHour > 0) {
                if (!isset($campaignSentCounts[$camId])) {
                    $stCount = $db->prepare("
                        SELECT COUNT(*) FROM email_queue 
                        WHERE campaign_id = ? AND status = 'sent' AND sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    ");
                    $stCount->execute([$camId]);
                    $campaignSentCounts[$camId] = (int)$stCount->fetchColumn();
                }

                if ($campaignSentCounts[$camId] >= $maxPerHour) {
                    // Exceeded hourly limit, push send_at forward 15 mins and revert to pending
                    $db->prepare("UPDATE email_queue SET status = 'pending', send_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?")
                       ->execute([$queueId]);
                    continue;
                }
                $campaignSentCounts[$camId]++;
            }
            
            $token = generateToken($job['email'], $camId, $subId);
            
            // Generate tracking URLs
            $unsubUrl = "{$appUrl}/unsubscribe?c={$camId}&s={$subId}&t={$token}";
            $pixelUrl = "{$appUrl}/o?c={$camId}&s={$subId}&t={$token}";

            // Template variables mapping
            $vars = [
                'first_name' => $job['first_name'] ?: 'Friend',
                'last_name'  => $job['last_name'] ?: '',
                'email'      => $job['email'],
                'unsubscribe_url' => $unsubUrl,
                'app_name'   => getSetting('app_name', 'Merlin Spellcaster'),
                'app_url'    => $appUrl,
            ];

            // Allow modules to alter or append custom macros (e.g. referral link)
            $hookVars = ['vars' => $vars, 'subscriber_id' => $subId, 'campaign_id' => $camId];
            Hook::fire('before_render_email', $hookVars);
            $vars = $hookVars['vars'];

            // Render subject, HTML and text bodies
            $rawSubject = $job['ab_subject'] !== null ? $job['ab_subject'] : $job['subject'];
            $subject = self::renderTemplate($rawSubject, $vars);
            $bodyHtml = self::renderTemplate($job['body_html'], $vars);
            
            // Auto-append footer details to comply with CAN-SPAM Act and GDPR
            $companyAddress = getSetting('company_address', '');
            $footerHtml = '';
            $hasUnsubUrlToken = str_contains($bodyHtml, 'unsubscribe_url');

            if (!$hasUnsubUrlToken && (int)$job['include_unsubscribe'] === 1) {
                $footerHtml .= '<br><br><hr><p style="font-size:11px;color:#a0aec0;font-family:sans-serif;line-height:1.5;">';
                if (!empty($companyAddress)) {
                    $footerHtml .= nl2br(e($companyAddress)) . '<br>';
                }
                $footerHtml .= 'To stop receiving these emails, you can <a href="' . $unsubUrl . '" style="color:#718096;text-decoration:underline;">unsubscribe here</a>.</p>';
            } elseif (!empty($companyAddress)) {
                // If unsub link is manually handled, still append physical address to comply with CAN-SPAM
                $footerHtml .= '<br><br><hr><p style="font-size:11px;color:#a0aec0;font-family:sans-serif;line-height:1.5;">' . nl2br(e($companyAddress)) . '</p>';
            }

            if ($footerHtml !== '') {
                if (str_contains($bodyHtml, '</body>')) {
                    $bodyHtml = str_replace('</body>', $footerHtml . '</body>', $bodyHtml);
                } else {
                    $bodyHtml .= $footerHtml;
                }
            }

            $bodyText = $job['body_text'] ? self::renderTemplate($job['body_text'], $vars) : strip_tags($bodyHtml);

            // Wrap links for click tracking
            $bodyHtml = preg_replace_callback('/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i', function ($m) use ($appUrl, $camId, $subId, $token) {
                $origUrl = $m[1];
                // Do not track unsubscribe link or already wrapped link
                if (str_contains($origUrl, '/unsubscribe') || str_contains($origUrl, '/r?')) {
                    return $m[0];
                }
                $trackUrl = "{$appUrl}/r?c={$camId}&s={$subId}&t={$token}&url=" . urlencode($origUrl);
                return str_replace($origUrl, $trackUrl, $m[0]);
            }, $bodyHtml) ?? $bodyHtml;

            // Inject open tracking pixel before </body> or at the end
            $pixelImg = '<img src="' . $pixelUrl . '" width="1" height="1" border="0" style="display:none !important;" alt="">';
            if (str_contains($bodyHtml, '</body>')) {
                $bodyHtml = str_replace('</body>', $pixelImg . '</body>', $bodyHtml);
            } else {
                $bodyHtml .= $pixelImg;
            }

            // Send email with compliant unsubscribe headers
            $extraHeaders = [
                'List-Unsubscribe' => "<{$unsubUrl}>",
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'
            ];
            $ok = $mailer->send($job['email'], $subject, $bodyHtml, $bodyText, '', '', null, $extraHeaders);

            if ($ok) {
                $db->prepare("UPDATE email_queue SET status='sent', sent_at=NOW() WHERE id=?")->execute([$queueId]);
                logActivity($subId, 'sent', "Sent Campaign #{$camId}: {$subject}");
                $sentCount++;
            } else {
                $err = $mailer->getLastError();
                
                // If it is a rate limit, pause the batch, reset current/remaining to pending and exit
                if (stripos($err, 'ratelimit') !== false || 
                    stripos($err, 'rate limit') !== false || 
                    stripos($err, 'limit exceeded') !== false || 
                    stripos($err, 'too many requests') !== false || 
                    str_contains($err, '451') || 
                    str_contains($err, '421')
                ) {
                    // Revert current job back to pending and defer it by 30 minutes
                    $db->prepare("
                        UPDATE email_queue 
                        SET status = 'pending', send_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE), error_message = ? 
                        WHERE id = ?
                    ")->execute([$err, $queueId]);

                    // Revert all remaining jobs in the batch back to pending
                    $remainingJobs = array_slice($jobs, array_search($job, $jobs) + 1);
                    if (!empty($remainingJobs)) {
                        $remIds = array_column($remainingJobs, 'queue_id');
                        $remPlaceholders = implode(',', array_fill(0, count($remIds), '?'));
                        $db->prepare("
                            UPDATE email_queue 
                            SET status = 'pending' 
                            WHERE id IN ($remPlaceholders)
                        ")->execute($remIds);
                    }

                    logActivity($subId, 'bounce', "SMTP Rate Limit Block. Resuming from this address in 30 mins.");

                    self::updateCampaignStats($db, $jobs);

                    return [
                        'success' => false,
                        'sent' => $sentCount,
                        'failed' => $failedCount,
                        'message' => "Rate Limit Encountered at {$job['email']}. Paused queue sending batch: {$err}"
                    ];
                }

                $db->prepare("UPDATE email_queue SET status='failed', error_message=? WHERE id=?")->execute([$err, $queueId]);
                logActivity($subId, 'bounce', "Failed to send Campaign #{$camId}. Error: {$err}");
                $failedCount++;
            }
        }

        self::updateCampaignStats($db, $jobs);

        return [
            'success' => true,
            'sent' => $sentCount,
            'failed' => $failedCount,
            'message' => "Processed {$sentCount} sent, {$failedCount} failed."
        ];
    }

    /**
     * Update campaign sent/failed stats.
     */
    private static function updateCampaignStats($db, array $jobs): void {
        $campaignIds = array_unique(array_column($jobs, 'campaign_id'));
        foreach ($campaignIds as $cid) {
            $stPending = $db->prepare("SELECT COUNT(*) FROM email_queue WHERE campaign_id=? AND status='pending'");
            $stPending->execute([$cid]);
            $pendingCount = (int)$stPending->fetchColumn();

            $stStats = $db->prepare("
                SELECT 
                    COUNT(IF(status='sent', 1, NULL)) as sent,
                    COUNT(IF(status='failed', 1, NULL)) as failed
                FROM email_queue 
                WHERE campaign_id=?
            ");
            $stStats->execute([$cid]);
            $stats = $stStats->fetch();

            if ($pendingCount === 0) {
                $db->prepare("
                    UPDATE campaigns 
                    SET status='sent', send_count=?, bounce_count=?, sent_at=NOW() 
                    WHERE id=? AND status IN ('sending', 'queued')
                ")->execute([(int)$stats['sent'], (int)$stats['failed'], $cid]);
            } else {
                $db->prepare("
                    UPDATE campaigns 
                    SET status='sending', send_count=?, bounce_count=? 
                    WHERE id=? AND status IN ('sending', 'queued')
                ")->execute([(int)$stats['sent'], (int)$stats['failed'], $cid]);
            }
        }
    }

    /**
     * Simple tag replacement template engine.
     * Replaces tag styles like {{first_name}} or {first_name}
     */
    private static function renderTemplate(string $content, array $vars): string {
        foreach ($vars as $key => $value) {
            $content = str_replace(
                ["{{{$key}}}", "{{ {$key} }}", "{{$key}}", "{$key}"],
                (string)$value,
                $content
            );
        }
        return $content;
    }
}
