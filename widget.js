(function() {
    'use strict';
    const script = document.currentScript || Array.from(document.querySelectorAll('script')).pop();
    const formId = script.getAttribute('data-form-id') || 0;
    const mode = script.getAttribute('data-mode') || 'inline'; // inline, popup, exit_intent
    const appUrl = script.src.replace(/\/widget\.js.*$/, '');

    function initWidget() {
        if (mode === 'popup' || mode === 'exit_intent') {
            createPopupModal();
            if (mode === 'exit_intent') {
                let triggered = false;
                document.addEventListener('mouseleave', function(e) {
                    if (e.clientY <= 0 && !triggered) {
                        triggered = true;
                        openModal();
                    }
                });
            } else {
                setTimeout(openModal, 3000);
            }
        } else {
            renderInlineForm();
        }
    }

    function renderInlineForm() {
        const target = document.getElementById('merlin_widget_' + formId) || script.parentNode;
        const container = document.createElement('div');
        container.className = 'merlin-widget-container';
        container.style.cssText = 'background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; font-family: system-ui, -apple-system, sans-serif; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); max-width: 440px; margin: 16px auto;';
        container.innerHTML = getFormHtml();
        target.appendChild(container);
        bindSubmit(container);
    }

    function createPopupModal() {
        const backdrop = document.createElement('div');
        backdrop.id = 'merlin_modal_backdrop_' + formId;
        backdrop.style.cssText = 'display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease;';

        const modal = document.createElement('div');
        modal.style.cssText = 'background: #ffffff; border-radius: 16px; padding: 32px; max-width: 480px; width: 90%; position: relative; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); font-family: system-ui, -apple-system, sans-serif;';
        modal.innerHTML = '<button type="button" id="merlin_modal_close_' + formId + '" style="position: absolute; top: 16px; right: 16px; background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>' + getFormHtml();

        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);

        document.getElementById('merlin_modal_close_' + formId).onclick = closeModal;
        backdrop.onclick = function(e) { if (e.target === backdrop) closeModal(); };
        bindSubmit(modal);
    }

    function openModal() {
        const backdrop = document.getElementById('merlin_modal_backdrop_' + formId);
        if (backdrop) {
            backdrop.style.display = 'flex';
            setTimeout(() => backdrop.style.opacity = '1', 10);
        }
    }

    function closeModal() {
        const backdrop = document.getElementById('merlin_modal_backdrop_' + formId);
        if (backdrop) {
            backdrop.style.opacity = '0';
            setTimeout(() => backdrop.style.display = 'none', 300);
        }
    }

    function getFormHtml() {
        return `
            <div style="text-align: center; margin-bottom: 20px;">
                <h3 style="margin: 0 0 8px 0; font-size: 20px; font-weight: 700; color: #0f172a;">Join Our VIP Community</h3>
                <p style="margin: 0; font-size: 13px; color: #64748b;">Subscribe to receive exclusive insights, alerts, and updates.</p>
            </div>
            <form class="merlin-widget-form" style="display: flex; flex-direction: column; gap: 12px;">
                <input type="text" name="first_name" placeholder="First Name" style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                <input type="email" name="email" placeholder="Email Address" required style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                <button type="submit" style="width: 100%; padding: 12px; background: #635bff; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: background 0.2s ease;">Subscribe Now →</button>
                <div class="merlin-msg" style="font-size: 12px; text-align: center; margin-top: 4px;"></div>
            </form>
        `;
    }

    function bindSubmit(parentEl) {
        const form = parentEl.querySelector('.merlin-widget-form');
        const msgEl = parentEl.querySelector('.merlin-msg');

        form.onsubmit = async function(e) {
            e.preventDefault();
            const btn = form.querySelector('button');
            btn.disabled = true;
            btn.textContent = 'Submitting...';

            const payload = {
                form_id: formId,
                first_name: form.first_name ? form.first_name.value : '',
                email: form.email.value
            };

            try {
                const resp = await fetch(appUrl + '/api/form-submit', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await resp.json();

                if (data.success) {
                    msgEl.style.color = '#10b981';
                    msgEl.textContent = data.message || 'Subscribed successfully!';
                    form.reset();
                    if (mode === 'popup' || mode === 'exit_intent') {
                        setTimeout(closeModal, 2000);
                    }
                } else {
                    msgEl.style.color = '#ef4444';
                    msgEl.textContent = data.error || 'Submission failed.';
                }
            } catch (err) {
                msgEl.style.color = '#ef4444';
                msgEl.textContent = 'Network error. Please try again.';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Subscribe Now →';
            }
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWidget);
    } else {
        initWidget();
    }
})();
