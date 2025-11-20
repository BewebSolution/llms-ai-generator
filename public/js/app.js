document.addEventListener('DOMContentLoaded', function () {
    // Gestione pulsanti AI per generazione descrizioni
    const aiButtons = document.querySelectorAll('.btn-ai-desc');

    aiButtons.forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const rowId = this.getAttribute('data-row-id');
            const row = document.querySelector('tr[data-url-row-id="' + rowId + '"]');
            if (!row) return;

            const titleInput = row.querySelector('.url-title-input');
            const descInput = row.querySelector('.url-desc-input');
            const statusSpan = row.querySelector('.ai-status');

            const title = titleInput.value.trim();
            const url = titleInput.getAttribute('data-url');

            if (!title || !url) {
                alert('Titolo e URL sono necessari per generare la descrizione tramite AI.');
                return;
            }

            // Mostra stato loading
            statusSpan.textContent = '...';
            statusSpan.classList.remove('ai-success', 'ai-error');
            statusSpan.classList.add('ai-loading');

            try {
                // Costruisce l'endpoint corretto
                const pathBase = window.location.pathname.split('/').slice(0, 2).join('/');
                const endpoint = pathBase + '/api/ai/description';

                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ title, url })
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    const message = errorData.error || 'Errore AI';
                    statusSpan.textContent = 'Errore';
                    statusSpan.title = message;
                    statusSpan.classList.remove('ai-loading');
                    statusSpan.classList.add('ai-error');

                    if (response.status === 503) {
                        alert('Servizio AI non disponibile. Verifica la configurazione nel file .env');
                    }
                    return;
                }

                const data = await response.json();
                if (data.description) {
                    descInput.value = data.description;
                    statusSpan.textContent = 'OK';
                    statusSpan.classList.remove('ai-loading');
                    statusSpan.classList.add('ai-success');
                } else {
                    statusSpan.textContent = 'Vuoto';
                    statusSpan.classList.remove('ai-loading');
                    statusSpan.classList.add('ai-error');
                }
            } catch (e) {
                console.error('Errore chiamata AI:', e);
                statusSpan.textContent = 'Errore';
                statusSpan.classList.remove('ai-loading');
                statusSpan.classList.add('ai-error');
            }
        });
    });

    // Auto-resize per textarea
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(function(textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });

    // Conferma per azioni pericolose
    const parseBtns = document.querySelectorAll('form[action*="/parse"] button');
    parseBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if (!confirm('Vuoi parsare questa sitemap? Le URL esistenti verranno aggiornate.')) {
                e.preventDefault();
            }
        });
    });

    // Seleziona/deseleziona tutte le checkbox
    const checkAllBtn = document.createElement('button');
    checkAllBtn.type = 'button';
    checkAllBtn.textContent = 'Seleziona tutti';
    checkAllBtn.style.marginBottom = '10px';

    const urlForm = document.querySelector('form[action*="/urls/bulk-update"]');
    if (urlForm) {
        urlForm.insertBefore(checkAllBtn, urlForm.querySelector('table'));

        let allChecked = false;
        checkAllBtn.addEventListener('click', function() {
            const checkboxes = urlForm.querySelectorAll('input[type="checkbox"][name*="is_selected"]');
            allChecked = !allChecked;
            checkboxes.forEach(cb => cb.checked = allChecked);
            this.textContent = allChecked ? 'Deseleziona tutti' : 'Seleziona tutti';
        });
    }

    // Evidenzia righe con checkbox selezionate
    const checkboxes = document.querySelectorAll('input[type="checkbox"][name*="is_selected"]');
    checkboxes.forEach(function(cb) {
        // Stato iniziale
        if (cb.checked) {
            cb.closest('tr').style.backgroundColor = '#e8f5e9';
        }

        // Al cambio
        cb.addEventListener('change', function() {
            if (this.checked) {
                this.closest('tr').style.backgroundColor = '#e8f5e9';
            } else {
                this.closest('tr').style.backgroundColor = '';
            }
        });
    });
});