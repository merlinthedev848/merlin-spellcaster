<?php
declare(strict_types=1);

/**
 * Workflow Automation Engine for Merlin V2.
 * Triggers automations on actions (like 'subscribe' or 'tag_added:x') and schedules step-by-step queues.
 */
class Automation {
    /**
     * Trigger all automations associated with a specific event
     */
    public static function trigger(string $event, int $subscriberId): void {
        $db = Database::getConnection();

        try {
            // Find active automations
            $st = $db->prepare("SELECT id, trigger_event, exclude_tag_id FROM automations WHERE status = 'active'");
            $st->execute();
            $automations = $st->fetchAll();

            foreach ($automations as $auto) {
                $autoId = (int)$auto['id'];
                $triggerEvent = $auto['trigger_event'];
                $excludeTagId = $auto['exclude_tag_id'] !== null ? (int)$auto['exclude_tag_id'] : null;

                $matched = false;
                if ($triggerEvent === $event) {
                    $matched = true;
                } elseif (str_starts_with($event, 'tag_added:') && str_starts_with($triggerEvent, 'tag_added:')) {
                    $tagId = substr($event, 10);
                    $allowedTagIds = explode(',', substr($triggerEvent, 10));
                    if (in_array($tagId, $allowedTagIds, true)) {
                        $matched = true;
                    }
                }

                if (!$matched) {
                    continue;
                }

                if ($excludeTagId !== null) {
                    $stCheckTag = $db->prepare("SELECT COUNT(*) FROM subscriber_tags WHERE subscriber_id = ? AND tag_id = ?");
                    $stCheckTag->execute([$subscriberId, $excludeTagId]);
                    if (((int)$stCheckTag->fetchColumn()) > 0) {
                        // Contact has the exclusion tag, skip triggering this automation
                        continue;
                    }
                }
                
                // Fetch the first step (lowest order_num)
                $stStep = $db->prepare("SELECT * FROM automation_steps WHERE automation_id = ? ORDER BY order_num ASC LIMIT 1");
                $stStep->execute([$autoId]);
                $firstStep = $stStep->fetch();

                if ($firstStep) {
                    self::scheduleStep($subscriberId, $autoId, $firstStep, new DateTime());
                }
            }
        } catch (Throwable $e) {
            error_log("Automation trigger error: " . $e->getMessage());
        }
    }

