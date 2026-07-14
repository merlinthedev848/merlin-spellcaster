<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>Behavioral Workflow Engine</h1>
        <p>Build advanced logic funnels. Trigger actions based on subscriber behavior across all channels.</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="saveWorkflow()">Save & Activate Workflow</button>
    </div>
</div>

<div style="display: flex; gap: 24px; align-items: flex-start; margin-bottom: 24px;">
    
    <!-- Sidebar: Nodes -->
    <div class="card" style="width: 280px; padding: 20px; flex-shrink: 0;">
        <h3 style="margin-top: 0; margin-bottom: 16px; font-size: 1.1rem;">Available Nodes</h3>
        
        <h4 style="margin: 0 0 8px 0; font-size: 0.85rem; text-transform: uppercase; color: var(--text-muted);">Triggers</h4>
        <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px;">
            <button class="btn btn-secondary" onclick="addNode('trigger', 'Subscribed to List', '#3b82f6')">Subscribed to List</button>
            <button class="btn btn-secondary" onclick="addNode('trigger', 'Tag Added', '#3b82f6')">Tag Added</button>
            <button class="btn btn-secondary" onclick="addNode('trigger', 'Link Clicked', '#3b82f6')">Link Clicked</button>
        </div>

        <h4 style="margin: 0 0 8px 0; font-size: 0.85rem; text-transform: uppercase; color: var(--text-muted);">Conditions</h4>
        <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px;">
            <button class="btn btn-secondary" onclick="addNode('condition', 'If / Else Split', '#f59e0b')">If / Else Split</button>
            <button class="btn btn-secondary" onclick="addNode('condition', 'Time Delay', '#f59e0b')">Wait Time</button>
        </div>

        <h4 style="margin: 0 0 8px 0; font-size: 0.85rem; text-transform: uppercase; color: var(--text-muted);">Actions</h4>
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <button class="btn btn-secondary" onclick="addNode('action', 'Send Email', '#10b981')">Send Email</button>
            <button class="btn btn-secondary" onclick="addNode('action', 'Send SMS (Twilio)', '#10b981')">Send SMS</button>
            <button class="btn btn-secondary" onclick="addNode('action', 'Add Tag', '#10b981')">Add Tag</button>
            <button class="btn btn-secondary" onclick="addNode('action', 'Notify Slack', '#10b981')">Notify Slack</button>
        </div>
    </div>
    
    <!-- Main Canvas -->
    <div style="flex-grow: 1; background: #e2e8f0; padding: 40px; border-radius: 8px; min-height: 600px; overflow-x: auto; position: relative;">
        
        <div id="canvas_empty" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #94a3b8; text-align: center;">
            <p>Click a node on the left to start building your flow.</p>
        </div>

        <div id="workflow_canvas" style="display: flex; flex-direction: column; align-items: center; gap: 24px;">
            <!-- Nodes will be appended here -->
        </div>
    </div>

</div>

<script>
let nodeCount = 0;

function addNode(type, title, color) {
    document.getElementById('canvas_empty').style.display = 'none';
    const canvas = document.getElementById('workflow_canvas');
    nodeCount++;
    
    // Create arrow if not first node
    if (nodeCount > 1) {
        const arrow = document.createElement('div');
        arrow.style.cssText = "height: 24px; width: 2px; background: #94a3b8; position: relative;";
        
        const arrowHead = document.createElement('div');
        arrowHead.style.cssText = "position: absolute; bottom: -5px; left: -4px; width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-top: 5px solid #94a3b8;";
        arrow.appendChild(arrowHead);
        
        canvas.appendChild(arrow);
    }
    
    const node = document.createElement('div');
    node.className = 'workflow-node';
    node.id = `node_${nodeCount}`;
    node.style.cssText = `background: white; border: 2px solid ${color}; border-radius: 8px; padding: 16px 24px; min-width: 220px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); position: relative; text-align: center;`;
    
    let subtext = "";
    if (type === 'trigger') subtext = "Start when...";
    if (type === 'condition') subtext = "Evaluate...";
    if (type === 'action') subtext = "Execute...";

    node.innerHTML = `
        <button onclick="removeNode(${nodeCount})" style="position: absolute; right: -10px; top: -10px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center;">✕</button>
        <div style="font-size: 0.75rem; color: ${color}; text-transform: uppercase; font-weight: bold; margin-bottom: 4px;">${subtext}</div>
        <div style="font-weight: 600; color: #1e293b;">${title}</div>
        <div style="margin-top: 12px;">
            <input type="text" placeholder="Setup details..." style="width: 100%; border: 1px solid #e2e8f0; border-radius: 4px; padding: 4px 8px; font-size: 0.85rem; outline: none;">
        </div>
    `;
    
    canvas.appendChild(node);
}

function removeNode(id) {
    // Prototyping shortcut: we will just clear the whole canvas for simplicity if they mess up
    if (confirm("Reset the canvas? (Prototype simplified deletion)")) {
        document.getElementById('workflow_canvas').innerHTML = '';
        document.getElementById('canvas_empty').style.display = 'block';
        nodeCount = 0;
    }
}

function saveWorkflow() {
    if (nodeCount === 0) {
        alert("Please add some nodes first!");
        return;
    }
    
    fetch('/automations/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nodes: nodeCount })
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
