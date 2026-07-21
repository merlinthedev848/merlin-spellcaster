<?php
declare(strict_types=1);

/**
 * Controller for visual marketing automations and workflow pipelines
 */
class AutomationController {
    public function index(): void {
        $db = Database::getConnection();
        
        $action = $_GET['action'] ?? '';
        $id = (int)($_GET['id'] ?? 0);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($action === 'delete' && $id > 0) {
                $db->prepare("DELETE FROM automations WHERE id = ?")->execute([$id]);
                $_SESSION['flash_success'] = 'Automation deleted successfully.';
                header('Location: ' . getSetting('app_url') . '/automations');
                exit;
            }
            if ($action === 'duplicate' && $id > 0) {
                $stAuto = $db->prepare("SELECT * FROM automations WHERE id = ?");
                $stAuto->execute([$id]);
                $auto = $stAuto->fetch();

                if ($auto) {
                    $newName = ($auto['name'] ?? 'Automation') . " (Copy)";
                    $stIns = $db->prepare("INSERT INTO automations (name, trigger_event, status, created_at) VALUES (?, ?, 'inactive', NOW())");
                    $stIns->execute([$newName, $auto['trigger_event']]);
                    $newId = (int)$db->lastInsertId();

                    $stSteps = $db->prepare("SELECT * FROM automation_steps WHERE automation_id = ? ORDER BY order_num ASC");
                    $stSteps->execute([$id]);
                    $steps = $stSteps->fetchAll();

                    $stInsStep = $db->prepare("INSERT INTO automation_steps (automation_id, order_num, step_type, step_value) VALUES (?, ?, ?, ?)");
                    foreach ($steps as $s) {
                        $stInsStep->execute([$newId, $s['order_num'], $s['step_type'], $s['step_value']]);
                    }
                    $_SESSION['flash_success'] = "Automation workflow duplicated.";
                }
                header('Location: ' . getSetting('app_url') . '/automations');
                exit;
            }

            if ($action === 'toggle' && $id > 0) {
                $stStatus = $db->prepare("SELECT status FROM automations WHERE id = ?");
                $stStatus->execute([$id]);
                $current = $stStatus->fetchColumn();
                $new = ($current === 'active') ? 'inactive' : 'active';
                
                $db->prepare("UPDATE automations SET status = ? WHERE id = ?")->execute([$new, $id]);
                $_SESSION['flash_success'] = 'Automation status updated.';
                header('Location: ' . getSetting('app_url') . '/automations');
                exit;
            }
        }
        