    /**
     * Process currently due automation queue items
     */
    public static function process(): int {
        $db = Database::getConnection();
        $processedCount = 0;

        try {
            // Fetch pending queue items that are due using FOR UPDATE SKIP LOCKED
            $st = $db->prepare("
                SELECT aq.*, ast.step_type, ast.step_value, ast.order_num, a.exclude_tag_id
                FROM automation_queue aq
                JOIN automation_steps ast ON ast.id = aq.step_id
                JOIN automations a ON a.id = aq.automation_id
                WHERE aq.status = 'pending' AND aq.execute_at <= NOW()
                LIMIT 50
                FOR UPDATE SKIP LOCKED
            ");
            $st->execute();
            $dueItems = $st->fetchAll();
        } catch (Throwable $e) {
            error_log("Automation fetch due items error: " . $e->getMessage());
            return 0;
        }

        foreach ($dueItems as $item) {
            try {
                $queueId = (int)$item['id'];
                $autoId = (int)$item['automation_id'];
                $subId = (int)$item['subscriber_id'];
                $stepId = (int)$item['step_id'];
                $stepType = $item['step_type'];
                $stepValue = $item['step_value'];
                $orderNum = (int)$item['order_num'];
                $excludeTagId = $item['exclude_tag_id'] !== null ? (int)$item['exclude_tag_id'] : null;

                if ($excludeTagId !== null) {
                    $stCheckTag = $db->prepare("SELECT COUNT(*) FROM subscriber_tags WHERE subscriber_id = ? AND tag_id = ?");
                    $stCheckTag->execute([$subId, $excludeTagId]);
                    if (((int)$stCheckTag->fetchColumn()) > 0) {
                        // Contact has the exclusion tag, delete their workflow queue and skip
                        $db->prepare("DELETE FROM automation_queue WHERE subscriber_id = ? AND automation_id = ?")->execute([$subId, $autoId]);
                        continue;
                    }
                }

                $db->beginTransaction();

                // 1. Mark current step as completed
                $db->prepare("UPDATE automation_queue SET status = 'completed' WHERE id = ?")->execute([$queueId]);

                // 2. Execute step action based on type
                if ($stepType === 'send_email') {
                    $campaignId = (int)$stepValue;
                    $db->prepare("
                        INSERT IGNORE INTO email_queue (campaign_id, subscriber_id, status, send_at) 
                        VALUES (?, ?, 'pending', NOW())
                    ")->execute([$campaignId, $subId]);
                    logActivity($subId, 'email_sent', "Email Campaign #{$campaignId} queued by automation");
                } 
                elseif ($stepType === 'send_sms') {
                    $message = $stepValue;
                    try {
                        $stPhone = $db->prepare("SELECT phone FROM subscribers WHERE id = ?");
                        $stPhone->execute([$subId]);
                        $phone = $stPhone->fetchColumn();
                        if (!empty($phone)) {
                            $stInsert = $db->prepare("INSERT INTO mod_sms_logs (subscriber_id, message, status, created_at) VALUES (?, ?, 'pending', NOW())");
                            $stInsert->execute([$subId, $message]);
                            logActivity($subId, 'sms_sent', "SMS queued by automation: " . substr($message, 0, 30) . "...");
                        }
                    } catch (Throwable $e) {
                        error_log("SMS step trigger failed: " . $e->getMessage());
                    }
                }
                elseif ($stepType === 'add_tag') {
                    $tagId = (int)$stepValue;
                    $db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)")
                       ->execute([$subId, $tagId]);
                    
                    $stName = $db->prepare("SELECT name FROM tags WHERE id = ?");
                    $stName->execute([$tagId]);
                    $tagName = $stName->fetchColumn() ?: "Tag #{$tagId}";
                    logActivity($subId, 'tag_added', "Tag assigned by automation: {$tagName}");
                } 
                elseif ($stepType === 'remove_tag') {
                    $tagId = (int)$stepValue;
                    
                    $stName = $db->prepare("SELECT name FROM tags WHERE id = ?");
                    $stName->execute([$tagId]);
                    $tagName = $stName->fetchColumn() ?: "Tag #{$tagId}";
                    
                    $db->prepare("DELETE FROM subscriber_tags WHERE subscriber_id = ? AND tag_id = ?")
                       ->execute([$subId, $tagId]);
                    
                    logActivity($subId, 'tag_removed', "Tag removed by automation: {$tagName}");
                } 
                elseif ($stepType === 'add_to_list') {
                    $listId = (int)$stepValue;
                    $db->prepare("INSERT IGNORE INTO subscriber_lists (subscriber_id, list_id, status) VALUES (?, ?, 'confirmed')")
                       ->execute([$subId, $listId]);
                    
                    $stName = $db->prepare("SELECT name FROM lists WHERE id = ?");
                    $stName->execute([$listId]);
                    $listName = $stName->fetchColumn() ?: "List #{$listId}";
                    logActivity($subId, 'list_added', "List assigned by automation: {$listName}");
                }
                elseif ($stepType === 'remove_from_list') {
                    $listId = (int)$stepValue;
                    $db->prepare("DELETE FROM subscriber_lists WHERE subscriber_id = ? AND list_id = ?")
                       ->execute([$subId, $listId]);
                    
                    $stName = $db->prepare("SELECT name FROM lists WHERE id = ?");
                    $stName->execute([$listId]);
                    $listName = $stName->fetchColumn() ?: "List #{$listId}";
                    logActivity($subId, 'list_removed', "List removed by automation: {$listName}");
                }
                elseif ($stepType === 'adjust_points') {
                    $points = (int)$stepValue;
                    $db->prepare("UPDATE subscribers SET lead_score = lead_score + ? WHERE id = ?")
                       ->execute([$points, $subId]);
                    logActivity($subId, 'score_adjusted', "Lead score adjusted by automation: " . ($points >= 0 ? "+{$points}" : $points) . " points");
                }
                elseif ($stepType === 'trigger_webhook') {
                    $webhookUrl = $stepValue;
                    try {
                        $stSub = $db->prepare("SELECT email, first_name, last_name, status, lead_score FROM subscribers WHERE id = ?");
                        $stSub->execute([$subId]);
                        $sub = $stSub->fetch(PDO::FETCH_ASSOC);
                        if ($sub) {
                            $payload = json_encode([
                                'event' => 'automation_triggered',
                                'timestamp' => date('Y-m-d H:i:s'),
                                'subscriber' => $sub
                             ]);
                            $ch = curl_init($webhookUrl);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                            curl_exec($ch);
                            curl_close($ch);
                        }
                    } catch (Throwable $e) {
                         error_log("Webhook step trigger failed: " . $e->getMessage());
                    }
                }
                elseif ($stepType === 'send_if_opened') {
                    $parts = explode(':', $stepValue);
                    $campaignId = (int)($parts[0] ?? 0);
                    $prevCampaignId = (int)($parts[1] ?? 0);

                    $stCheck = $db->prepare("SELECT COUNT(*) FROM campaign_opens WHERE campaign_id = ? AND subscriber_id = ?");
                    $stCheck->execute([$prevCampaignId, $subId]);
                    $opened = ((int)$stCheck->fetchColumn()) > 0;

                    if ($opened && $campaignId > 0) {
                        $db->prepare("
                            INSERT IGNORE INTO email_queue (campaign_id, subscriber_id, status, send_at) 
                            VALUES (?, ?, 'pending', NOW())
                        ")->execute([$campaignId, $subId]);
                    }
                } 
                elseif ($stepType === 'send_if_not_opened') {
                    $parts = explode(':', $stepValue);
                    $campaignId = (int)($parts[0] ?? 0);
                    $prevCampaignId = (int)($parts[1] ?? 0);

                    $stCheck = $db->prepare("SELECT COUNT(*) FROM campaign_opens WHERE campaign_id = ? AND subscriber_id = ?");
                    $stCheck->execute([$prevCampaignId, $subId]);
                    $opened = ((int)$stCheck->fetchColumn()) > 0;

                    if (!$opened && $campaignId > 0) {
                        $db->prepare("
                            INSERT IGNORE INTO email_queue (campaign_id, subscriber_id, status, send_at) 
                            VALUES (?, ?, 'pending', NOW())
                        ")->execute([$campaignId, $subId]);
                    }
                } 
                elseif ($stepType === 'tag_if_not_opened') {
                    $parts = explode(':', $stepValue);
                    $tagId = (int)($parts[0] ?? 0);
                    $prevCampaignId = (int)($parts[1] ?? 0);

                    $stCheck = $db->prepare("SELECT COUNT(*) FROM campaign_opens WHERE campaign_id = ? AND subscriber_id = ?");
                    $stCheck->execute([$prevCampaignId, $subId]);
                    $opened = ((int)$stCheck->fetchColumn()) > 0;

                    if (!$opened && $tagId > 0) {
                        $db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)")
                           ->execute([$subId, $tagId]);
                        
                        $stName = $db->prepare("SELECT name FROM tags WHERE id = ?");
                        $stName->execute([$tagId]);
                        $tagName = $stName->fetchColumn() ?: "Tag #{$tagId}";
                        logActivity($subId, 'tag_added', "Tag assigned (No response to Campaign #{$prevCampaignId}): {$tagName}");
                    }
                }
                elseif ($stepType === 'send_if_clicked') {
                    $parts = explode(':', $stepValue);
                    $campaignId = (int)($parts[0] ?? 0);
                    $prevCampaignId = (int)($parts[1] ?? 0);

                    $stCheck = $db->prepare("SELECT COUNT(*) FROM campaign_clicks WHERE campaign_id = ? AND subscriber_id = ?");
                    $stCheck->execute([$prevCampaignId, $subId]);
                    $clicked = ((int)$stCheck->fetchColumn()) > 0;

                    if ($clicked && $campaignId > 0) {
                        $db->prepare("
                            INSERT IGNORE INTO email_queue (campaign_id, subscriber_id, status, send_at) 
                            VALUES (?, ?, 'pending', NOW())
                        ")->execute([$campaignId, $subId]);
                    }
                }
                elseif ($stepType === 'tag_if_clicked') {
                    $parts = explode(':', $stepValue);
                    $tagId = (int)($parts[0] ?? 0);
                    $prevCampaignId = (int)($parts[1] ?? 0);

                    $stCheck = $db->prepare("SELECT COUNT(*) FROM campaign_clicks WHERE campaign_id = ? AND subscriber_id = ?");
                    $stCheck->execute([$prevCampaignId, $subId]);
                    $clicked = ((int)$stCheck->fetchColumn()) > 0;

                    if ($clicked && $tagId > 0) {
                        $db->prepare("INSERT IGNORE INTO subscriber_tags (subscriber_id, tag_id) VALUES (?, ?)")
                           ->execute([$subId, $tagId]);
                        
                        $stName = $db->prepare("SELECT name FROM tags WHERE id = ?");
                        $stName->execute([$tagId]);
                        $tagName = $stName->fetchColumn() ?: "Tag #{$tagId}";
                        logActivity($subId, 'tag_added', "Tag assigned (Clicked link in Campaign #{$prevCampaignId}): {$tagName}");
                    }
                }
                elseif ($stepType === 'send_if_has_tag') {
                    $parts = explode(':', $stepValue);
                    $campaignId = (int)($parts[0] ?? 0);
                    $tagId = (int)($parts[1] ?? 0);

                    $stCheck = $db->prepare("SELECT COUNT(*) FROM subscriber_tags WHERE tag_id = ? AND subscriber_id = ?");
                    $stCheck->execute([$tagId, $subId]);
                    $hasTag = ((int)$stCheck->fetchColumn()) > 0;

                    if ($hasTag && $campaignId > 0) {
                        $db->prepare("
                            INSERT IGNORE INTO email_queue (campaign_id, subscriber_id, status, send_at) 
                            VALUES (?, ?, 'pending', NOW())
                        ")->execute([$campaignId, $subId]);
                    }
                }
                elseif ($stepType === 'send_if_has_no_tag') {
                    $parts = explode(':', $stepValue);
                    $campaignId = (int)($parts[0] ?? 0);
                    $tagId = (int)($parts[1] ?? 0);

                    $stCheck = $db->prepare("SELECT COUNT(*) FROM subscriber_tags WHERE tag_id = ? AND subscriber_id = ?");
                    $stCheck->execute([$tagId, $subId]);
                    $hasTag = ((int)$stCheck->fetchColumn()) > 0;

                    if (!$hasTag && $campaignId > 0) {
                        $db->prepare("
                            INSERT IGNORE INTO email_queue (campaign_id, subscriber_id, status, send_at) 
                            VALUES (?, ?, 'pending', NOW())
                        ")->execute([$campaignId, $subId]);
                    }
                }

                // 3. Find the NEXT step
                $stNext = $db->prepare("
                    SELECT * 
                    FROM automation_steps 
                    WHERE automation_id = ? AND order_num > ? 
                    ORDER BY order_num ASC 
                    LIMIT 1
                ");
                $stNext->execute([$autoId, $orderNum]);
                $nextStep = $stNext->fetch();

                if ($nextStep) {
                    // Schedule next step relative to current time
                    self::scheduleStep($subId, $autoId, $nextStep, new DateTime());
                }

                $db->commit();
                $processedCount++;
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Automation item process error (Item ID: " . ($item['id'] ?? 'unknown') . "): " . $e->getMessage());
            }
        }

        return $processedCount;
    }

    /**
     * Schedule a step in the automation queue
     */
    private static function scheduleStep(int $subscriberId, int $automationId, array $step, DateTime $baseTime): void {
        $db = Database::getConnection();
        $stepId = (int)$step['id'];
        $stepType = $step['step_type'];
        $stepValue = $step['step_value'];

        $executeAt = clone $baseTime;

        if ($stepType === 'wait') {
            $interval = DateInterval::createFromDateString($stepValue);
            if ($interval) {
                $executeAt->add($interval);
            }
        }

        $stInsert = $db->prepare("
            INSERT INTO automation_queue (automation_id, subscriber_id, step_id, status, execute_at)
            VALUES (?, ?, ?, 'pending', ?)
        ");
        $stInsert->execute([$automationId, $subscriberId, $stepId, $executeAt->format('Y-m-d H:i:s')]);
    }
}
