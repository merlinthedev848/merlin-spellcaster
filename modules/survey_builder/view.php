<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Survey & Form Builder</h1>
        <p>Design multi-step surveys to capture zero-party data. Responses can automatically tag subscribers.</p>
    </div>
    <button class="btn btn-primary" onclick="saveSurvey()">Save & Publish</button>
</div>

<div class="grid grid-1-3" style="align-items: start; margin-bottom: 24px;">
    <!-- Left Pane: Builder Controls -->
    <div class="card" style="padding: 24px; min-height: 400px; display: flex; flex-direction: column;">
        <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
            <span class="card-title">Survey Details</span>
        </div>
        
        <div class="form-group">
            <label class="form-label" for="survey_title">Survey Title</label>
            <input class="form-control" type="text" id="survey_title" placeholder="e.g. 2026 Customer Feedback">
        </div>
        
        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label">Add Question Block</label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <button type="button" class="btn btn-secondary" onclick="addBlock('text')" style="justify-content: center;">Text Input</button>
                <button type="button" class="btn btn-secondary" onclick="addBlock('radio')" style="justify-content: center;">Multiple Choice</button>
                <button type="button" class="btn btn-secondary" onclick="addBlock('rating')" style="justify-content: center;">NPS Rating</button>
                <button type="button" class="btn btn-secondary" onclick="addBlock('email')" style="justify-content: center;">Email Capture</button>
            </div>
        </div>
    </div>

    <!-- Right Pane: Canvas -->
    <div class="card" style="padding: 24px; min-height: 400px; background: #f8fafc;">
        <div class="card-header" style="margin-bottom: 12px; border-bottom: none; padding-bottom: 0;">
            <span class="card-title" style="color: #334155;">Visual Canvas</span>
        </div>
        
        <div id="survey_canvas" style="display: flex; flex-direction: column; gap: 16px;">
            <div id="empty_state" style="text-align: center; padding: 40px; color: #94a3b8; border: 2px dashed #cbd5e1; border-radius: 8px;">
                Click a button on the left to add your first question block.
            </div>
        </div>
    </div>
</div>

<script>
let blockCounter = 0;
const canvas = document.getElementById('survey_canvas');
const emptyState = document.getElementById('empty_state');

function addBlock(type) {
    if (emptyState) emptyState.style.display = 'none';
    blockCounter++;
    
    const block = document.createElement('div');
    block.style.cssText = "background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); position: relative;";
    block.id = `block_${blockCounter}`;
    
    const deleteBtn = `<button type="button" onclick="document.getElementById('block_${blockCounter}').remove()" style="position: absolute; top: 16px; right: 16px; color: #ef4444; background: none; border: none; cursor: pointer;">✕</button>`;
    
    let content = '';
    
    if (type === 'text') {
        content = `
            <input type="text" value="Untitled Question" style="font-weight: bold; border: none; font-size: 1.1rem; width: 90%; outline: none; margin-bottom: 8px;" class="question-input">
            <input type="text" placeholder="Short answer text" disabled style="width: 100%; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; background: #f8fafc;">
        `;
    } else if (type === 'radio') {
        content = `
            <input type="text" value="Multiple Choice Question" style="font-weight: bold; border: none; font-size: 1.1rem; width: 90%; outline: none; margin-bottom: 8px;" class="question-input">
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <div><input type="radio" disabled> <input type="text" value="Option 1" style="border: none; border-bottom: 1px solid #e2e8f0; outline: none;"></div>
                <div><input type="radio" disabled> <input type="text" value="Option 2" style="border: none; border-bottom: 1px solid #e2e8f0; outline: none;"></div>
            </div>
        `;
    } else if (type === 'rating') {
        content = `
            <input type="text" value="How likely are you to recommend us?" style="font-weight: bold; border: none; font-size: 1.1rem; width: 90%; outline: none; margin-bottom: 8px;" class="question-input">
            <div style="display: flex; gap: 4px; margin-top: 8px;">
                ${[1,2,3,4,5,6,7,8,9,10].map(n => `<div style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 50%; color: #64748b; font-size: 0.9rem;">${n}</div>`).join('')}
            </div>
        `;
    } else if (type === 'email') {
        content = `
            <input type="text" value="What is your email address?" style="font-weight: bold; border: none; font-size: 1.1rem; width: 90%; outline: none; margin-bottom: 8px;" class="question-input">
            <input type="email" placeholder="john@example.com" disabled style="width: 100%; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px; background: #f8fafc;">
        `;
    }
    
    block.innerHTML = deleteBtn + content;
    canvas.appendChild(block);
}

function saveSurvey() {
    const title = document.getElementById('survey_title').value.trim();
    if (!title) {
        alert("Please enter a survey title before saving.");
        return;
    }
    
    fetch('/survey-builder/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title: title, blocks: blockCounter })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
        } else {
            alert("Error: " + data.error);
        }
    })
    .catch(err => alert("Network error."));
}
</script>