        // Fetch automations with count of associated steps
        $st = $db->query("
            SELECT a.*, COUNT(s.id) as step_count 
            FROM automations a
            LEFT JOIN automation_steps s ON s.automation_id = a.id
            GROUP BY a.id
            ORDER BY a.created_at DESC
        ");
        $automations = $st->fetchAll();

        // Mappings for human-readable triggers
        $tags = [];
        foreach ($db->query("SELECT id, name FROM tags")->fetchAll() as $row) {
            $tags[(int)$row['id']] = $row['name'];
        }

        $forms = [];
        foreach ($db->query("SELECT id, name FROM forms")->fetchAll() as $row) {
            $forms[(int)$row['id']] = $row['name'];
        }

        $campaigns = [];
        foreach ($db->query("SELECT id, name FROM campaigns")->fetchAll() as $row) {
            $campaigns[(int)$row['id']] = $row['name'];
        }
        
        $title = 'Automations';
        $viewPath = dirname(__DIR__) . '/views/automations.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    public function create(): void {
        $db = Database::getConnection();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $triggerType = trim($_POST['trigger_type'] ?? 'subscribe');
            $triggerTagId = (int)($_POST['trigger_tag_id'] ?? 0);
            $triggerTagIds = $_POST['trigger_tag_ids'] ?? [];
            $triggerFormId = (int)($_POST['trigger_form_id'] ?? 0);
            $triggerCampaignId = (int)($_POST['trigger_campaign_id'] ?? 0);
            $triggerPoints = (int)($_POST['trigger_points'] ?? 0);
            $steps = $_POST['steps'] ?? [];

            // Set final trigger event string
            if ($triggerType === 'tag_added') {
                if (!empty($triggerTagIds)) {
                    $triggerEvent = "tag_added:" . implode(',', array_map('intval', $triggerTagIds));
                } else {
                    $triggerEvent = "tag_added:{$triggerTagId}";
                }
            } elseif ($triggerType === 'form_submit') {
                $triggerEvent = "form_submit:{$triggerFormId}";
            } elseif ($triggerType === 'email_open') {
                $triggerEvent = "email_open:{$triggerCampaignId}";
            } elseif ($triggerType === 'link_click') {
                $triggerEvent = "link_click:{$triggerCampaignId}";
            } elseif ($triggerType === 'points_threshold') {
                $triggerEvent = "points_threshold:{$triggerPoints}";
            } else {
                $triggerEvent = 'subscribe';
            }

            if ($name === '' || empty($steps)) {
                $_SESSION['flash_error'] = 'Automation name and at least one step are required.';
            } else {
                try {
                    $db->beginTransaction();
                    
                    // 1. Insert Automation Header
                    $excludeTagId = isset($_POST['exclude_tag_id']) && $_POST['exclude_tag_id'] !== '' ? (int)$_POST['exclude_tag_id'] : null;
                    $stAuto = $db->prepare("INSERT INTO automations (name, trigger_event, status, exclude_tag_id, created_at) VALUES (?, ?, 'active', ?, NOW())");
                    $stAuto->execute([$name, $triggerEvent, $excludeTagId]);
                    $autoId = (int)$db->lastInsertId();
                    
                    // 2. Insert Automation Steps Sequentially
                    $stStep = $db->prepare("INSERT INTO automation_steps (automation_id, step_type, step_value, order_num) VALUES (?, ?, ?, ?)");
                    
                    $order = 1;
                    foreach ($steps as $step) {
                        $type = $step['type'] ?? '';
                        
                        // Parse values based on step type
                        if ($type === 'wait') {
                            $value = trim($step['wait_value'] ?? '');
                            if ($value !== '') {
                                $stStep->execute([$autoId, 'wait', $value, $order++]);
                            }
                        } elseif ($type === 'send_email') {
                            $value = trim($step['campaign_id'] ?? '');
                            if ($value !== '') {
                                $stStep->execute([$autoId, 'send_email', $value, $order++]);
                            }
                        } elseif ($type === 'send_sms') {
                            $value = trim($step['sms_message'] ?? '');
                            if ($value !== '') {
                                $stStep->execute([$autoId, 'send_sms', $value, $order++]);
                            }
                        } elseif ($type === 'add_tag') {
                            $value = trim($step['tag_id'] ?? '');
                            if ($value !== '') {
                                $stStep->execute([$autoId, 'add_tag', $value, $order++]);
                            }
                        } elseif ($type === 'remove_tag') {
                            $value = trim($step['tag_id'] ?? '');
                            if ($value !== '') {
                                $stStep->execute([$autoId, 'remove_tag', $value, $order++]);
                            }
                        } elseif ($type === 'add_to_list') {
                            $value = trim($step['list_id'] ?? '');
                            if ($value !== '') {
                                $stStep->execute([$autoId, 'add_to_list', $value, $order++]);
                            }
                        } elseif ($type === 'remove_from_list') {
                            $value = trim($step['list_id'] ?? '');
                            if ($value !== '') {
                                $stStep->execute([$autoId, 'remove_from_list', $value, $order++]);
                            }
                        } elseif ($type === 'adjust_points') {
                            $value = trim($step['points_value'] ?? '');
                            if ($value !== '') {
                                $stStep->execute([$autoId, 'adjust_points', $value, $order++]);
                            }
                        } elseif ($type === 'trigger_webhook') {
                            $value = trim($step['webhook_url'] ?? '');
                            if ($value !== '') {
                                $stStep->execute([$autoId, 'trigger_webhook', $value, $order++]);
                            }
                        } elseif ($type === 'send_if_opened') {
                            $camp = trim($step['campaign_id'] ?? '');
                            $prev = trim($step['prev_campaign_id'] ?? '');
                            if ($camp !== '' && $prev !== '') {
                                $stStep->execute([$autoId, 'send_if_opened', "{$camp}:{$prev}", $order++]);
                            }
                        } elseif ($type === 'send_if_not_opened') {
                            $camp = trim($step['campaign_id'] ?? '');
                            $prev = trim($step['prev_campaign_id'] ?? '');
                            if ($camp !== '' && $prev !== '') {
                                $stStep->execute([$autoId, 'send_if_not_opened', "{$camp}:{$prev}", $order++]);
                            }
                        } elseif ($type === 'tag_if_not_opened') {
                            $tg = trim($step['tag_id'] ?? '');
                            $prev = trim($step['prev_campaign_id'] ?? '');
                            if ($tg !== '' && $prev !== '') {
                                $stStep->execute([$autoId, 'tag_if_not_opened', "{$tg}:{$prev}", $order++]);
                            }
                        } elseif ($type === 'send_if_clicked') {
                            $camp = trim($step['campaign_id'] ?? '');
                            $prev = trim($step['prev_campaign_id'] ?? '');
                            if ($camp !== '' && $prev !== '') {
                                $stStep->execute([$autoId, 'send_if_clicked', "{$camp}:{$prev}", $order++]);
                            }
                        } elseif ($type === 'tag_if_clicked') {
                            $tg = trim($step['tag_id'] ?? '');
                            $prev = trim($step['prev_campaign_id'] ?? '');
                            if ($tg !== '' && $prev !== '') {
                                $stStep->execute([$autoId, 'tag_if_clicked', "{$tg}:{$prev}", $order++]);
                            }
                        } elseif ($type === 'send_if_has_tag') {
                            $camp = trim($step['campaign_id'] ?? '');
                            $tg = trim($step['tag_id'] ?? '');
                            if ($camp !== '' && $tg !== '') {
                                $stStep->execute([$autoId, 'send_if_has_tag', "{$camp}:{$tg}", $order++]);
                            }
                        } elseif ($type === 'send_if_has_no_tag') {
                            $camp = trim($step['campaign_id'] ?? '');
                            $tg = trim($step['tag_id'] ?? '');
                            if ($camp !== '' && $tg !== '') {
                                $stStep->execute([$autoId, 'send_if_has_no_tag', "{$camp}:{$tg}", $order++]);
                            }
                        }
                    }
                    
                    $db->commit();
                    $_SESSION['flash_success'] = 'Automation sequence created and activated successfully!';
                    header('Location: ' . getSetting('app_url') . '/automations');
                    exit;

                } catch (Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $_SESSION['flash_error'] = 'Failed to create automation: ' . $e->getMessage();
                }
            }
        }
        
