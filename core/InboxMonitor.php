<?php
declare(strict_types=1);

/**
 * Core Inbox Monitoring Service.
 * Connects to IMAP sending/reply mailboxes to detect and classify:
 * 1. Delivery Bounces: Marks status 'bounced', strips other tags, sets 'bounced' tag.
 * 2. Human Replies: Assigns 'replied' tag to subscriber and logs interaction on CRM timeline.
 */
class InboxMonitor {
    public static function process(): array {
        $db = Database::getConnection();

        $smtpHost = getSetting('smtp_host', '');
        $smtpUser = getSetting('smtp_user', '');
        $smtpPass = getSetting('smtp_pass', '');
        $smtpEnc  = strtolower(getSetting('smtp_encryption', 'tls'));

        if (empty($smtpHost) || empty($smtpUser) || empty($smtpPass)) {
            return ['success' => false, 'message' => 'SMTP mailer settings not configured. Please setup SMTP in Settings first.'];
        }

        // Determine if custom IMAP settings should be used
        $inherit = getSetting('bounce_imap_inherit', '1') === '1';
        
        $host = '';
        $portVal = '';
        $user = '';
        $pass = '';
        $enc = '';

        if (!$inherit) {
            $host = getSetting('bounce_imap_host', '');
            $portVal = getSetting('bounce_imap_port', '');
            $user = getSetting('bounce_imap_user', '');
            $pass = getSetting('bounce_imap_pass', '');
            $enc = strtolower(getSetting('bounce_imap_encryption', 'ssl'));
        }

        // Fallback to derived SMTP settings if values are empty
        if (empty($host)) {
            if (stripos($smtpHost, 'smtp.') === 0) {
                $host = preg_replace('/^smtp\./i', 'imap.', $smtpHost);
            } else {
                $host = $smtpHost;
            }
        }
        if (empty($user)) {
            $user = $smtpUser;
        }
        if (empty($pass)) {
            $pass = $smtpPass;
        }
        if (empty($enc)) {
            $enc = $smtpEnc;
        }

        $port = 143;
        if (!empty($portVal)) {
            $port = (int)$portVal;
        } else {
            $port = ($enc === 'ssl') ? 993 : 143;
        }

        $sslStr = '/novalidate-cert';
        if ($enc === 'ssl') {
            $sslStr = '/ssl/novalidate-cert';
        } elseif ($enc === 'tls') {
            $sslStr = '/tls/novalidate-cert';
        }

        if (!function_exists('imap_open')) {
            return ['success' => false, 'message' => 'PHP IMAP extension is not loaded on this system.'];
        }

        $bouncesFolder = getSetting('bounce_imap_folder_bounces', 'INBOX');
        $repliesFolder = getSetting('bounce_imap_folder_replies', 'INBOX');
        $action = getSetting('bounce_imap_action', 'mark_read');
        $archiveBounces = getSetting('bounce_imap_folder_archive_bounces', 'INBOX.ProcessedBounces');
        $archiveReplies = getSetting('bounce_imap_folder_archive_replies', 'INBOX.ProcessedReplies');

        $folders = array_unique([$bouncesFolder, $repliesFolder]);

        $processedBounces = 0;
        $processedReplies = 0;
        $processedUnsubscribes = 0;
        $bouncedList = [];
        $repliedList = [];
        $unsubscribedList = [];

        foreach ($folders as $folder) {
            if (empty(trim($folder))) continue;

            $mailbox = "{" . $host . ":" . $port . "/imap" . $sslStr . "}" . $folder;

            // Open IMAP Connection
            $inbox = @imap_open($mailbox, $user, $pass);
            if (!$inbox) {
                // Flush IMAP error stack to prevent shutdown SSL notices
                @imap_errors();
                @imap_alerts();
                continue;
            }

            // Fetch all unseen messages
            $emails = imap_search($inbox, 'UNSEEN');
            
            if ($emails) {
                foreach ($emails as $emailNumber) {
                    $header = imap_headerinfo($inbox, $emailNumber);
                    $body = imap_body($inbox, $emailNumber, FT_PEEK); // Use FT_PEEK to avoid marking as Seen automatically
                    
                    $subject = strtolower($header->subject ?? '');
                    
                    // Determine if it is a bounce
                    $bounceEmail = self::parseBounceEmail($header, $body);
                    $isBounce = false;
                    $isReply = false;
                    $isUnsub = false;

                    if ($bounceEmail) {
                        // It is a bounce!
                        $st = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
                        $st->execute([$bounceEmail]);
                        $subId = (int)$st->fetchColumn();

                        if ($subId > 0) {
                            self::markAsBounced($db, $subId, $subject);
                            $bouncedList[] = $bounceEmail;
                            $processedBounces++;
                            $isBounce = true;
                        }
                    } else {
                        // Check if it is a reply or unsubscribe from an existing contact
                        $fromAddress = '';
                        if (!empty($header->from)) {
                            $fromObj = $header->from[0];
                            $fromAddress = strtolower($fromObj->mailbox . '@' . $fromObj->host);
                        }

                        if ($fromAddress && filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
                            // Skip system addresses or mailer daemons
                            if (!preg_match('/postmaster|mailer-daemon|noreply|no-reply/i', $fromAddress)) {
                                $st = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
                                $st->execute([$fromAddress]);
                                $subId = (int)$st->fetchColumn();

                                if ($subId > 0) {
                                    if (self::parseUnsubscribeEmail($header, $body)) {
                                        self::markAsUnsubscribed($db, $subId, $header->subject ?? 'Unsubscribe request');
                                        $unsubscribedList[] = $fromAddress;
                                        $processedUnsubscribes++;
                                        $isUnsub = true;
                                    } else {
                                        self::markAsReplied($db, $subId, $header->subject ?? 'Re: Campaign');
                                        $repliedList[] = $fromAddress;
                                        $processedReplies++;
                                        $isReply = true;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Post-processing action for bounces, replies, and unsubscribes
                    if ($isBounce || $isReply || $isUnsub) {
                        if ($action === 'delete') {
                            imap_delete($inbox, (string)$emailNumber);
                        } elseif ($action === 'move') {
                            if ($isBounce && !empty(trim($archiveBounces))) {
                                imap_mail_move($inbox, (string)$emailNumber, trim($archiveBounces));
                            } elseif (($isReply || $isUnsub) && !empty(trim($archiveReplies))) {
                                imap_mail_move($inbox, (string)$emailNumber, trim($archiveReplies));
                            } else {
                                imap_setflag_full($inbox, (string)$emailNumber, '\\Seen');
                            }
                        } else {
                            // Default: just mark as read
                            imap_setflag_full($inbox, (string)$emailNumber, '\\Seen');
                        }
                    }
                }
            }
            
            // Execute deletions/moves
            imap_expunge($inbox);
            imap_close($inbox);
            @imap_errors();
            @imap_alerts();
        }

        @imap_errors();
        @imap_alerts();

        return [
            'success' => true,
            'bounces_count' => $processedBounces,
            'replies_count' => $processedReplies,
            'unsubscribes_count' => $processedUnsubscribes,
            'bounces' => $bouncedList,
            'replies' => $repliedList,
            'unsubscribes' => $unsubscribedList
        ];
    }

    /**
     * Check if email is bounce and return the affected recipient email address
     */
    private static function parseBounceEmail($header, string $body): ?string {
        $subject = strtolower($header->subject ?? '');
        $from = '';
        if (!empty($header->from)) {
            $fromObj = $header->from[0];
            $from = strtolower($fromObj->mailbox . '@' . $fromObj->host);
        }

        $indicators = [
            'delivery status notification', 
            'undeliverable', 
            'returned mail', 
            'mail delivery failed', 
            'failure notice',
            'returning message to sender'
        ];

        $isBounce = false;
        if (preg_match('/postmaster|mailer-daemon|mailer_daemon/i', $from)) {
            $isBounce = true;
        } else {
            foreach ($indicators as $ind) {
                if (str_contains($subject, $ind)) {
                    $isBounce = true;
                    break;
                }
            }
        }

        if ($isBounce) {
            $patterns = [
                '/Final-Recipient:\s*rfc822;\s*([a-z0-9_\-\+\.]+@[a-z0-9\-]+\.[a-z0-9\-\.]+)/i',
                '/Failed recipient:\s*([a-z0-9_\-\+\.]+@[a-z0-9\-]+\.[a-z0-9\-\.]+)/i',
                '/Original-Recipient:\s*rfc822;\s*([a-z0-9_\-\+\.]+@[a-z0-9\-]+\.[a-z0-9\-\.]+)/i',
                '/To:\s*<([a-z0-9_\-\+\.]+@[a-z0-9\-]+\.[a-z0-9\-\.]+)>+/i',
                '/([a-z0-9_\-\+\.]+@[a-z0-9\-]+\.[a-z0-9\-\.]+)\s+destination\s+address\s+rejected/i'
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $body, $matches)) {
                    return strtolower(trim($matches[1]));
                }
            }
        }
        return null;
    }

    /**
     * Set subscriber status 'bounced', strip other tags, assign 'bounced' tag, log bounce activity
     */
    private static function markAsBounced(PDO $db, int $subId, string $subject): void {
        try {
            $db->beginTransaction();

            $db->prepare("UPDATE subscribers SET status = 'bounced', updated_at = NOW() WHERE id = ?")->execute([$subId]);

            // Resolve 'bounced' tag
            $stTag = $db->prepare("SELECT id FROM tags WHERE name = ?");
            $stTag->execute(['bounced']);
            $tagId = $stTag->fetchColumn();
            if (!$tagId) {
                $stIns = $db->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
                $stIns->execute(['bounced', '#e53e3e']);
                $tagId = (int)$db->lastInsertId();
            } else {
                $tagId = (int)$tagId;
            }

            // Strip existing tags
            $db->prepare("DELETE FROM subscriber_tags WHERE subscriber_id = ?")->execute([$subId]);

            // Assign bounced tag
            $db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)")->execute([$subId, $tagId]);

            // Clean pending queue
            $db->prepare("DELETE FROM email_queue WHERE subscriber_id = ? AND status = 'pending'")->execute([$subId]);

            logActivity($subId, 'bounce', "Delivery bounce detected: " . $subject);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('InboxMonitor markAsBounced error: ' . $e->getMessage());
        }
    }

    /**
     * Assign 'replied' tag to subscriber and log reply interaction
     */
    private static function markAsReplied(PDO $db, int $subId, string $subject): void {
        try {
            $db->beginTransaction();

            // Resolve 'replied' tag
            $stTag = $db->prepare("SELECT id FROM tags WHERE name = ?");
            $stTag->execute(['replied']);
            $tagId = $stTag->fetchColumn();
            if (!$tagId) {
                $stIns = $db->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
                $stIns->execute(['replied', '#3182ce']); // blue color
                $tagId = (int)$db->lastInsertId();
            } else {
                $tagId = (int)$tagId;
            }

            // Assign replied tag (keep other tags as replying is a positive interaction)
            $db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)")->execute([$subId, $tagId]);

            logActivity($subId, 'reply', "Subscriber Replied: " . $subject);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('InboxMonitor markAsReplied error: ' . $e->getMessage());
        }
    }

    /**
     * Check if email subject or body contains explicit unsubscribe requests
     */
    private static function parseUnsubscribeEmail($header, string $body): bool {
        $subject = strtolower($header->subject ?? '');
        $snippet = strtolower(substr(strip_tags($body), 0, 1500));

        // Direct subject match
        if (preg_match('/\b(unsubscribe|opt-out|optout|remove me|stop|cancel subscription)\b/i', $subject)) {
            return true;
        }

        // Snippet/Body phrase match
        $bodyKeywords = [
            'please unsubscribe me',
            'unsubscribe me',
            'remove me from your list',
            'remove me from this list',
            'take me off your list',
            'take me off this list',
            'stop sending me emails',
            'stop sending email',
            'please opt me out',
            'opt me out',
            'do not contact me',
            'don\'t contact me',
            'please remove my email',
            'cancel my subscription'
        ];

        foreach ($bodyKeywords as $kw) {
            if (str_contains($snippet, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set subscriber status 'unsubscribed', strip other tags, assign 'DO NOT CONTACT' tag, clean pending queues, log activity
     */
    private static function markAsUnsubscribed(PDO $db, int $subId, string $subject): void {
        try {
            $db->beginTransaction();

            $db->prepare("UPDATE subscribers SET status = 'unsubscribed', updated_at = NOW() WHERE id = ?")->execute([$subId]);

            // Resolve 'DO NOT CONTACT' tag
            $stTag = $db->prepare("SELECT id FROM tags WHERE name = ?");
            $stTag->execute(['DO NOT CONTACT']);
            $tagId = $stTag->fetchColumn();
            if (!$tagId) {
                $stIns = $db->prepare("INSERT INTO tags (name, color) VALUES (?, ?)");
                $stIns->execute(['DO NOT CONTACT', '#d69e2e']);
                $tagId = (int)$db->lastInsertId();
            } else {
                $tagId = (int)$tagId;
            }

            // Strip existing tags & assign DNC tag
            $db->prepare("DELETE FROM subscriber_tags WHERE subscriber_id = ?")->execute([$subId]);
            $db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)")->execute([$subId, $tagId]);

            // Clean pending queues
            $db->prepare("DELETE FROM email_queue WHERE subscriber_id = ? AND status = 'pending'")->execute([$subId]);
            $db->prepare("DELETE FROM automation_queue WHERE subscriber_id = ? AND status = 'pending'")->execute([$subId]);

            logActivity($subId, 'unsubscribed', "Unsubscribed via Inbox Monitor: " . $subject);
            Automation::trigger('unsubscribed', $subId);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('InboxMonitor markAsUnsubscribed error: ' . $e->getMessage());
        }
    }
}
