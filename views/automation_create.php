<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <a href="<?= e(getSetting('app_url')) ?>/automations" style="color: var(--theme-dark-slate); font-weight: 500; font-size: 13px; text-decoration: none; display: flex; align-items: center; gap: 4px; margin-bottom: 8px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Automations
        </a>
        <h1>Create Automation Sequence</h1>
        <p>Define dynamic, step-by-step subscriber workflows triggered by list events or CRM tags.</p>
    </div>
</div>

<div style="max-width: 800px; margin: auto;">
    <form method="post" action="">
        <?= Auth::csrfField() ?>
        <!-- Automation Info -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header"><span class="card-title">Automation Trigger Details</span></div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="name">Automation Name</label>
                    <input class="form-control" type="text" id="name" name="name" required placeholder="e.g. Lead Onboarding Pipeline">
                </div>
                <div class="form-group">
                    <label class="form-label" for="trigger_type">Trigger Type</label>
                    <select class="form-control" id="trigger_type" name="trigger_type" onchange="toggleTriggerType(this.value)" required>
                        <option value="subscribe">On Subscriber Joined List (Segment Membership)</option>
                        <option value="tag_added">On Tag Assigned to Contact (Contact Tagged)</option>
                        <option value="form_submit">On Form Submitted (Form Submission)</option>
                        <option value="email_open">On Email Opened (Email Opened)</option>
                        <option value="link_click">On Link Clicked (Email Link Clicked)</option>
                        <option value="points_threshold">On Lead Score Exceeds Threshold (Lead Score Change)</option>
                    </select>
                </div>
            </div>

            <!-- Trigger Tag Selection -->
            <div class="form-group trigger-conditional-group" id="trigger_tag_group" style="display: none; margin-top: 12px;">
                <label class="form-label">Target Trigger CRM Tag(s) (Select multiple if desired)</label>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; padding: 12px; border: 1px solid var(--theme-border); border-radius: 6px; background-color: #fafbfc; max-height: 120px; overflow-y: auto;">
                    <?php foreach ($tags as $t): ?>
                        <label style="display: inline-flex; align-items: center; gap: 6px; background-color: white; border: 1px solid var(--theme-border); padding: 4px 10px; border-radius: 20px; font-size: 12px; cursor: pointer; font-weight: 500;">
                            <input type="checkbox" name="trigger_tag_ids[]" value="<?= $t['id'] ?>" style="accent-color: var(--theme-blurple);">
                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: <?= e($t['color'] ?? '#635bff') ?>;"></span>
                            <?= e($t['name']) ?>
                        </label>
                    <?php endforeach; ?>
                    <?php if (empty($tags)): ?>
                        <span style="color: var(--theme-dark-slate); font-size: 12px;">No tags created.</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Trigger Form Selection -->
            <div class="form-group trigger-conditional-group" id="trigger_form_group" style="display: none; margin-top: 12px;">
                <label class="form-label" for="trigger_form_id">Target Trigger Signup Form</label>
                <select class="form-control" id="trigger_form_id" name="trigger_form_id">
                    <option value="">-- Select Form to Trigger On --</option>
                    <?php foreach ($forms as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= e($f['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Trigger Campaign Selection -->
            <div class="form-group trigger-conditional-group" id="trigger_campaign_group" style="display: none; margin-top: 12px;">
                <label class="form-label" for="trigger_campaign_id">Target Trigger Campaign</label>
                <select class="form-control" id="trigger_campaign_id" name="trigger_campaign_id">
                    <option value="">-- Select Campaign --</option>
                    <?php foreach ($campaigns as $camp): ?>
                        <option value="<?= $camp['id'] ?>"><?= e($camp['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Trigger Lead Score Threshold -->
            <div class="form-group trigger-conditional-group" id="trigger_points_group" style="display: none; margin-top: 12px;">
                <label class="form-label" for="trigger_points">Target Lead Score Threshold</label>
                <input class="form-control" type="number" id="trigger_points" name="trigger_points" placeholder="e.g. 50">
            </div>

            <!-- Exclude Contacts with Tag -->
            <div class="form-group" style="margin-top: 16px; border-top: 1px solid var(--theme-border); padding-top: 16px;">
                <label class="form-label" for="exclude_tag_id">Exclude Contacts with Tag (Optional)</label>
                <select class="form-control" id="exclude_tag_id" name="exclude_tag_id">
                    <option value="">-- No Exclusion Tag (All Contacts Eligible) --</option>
                    <?php if (empty($tags)): ?>
                        <option value="">-- No tags found. Create one first --</option>
                    <?php else: ?>
                        <?php foreach ($tags as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <p style="font-size: 11px; color: var(--theme-dark-slate); margin-top: 4px;">Contacts carrying this tag will be automatically skipped and removed from the queue for this automation.</p>
            </div>
        </div>

        <!-- Automation Steps Pipeline -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header" style="justify-content: space-between; border-bottom: 1px solid var(--theme-border); padding-bottom: 16px; margin-bottom: 20px;">
                <span class="card-title">Workflow Steps Sequence</span>
                <span style="font-size: 11px; color: var(--theme-dark-slate);">Executed sequentially top to bottom.</span>
            </div>

            <!-- Steps Container -->
            <div id="steps_container" style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px;">
                <!-- Initial Step 1 -->
                <div class="step-card" style="display: flex; flex-direction: column; gap: 16px; padding: 20px; border: 1px solid var(--theme-border); border-radius: 8px; background-color: var(--theme-bg); position: relative;">
                    <div style="position: absolute; top: -10px; left: 16px; background-color: var(--theme-blurple); color: white; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px;" class="step-badge">Step 1</div>
                    
                    <div style="display: flex; gap: 16px; align-items: flex-end;">
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label class="form-label">Action / Decision Type</label>
                            <select class="form-control step-type-select" name="steps[0][type]" onchange="toggleStepInputs(this)" required>
                                <optgroup label="Actions">
                                    <option value="send_email">Send Campaign Email</option>
                                    <option value="send_sms">Send SMS Notification</option>
                                    <option value="wait">Wait Delay</option>
                                    <option value="add_tag">Add Tag to Contact</option>
                                    <option value="remove_tag">Remove Tag from Contact</option>
                                    <option value="add_to_list">Add to Subscriber List</option>
                                    <option value="remove_from_list">Remove from List</option>
                                    <option value="adjust_points">Adjust CRM Lead Score</option>
                                    <option value="trigger_webhook">Trigger Custom Outbound Webhook</option>
                                </optgroup>
                                <optgroup label="Decisions & Conditions">
                                    <option value="send_if_opened">Send Email if Opened</option>
                                    <option value="send_if_not_opened">Send Email if NOT Opened</option>
                                    <option value="tag_if_not_opened">Add Tag if NOT Opened</option>
                                    <option value="send_if_clicked">Send Email if Link Clicked</option>
                                    <option value="tag_if_clicked">Add Tag if Link Clicked</option>
                                    <option value="send_if_has_tag">Send Email if has tag</option>
                                    <option value="send_if_has_no_tag">Send Email if doesn't have tag</option>
                                </optgroup>
                            </select>
                        </div>

                        <!-- Dynamic value wrappers -->
                        <div style="flex: 2; display: flex; flex-direction: column; gap: 12px;" class="step-value-inputs">
                            
                            <!-- Campaign selector (default shown) -->
                            <div class="input-block block-campaign">
                                <label class="form-label">Select Campaign to Send</label>
                                <select class="form-control" name="steps[0][campaign_id]">
                                    <?php if (empty($campaigns)): ?>
                                        <option value="">-- No campaigns found. Create one first --</option>
                                    <?php else: ?>
                                        <?php foreach ($campaigns as $camp): ?>
                                            <option value="<?= $camp['id'] ?>"><?= e($camp['name']) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- SMS Message Input -->
                            <div class="input-block block-sms" style="display: none;">
                                <label class="form-label">SMS Notification Text Message</label>
                                <input class="form-control" type="text" name="steps[0][sms_message]" placeholder="e.g. Hey {first_name}, check your inbox for our guide!">
                            </div>

                            <!-- Wait delay input (hidden) -->
                            <div class="input-block block-wait" style="display: none;">
                                <label class="form-label">Wait Duration</label>
                                <input class="form-control" type="text" name="steps[0][wait_value]" placeholder="e.g. 7 days, 2 hours, 3 days">
                            </div>

                            <!-- Tag Selector (hidden) -->
                            <div class="input-block block-tag" style="display: none;">
                                <label class="form-label">Select CRM Tag</label>
                                <select class="form-control" name="steps[0][tag_id]">
                                    <?php if (empty($tags)): ?>
                                        <option value="">-- No tags found. Create one first --</option>
                                    <?php else: ?>
                                        <?php foreach ($tags as $t): ?>
                                            <option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- List Selector (hidden) -->
                            <div class="input-block block-list" style="display: none;">
                                <label class="form-label">Select Subscriber List</label>
                                <select class="form-control" name="steps[0][list_id]">
                                    <?php if (empty($lists)): ?>
                                        <option value="">-- No lists found. Create one first --</option>
                                    <?php else: ?>
                                        <?php foreach ($lists as $l): ?>
                                            <option value="<?= $l['id'] ?>"><?= e($l['name']) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- Points Adjustment Input -->
                            <div class="input-block block-points" style="display: none;">
                                <label class="form-label">Adjust CRM Lead Score Points (+/-)</label>
                                <input class="form-control" type="text" name="steps[0][points_value]" placeholder="e.g. +10, -5">
                            </div>

                            <!-- Webhook URL input -->
                            <div class="input-block block-webhook" style="display: none;">
                                <label class="form-label">Outbound Webhook URL</label>
                                <input class="form-control" type="url" name="steps[0][webhook_url]" placeholder="https://api.my-crm.com/webhook">
                            </div>

                            <!-- Prev Campaign selector (hidden) -->
                            <div class="input-block block-prev-campaign" style="display: none;">
                                <label class="form-label">Relative to Campaign</label>
                                <select class="form-control" name="steps[0][prev_campaign_id]">
                                    <option value="">-- Choose Previous Campaign --</option>
                                    <?php if (empty($campaigns)): ?>
                                        <option value="">-- No campaigns found. Create one first --</option>
                                    <?php else: ?>
                                        <?php foreach ($campaigns as $camp): ?>
                                            <option value="<?= $camp['id'] ?>"><?= e($camp['name']) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                        </div>

                        <button type="button" class="btn btn-danger" onclick="removeStep(this)" style="padding: 10px; line-height: 1; height: 38px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>
                    </div>
                </div>
            </div>

            <!-- Actions to Add steps -->
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <button type="button" class="btn btn-secondary" onclick="addStep()">+ Add Next Sequence Step</button>
                <div style="display: flex; gap: 12px;">
                    <a href="<?= e(getSetting('app_url')) ?>/automations" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:6px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>Save and Activate Flow →</button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- JavaScript Step Builder Controller -->
<script>
    let stepIndex = 1;

    // Trigger forms select toggler
    function toggleTriggerType(val) {
        // Hide all trigger groups first
        document.querySelectorAll(".trigger-conditional-group").forEach(el => {
            el.style.display = "none";
            const selectOrInput = el.querySelector("select, input[type='text'], input[type='number']");
            if (selectOrInput) {
                selectOrInput.removeAttribute("required");
                selectOrInput.value = "";
            }
            el.querySelectorAll("input[type='checkbox']").forEach(cb => {
                cb.checked = false;
            });
        });

        if (val === "tag_added") {
            const group = document.getElementById("trigger_tag_group");
            group.style.display = "block";
        } else if (val === "form_submit") {
            const group = document.getElementById("trigger_form_group");
            group.style.display = "block";
            group.querySelector("select").setAttribute("required", "required");
        } else if (val === "email_open" || val === "link_click") {
            const group = document.getElementById("trigger_campaign_group");
            group.style.display = "block";
            group.querySelector("select").setAttribute("required", "required");
        } else if (val === "points_threshold") {
            const group = document.getElementById("trigger_points_group");
            group.style.display = "block";
            group.querySelector("input").setAttribute("required", "required");
        }
    }

    // HTML templates for dynamic injection
    const campaignsOptions = `
        <?php if (empty($campaigns)): ?>
            <option value="">-- No campaigns found. Create one first --</option>
        <?php else: ?>
            <?php foreach ($campaigns as $camp): ?>
                <option value="<?= $camp['id'] ?>"><?= json_encode($camp['name'], JSON_HEX_TAG | JSON_HEX_APOS) ?></option>
            <?php endforeach; ?>
        <?php endif; ?>
    `;

    const tagsOptions = `
        <?php if (empty($tags)): ?>
            <option value="">-- No tags found. Create one first --</option>
        <?php else: ?>
            <?php foreach ($tags as $t): ?>
                <option value="<?= $t['id'] ?>"><?= json_encode($t['name'], JSON_HEX_TAG | JSON_HEX_APOS) ?></option>
            <?php endforeach; ?>
        <?php endif; ?>
    `;

    const listsOptions = `
        <?php if (empty($lists)): ?>
            <option value="">-- No lists found. Create one first --</option>
        <?php else: ?>
            <?php foreach ($lists as $l): ?>
                <option value="<?= $l['id'] ?>"><?= json_encode($l['name'], JSON_HEX_TAG | JSON_HEX_APOS) ?></option>
            <?php endforeach; ?>
        <?php endif; ?>
    `;

    function addStep() {
        const container = document.getElementById("steps_container");
        const idx = stepIndex++;
        
        const stepHtml = `
            <div class="step-card" style="display: flex; flex-direction: column; gap: 16px; padding: 20px; border: 1px solid var(--theme-border); border-radius: 8px; background-color: var(--theme-bg); position: relative; margin-top: 4px;">
                <div style="position: absolute; top: -10px; left: 16px; background-color: var(--theme-blurple); color: white; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px;" class="step-badge">Step ${container.children.length + 1}</div>
                
                <div style="display: flex; gap: 16px; align-items: flex-end;">
                    <div class="form-group" style="flex: 1; margin-bottom: 0;">
                        <label class="form-label">Action / Decision Type</label>
                        <select class="form-control step-type-select" name="steps[${idx}][type]" onchange="toggleStepInputs(this)" required>
                            <optgroup label="Actions">
                                <option value="send_email">Send Campaign Email</option>
                                <option value="send_sms">Send SMS Notification</option>
                                <option value="wait">Wait Delay</option>
                                <option value="add_tag">Add Tag to Contact</option>
                                <option value="remove_tag">Remove Tag from Contact</option>
                                <option value="add_to_list">Add to Subscriber List</option>
                                <option value="remove_from_list">Remove from List</option>
                                <option value="adjust_points">Adjust CRM Lead Score</option>
                                <option value="trigger_webhook">Trigger Custom Outbound Webhook</option>
                            </optgroup>
                            <optgroup label="Decisions & Conditions">
                                <option value="send_if_opened">Send Email if Opened</option>
                                <option value="send_if_not_opened">Send Email if NOT Opened</option>
                                <option value="tag_if_not_opened">Add Tag if NOT Opened</option>
                                <option value="send_if_clicked">Send Email if Link Clicked</option>
                                <option value="tag_if_clicked">Add Tag if Link Clicked</option>
                                <option value="send_if_has_tag">Send Email if has tag</option>
                                <option value="send_if_has_no_tag">Send Email if doesn't have tag</option>
                            </optgroup>
                        </select>
                    </div>

                    <div style="flex: 2; display: flex; flex-direction: column; gap: 12px;" class="step-value-inputs">
                        <div class="input-block block-campaign">
                            <label class="form-label">Select Campaign to Send</label>
                            <select class="form-control" name="steps[${idx}][campaign_id]">
                                ${campaignsOptions}
                            </select>
                        </div>

                        <div class="input-block block-sms" style="display: none;">
                            <label class="form-label">SMS Notification Text Message</label>
                            <input class="form-control" type="text" name="steps[${idx}][sms_message]" placeholder="e.g. Hey {first_name}, check your inbox for our guide!">
                        </div>

                        <div class="input-block block-wait" style="display: none;">
                            <label class="form-label">Wait Duration</label>
                            <input class="form-control" type="text" name="steps[${idx}][wait_value]" placeholder="e.g. 7 days, 2 hours, 3 days">
                        </div>

                        <div class="input-block block-tag" style="display: none;">
                            <label class="form-label">Select CRM Tag</label>
                            <select class="form-control" name="steps[${idx}][tag_id]">
                                ${tagsOptions}
                            </select>
                        </div>

                        <div class="input-block block-list" style="display: none;">
                            <label class="form-label">Select Subscriber List</label>
                            <select class="form-control" name="steps[${idx}][list_id]">
                                ${listsOptions}
                            </select>
                        </div>

                        <div class="input-block block-points" style="display: none;">
                            <label class="form-label">Adjust CRM Lead Score Points (+/-)</label>
                            <input class="form-control" type="text" name="steps[${idx}][points_value]" placeholder="e.g. +10, -5">
                        </div>

                        <div class="input-block block-webhook" style="display: none;">
                            <label class="form-label">Outbound Webhook URL</label>
                            <input class="form-control" type="url" name="steps[${idx}][webhook_url]" placeholder="https://api.my-crm.com/webhook">
                        </div>

                        <div class="input-block block-prev-campaign" style="display: none;">
                            <label class="form-label">Relative to Campaign</label>
                            <select class="form-control" name="steps[${idx}][prev_campaign_id]">
                                <option value="">-- Choose Previous Campaign --</option>
                                ${campaignsOptions}
                            </select>
                        </div>
                    </div>

                    <button type="button" class="btn btn-danger" onclick="removeStep(this)" style="padding: 10px; line-height: 1; height: 38px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button>
                </div>
            </div>
        `;
        
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = stepHtml.trim();
        container.appendChild(tempDiv.firstChild);
        reindexSteps();
    }

    function removeStep(btn) {
        const container = document.getElementById("steps_container");
        if (container.children.length > 1) {
            btn.closest(".step-card").remove();
            reindexSteps();
        } else {
            alert("Your automation requires at least one sequence step.");
        }
    }

    function reindexSteps() {
        const cards = document.querySelectorAll("#steps_container .step-card");
        cards.forEach((card, index) => {
            const badge = card.querySelector(".step-badge");
            if (badge) {
                badge.textContent = `Step ${index + 1}`;
            }
        });
    }

    function toggleStepInputs(select) {
        const card = select.closest(".step-card");
        
        // Hide all input blocks inside this card first
        card.querySelectorAll(".input-block").forEach(block => block.style.display = "none");
        
        const val = select.value;
        
        if (val === "wait") {
            card.querySelector(".block-wait").style.display = "block";
        } 
        else if (val === "send_email") {
            card.querySelector(".block-campaign").style.display = "block";
        } 
        else if (val === "send_sms") {
            card.querySelector(".block-sms").style.display = "block";
        }
        else if (val === "add_tag" || val === "remove_tag" || val === "send_if_has_tag" || val === "send_if_has_no_tag") {
            card.querySelector(".block-tag").style.display = "block";
            if (val === "send_if_has_tag" || val === "send_if_has_no_tag") {
                card.querySelector(".block-campaign").style.display = "block";
            }
        } 
        else if (val === "add_to_list" || val === "remove_from_list") {
            card.querySelector(".block-list").style.display = "block";
        }
        else if (val === "adjust_points") {
            card.querySelector(".block-points").style.display = "block";
        }
        else if (val === "trigger_webhook") {
            card.querySelector(".block-webhook").style.display = "block";
        }
        else if (val === "send_if_opened" || val === "send_if_not_opened" || val === "send_if_clicked") {
            card.querySelector(".block-campaign").style.display = "block";
            card.querySelector(".block-prev-campaign").style.display = "block";
        } 
        else if (val === "tag_if_not_opened" || val === "tag_if_clicked") {
            card.querySelector(".block-tag").style.display = "block";
            card.querySelector(".block-prev-campaign").style.display = "block";
        }
    }
</script>
