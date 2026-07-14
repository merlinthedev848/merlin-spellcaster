<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Visual Email Builder</h1>
        <p>Design responsive emails using drag-and-drop components.</p>
    </div>
    <div>
        <button class="btn btn-secondary" onclick="previewEmail()">Preview</button>
        <button class="btn btn-primary" onclick="exportTemplate()">Export Template</button>
    </div>
</div>

<div style="display: flex; gap: 24px; align-items: flex-start; margin-bottom: 24px;">
    
    <!-- Sidebar: Components -->
    <div class="card" style="width: 280px; padding: 20px; flex-shrink: 0;">
        <h3 style="margin-top: 0; margin-bottom: 16px; font-size: 1.1rem;">Components</h3>
        
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <button type="button" class="btn btn-secondary" style="justify-content: flex-start; text-align: left;" onclick="addBlock('header')">
                <svg style="width: 16px; height: 16px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path></svg>
                Header text
            </button>
            <button type="button" class="btn btn-secondary" style="justify-content: flex-start; text-align: left;" onclick="addBlock('paragraph')">
                <svg style="width: 16px; height: 16px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
                Paragraph
            </button>
            <button type="button" class="btn btn-secondary" style="justify-content: flex-start; text-align: left;" onclick="addBlock('image')">
                <svg style="width: 16px; height: 16px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Image Banner
            </button>
            <button type="button" class="btn btn-secondary" style="justify-content: flex-start; text-align: left;" onclick="addBlock('button')">
                <svg style="width: 16px; height: 16px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path></svg>
                Call to Action
            </button>
            <button type="button" class="btn btn-secondary" style="justify-content: flex-start; text-align: left;" onclick="addBlock('divider')">
                <svg style="width: 16px; height: 16px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>
                Divider
            </button>
            <button type="button" class="btn btn-secondary" style="justify-content: flex-start; text-align: left;" onclick="addBlock('footer')">
                <svg style="width: 16px; height: 16px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path></svg>
                Footer Info
            </button>
        </div>
    </div>
    
    <!-- Main Canvas -->
    <div style="flex-grow: 1; display: flex; justify-content: center; background: #e2e8f0; padding: 40px; border-radius: 8px; min-height: 600px;">
        <div id="email_canvas" style="width: 600px; background: white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); min-height: 400px; position: relative;">
            <div id="empty_prompt" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #94a3b8; text-align: center;">
                <svg style="width: 48px; height: 48px; margin: 0 auto 12px; display: block; opacity: 0.5;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                Click components to add them here.
            </div>
            
            <div id="blocks_container" style="display: flex; flex-direction: column; width: 100%; z-index: 10; position: relative;"></div>
        </div>
    </div>

</div>

<script>
let bId = 0;

function addBlock(type) {
    document.getElementById('empty_prompt').style.display = 'none';
    const container = document.getElementById('blocks_container');
    
    bId++;
    const block = document.createElement('div');
    block.id = `eblock_${bId}`;
    block.style.cssText = "position: relative; border: 2px solid transparent; transition: all 0.2s;";
    
    // Hover effects via JS for inline style simplicity
    block.onmouseover = () => block.style.borderColor = '#38bdf8';
    block.onmouseout = () => block.style.borderColor = 'transparent';
    
    const delBtn = `<button onclick="document.getElementById('eblock_${bId}').remove()" style="position: absolute; right: 0; top: 0; background: #ef4444; color: white; border: none; padding: 4px 8px; font-size: 12px; cursor: pointer; z-index: 20;">✕</button>`;
    
    let content = '';
    
    if (type === 'header') {
        content = `<div style="padding: 20px; text-align: center; font-family: sans-serif;"><h1 contenteditable="true" style="margin: 0; color: #1e293b;">Your Headline Here</h1></div>`;
    } else if (type === 'paragraph') {
        content = `<div style="padding: 20px; font-family: sans-serif; color: #475569; line-height: 1.6;"><p contenteditable="true" style="margin: 0;">Click here to edit this text. This is a great place to elaborate on your message and connect with your audience.</p></div>`;
    } else if (type === 'image') {
        content = `<div style="padding: 20px; text-align: center;"><div style="background: #cbd5e1; width: 100%; height: 200px; display: flex; align-items: center; justify-content: center; color: #64748b; font-family: sans-serif;">[ Placeholder Image ]</div></div>`;
    } else if (type === 'button') {
        content = `<div style="padding: 20px; text-align: center;"><a href="#" style="display: inline-block; background: #4f46e5; color: white; text-decoration: none; padding: 12px 24px; border-radius: 4px; font-family: sans-serif; font-weight: bold;" contenteditable="true">Call to Action</a></div>`;
    } else if (type === 'divider') {
        content = `<div style="padding: 20px;"><hr style="border: none; border-top: 1px solid #e2e8f0; margin: 0;"></div>`;
    } else if (type === 'footer') {
        content = `<div style="padding: 20px; text-align: center; font-family: sans-serif; font-size: 12px; color: #94a3b8;"><p style="margin: 0;">Company Name, 123 Business Rd, City, Country</p><p style="margin: 5px 0 0 0;"><a href="{unsubscribe_url}" style="color: #94a3b8; text-decoration: underline;">Unsubscribe</a></p></div>`;
    }
    
    block.innerHTML = delBtn + content;
    container.appendChild(block);
}

function previewEmail() {
    alert("Preview mode activated! (Prototype)");
}

function exportTemplate() {
    const container = document.getElementById('blocks_container');
    if (container.children.length === 0) {
        alert("Add some blocks first!");
        return;
    }
    
    // In a real app, we'd clean up the contenteditable and delete buttons before saving.
    fetch('/visual-builder/export', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ html: 'Mock HTML payload' })
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
