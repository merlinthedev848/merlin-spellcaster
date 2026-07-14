<?php
declare(strict_types=1);
?>

<div class="header-actions">
    <div class="page-title">
        <h1>B2B Data Enrichment Engine</h1>
        <p>Enrich corporate email addresses with company information like sizes, locations, and industries automatically.</p>
    </div>
</div>

<div class="card" style="padding: 24px;">
    <div class="card-header" style="margin-bottom: 16px; padding-bottom: 0; border-bottom: none;">
        <span class="card-title">Corporate Leads Available for Enrichment</span>
    </div>
    
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Email Address</th>
                    <th>Name</th>
                    <th>Company Name</th>
                    <th>Company Size</th>
                    <th>Industry</th>
                    <th style="width: 140px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($contacts)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 40px;">No non-generic corporate domains found in contacts. Add some first!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($contacts as $c): ?>
                        <tr id="row_<?= $c['id'] ?>">
                            <td style="font-weight: 600;"><?= e($c['email']) ?></td>
                            <td><?= e($c['first_name'] . ' ' . $c['last_name']) ?: '—' ?></td>
                            <td class="col-company"><?= e($c['company']) ?></td>
                            <td class="col-size"><?= e($c['size']) ?></td>
                            <td class="col-industry"><?= e($c['industry']) ?></td>
                            <td>
                                <?php if ($c['enriched']): ?>
                                    <span class="badge" style="background: rgba(16,185,129,0.1); color: #10b981;">Enriched ✓</span>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-enrich" style="padding: 4px 8px; font-size: 11px;" onclick="enrichContact(<?= $c['id'] ?>)">Enrich Profile</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function enrichContact(id) {
    const row = document.getElementById('row_' + id);
    const btn = row.querySelector('.btn-enrich');
    btn.disabled = true;
    btn.innerText = 'Searching...';
    
    fetch('/enrichment/run', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ subscriber_id: id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            row.querySelector('.col-company').innerText = data.company;
            row.querySelector('.col-size').innerText = data.size;
            row.querySelector('.col-industry').innerText = data.industry;
            
            const cell = btn.parentNode;
            cell.innerHTML = '<span class="badge" style="background: rgba(16,185,129,0.1); color: #10b981;">Enriched ✓</span>';
        } else {
            alert("Error: " + data.error);
            btn.disabled = false;
            btn.innerText = 'Enrich Profile';
        }
    })
    .catch(err => {
        alert("Network error.");
        btn.disabled = false;
        btn.innerText = 'Enrich Profile';
    });
}
</script>