        // Fetch campaigns, tags, forms, and lists for drop-down selectors
        $campaigns = $db->query("SELECT id, name FROM campaigns ORDER BY name ASC")->fetchAll();
        $tags = $db->query("SELECT id, name FROM tags ORDER BY name ASC")->fetchAll();
        $forms = $db->query("SELECT id, name FROM forms ORDER BY name ASC")->fetchAll();
        $lists = $db->query("SELECT id, name FROM lists ORDER BY name ASC")->fetchAll();
        
        $title = 'Create Automation';
        $viewPath = dirname(__DIR__) . '/views/automation_create.php';
        include dirname(__DIR__) . '/views/layout.php';
    }

    public function edit(): void {
        $db = Database::getConnection();
        
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'Invalid automation ID.';
            header('Location: ' . getSetting('app_url') . '/automations');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $triggerType = trim($_POST['trigger_type'] ?? 'subscribe');
            $triggerTagId = (int)($_POST['trigger_tag_id'] ?? 0);
            $triggerTagIds = $_POST['trigger_tag_ids'] ?? [];
            $triggerFormId = (int)($_POST['trigger_form_id'] ?? 0);
            $triggerCampaignId = (int)($_POST['trigger_campaign_id'] ?? 0);
            $triggerPoints = (int)($_POST['trigger_points'] ?? 0);
            $steps = $_POST['steps'] ?? [];

            // Set final trigger event string
            if ($triggerType === 'tag_added') {
                if (!empty($triggerTagIds)) {
                    $triggerEvent = "tag_added:" . implode(',', array_map('intval', $triggerTagIds));
                } else {
                    $triggerEvent = "tag_added:{$triggerTagId}";
                }
            } elseif ($triggerType === 'form_submit') {
                $triggerEvent = "form_submit:{$triggerFormId}";
            } elseif ($triggerType === 'email_open') {
                $triggerEvent = "email_open:{$triggerCampaignId}";
            } elseif ($triggerType === 'link_click') {
                $triggerEvent = "link_click:{$triggerCampaignId}";
            } elseif ($triggerType === 'points_threshold') {
                $triggerEvent = "points_threshold:{$triggerPoints}";
            } else {
                $triggerEvent = 'subscribe';
            }

            if ($name === '' || empty($steps)) {
                $_SESSION['flash_error'] = 'Automation name and at least one step are required.';
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Update Automation Header
                    $excludeTagId = isset($_POST['exclude_tag_id']) && $_POST['exclude_tag_id'] !== '' ? (int)$_POST['exclude_tag_id'] : null;
                    $db->prepare("UPDATE automations SET name = ?, trigger_event = ?, exclude_tag_id = ? WHERE id = ?")->execute([$name, $triggerEvent, $excludeTagId, $id]);
                    
                    // Clear existing steps
                    $db->prepare("DELETE FROM automation_steps WHERE automation_id = ?")->execute([$id]);

                    // Re-insert Automation Steps Sequentially
                    $stStep = $db->prepare("INSERT INTO automation_steps (automation_id, step_type, step_value, order_num) VALUES (?, ?, ?, ?)");
                    
                    $order = 1;
                    foreach ($steps as $step) {
                        $type = $step['type'] ?? '';
                        
                        if ($type === 'wait') {
                            $value = trim($step['wait_value'] ?? '');
                            if ($value !== '') $stStep->execute([$id, 'wait', $value, $order++]);
                        } elseif ($type === 'send_email') {
                            $value = trim($step['campaign_id'] ?? '');
                            if ($value !== '') $stStep->execute([$id, 'send_email', $value, $order++]);
                        } elseif ($type === 'send_sms') {
                            $value = trim($step['sms_message'] ?? '');
                            if ($value !== '') $stStep->execute([$id, 'send_sms', $value, $order++]);
                        } elseif ($type === 'add_tag') {
                            $value = trim($step['tag_id'] ?? '');
                            if ($value !== '') $stStep->execute([$id, 'add_tag', $value, $order++]);
                        } elseif ($type === 'remove_tag') {
                            $value = trim($step['tag_id'] ?? '');
                            if ($value !== '') $stStep->execute([$id, 'remove_tag', $value, $order++]);
                        } elseif ($type === 'add_to_list') {
                            $value = trim($step['list_id'] ?? '');
                            if ($value !== '') $stStep->execute([$id, 'add_to_list', $value, $order++]);
                        } elseif ($type === 'remove_from_list') {
                            $value = trim($step['list_id'] ?? '');
                            if ($value !== '') $stStep->execute([$id, 'remove_from_list', $value, $order++]);
                        } elseif ($type === 'adjust_points') {
                            $value = trim($step['points_value'] ?? '');
                            if ($value !== '') $stStep->execute([$id, 'adjust_points', $value, $order++]);
                        } elseif ($type === 'trigger_webhook') {
                            $value = trim($step['webhook_url'] ?? '');
                            if ($value !== '') $stStep->execute([$id, 'trigger_webhook', $value, $order++]);
                        } elseif ($type === 'send_if_opened') {
                            $camp = trim($step['campaign_id'] ?? '');
                            $prev = trim($step['prev_campaign_id'] ?? '');
                            if ($camp !== '' && $prev !== '') $stStep->execute([$id, 'send_if_opened', "{$camp}:{$prev}", $order++]);
                        } elseif ($type === 'send_if_not_opened') {
                            $camp = trim($step['campaign_id'] ?? '');
                            $prev = trim($step['prev_campaign_id'] ?? '');
                            if ($camp !== '' && $prev !== '') $stStep->execute([$id, 'send_if_not_opened', "{$camp}:{$prev}", $order++]);
                        } elseif ($type === 'tag_if_not_opened') {
                            $tg = trim($step['tag_id'] ?? '');
                            $prev = trim($step['prev_campaign_id'] ?? '');
                            if ($tg !== '' && $prev !== '') $stStep->execute([$id, 'tag_if_not_opened', "{$tg}:{$prev}", $order++]);
                        } elseif ($type === 'send_if_clicked') {
                            $camp = trim($step['campaign_id'] ?? '');
                            $prev = trim($step['prev_campaign_id'] ?? '');
                            if ($camp !== '' && $prev !== '') $stStep->execute([$id, 'send_if_clicked', "{$camp}:{$prev}", $order++]);
                        } elseif ($type === 'tag_if_clicked') {
                            $tg = trim($step['tag_id'] ?? '');
                            $prev = trim($step['prev_campaign_id'] ?? '');
                            if ($tg !== '' && $prev !== '') $stStep->execute([$id, 'tag_if_clicked', "{$tg}:{$prev}", $order++]);
                        } elseif ($type === 'send_if_has_tag') {
                            $camp = trim($step['campaign_id'] ?? '');
                            $tg = trim($step['tag_id'] ?? '');
                            if ($camp !== '' && $tg !== '') $stStep->execute([$id, 'send_if_has_tag', "{$camp}:{$tg}", $order++]);
                        } elseif ($type === 'send_if_has_no_tag') {
                            $camp = trim($step['campaign_id'] ?? '');
                            $tg = trim($step['tag_id'] ?? '');
                            if ($camp !== '' && $tg !== '') $stStep->execute([$id, 'send_if_has_no_tag', "{$camp}:{$tg}", $order++]);
                        }
                    }
                    
                    $db->commit();
                    $_SESSION['flash_success'] = 'Automation sequence updated successfully!';
                    header('Location: ' . getSetting('app_url') . '/automations');
                    exit;

                } catch (Throwable $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $_SESSION['flash_error'] = 'Failed to update automation: ' . $e->getMessage();
                }
            }
        }
        
        $st = $db->prepare("SELECT * FROM automations WHERE id = ?");
        $st->execute([$id]);
        $automation = $st->fetch();
        if (!$automation) {
            $_SESSION['flash_error'] = 'Automation not found.';
            header('Location: ' . getSetting('app_url') . '/automations');
            exit;
        }

        $stSteps = $db->prepare("SELECT * FROM automation_steps WHERE automation_id = ? ORDER BY order_num ASC");
        $stSteps->execute([$id]);
        $automationSteps = $stSteps->fetchAll();

        // Fetch campaigns, tags, forms, and lists for drop-down selectors
        $campaigns = $db->query("SELECT id, name FROM campaigns ORDER BY name ASC")->fetchAll();
        $tags = $db->query("SELECT id, name FROM tags ORDER BY name ASC")->fetchAll();
        $forms = $db->query("SELECT id, name FROM forms ORDER BY name ASC")->fetchAll();
        $lists = $db->query("SELECT id, name FROM lists ORDER BY name ASC")->fetchAll();
        
        $title = 'Edit Automation: ' . $automation['name'];
        $viewPath = dirname(__DIR__) . '/views/automation_edit.php';
        include dirname(__DIR__) . '/views/layout.php';
    }
}
