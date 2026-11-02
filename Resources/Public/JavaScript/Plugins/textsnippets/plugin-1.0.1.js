/**
 * CKEditor 4 Plugin: textSnippets (Modal-Version)
 * Mit Debug-Ausgaben zur Fehlersuche
 */
CKEDITOR.plugins.add('textSnippets', {
    init: function (editor) {
        var config = editor.config.textSnippets || {};
        var groupTitle = config.groupTitle || 'Textbausteine';
        var currentEditor = editor;

        var loadedItems = null;
        var modalCreated = false;
        var currentModal = null;

        var ajaxUrl = (typeof TYPO3 !== 'undefined' &&
                       TYPO3.settings &&
                       TYPO3.settings.ajaxUrls &&
                       TYPO3.settings.ajaxUrls['rescue_reports_snippets'])
            ? TYPO3.settings.ajaxUrls['rescue_reports_snippets']
            : null;

        console.log('TextSnippets Plugin - Init. AJAX URL:', ajaxUrl);

        function createModal() {
            if (modalCreated) return;
            modalCreated = true;

            var modalHtml = '<div id="textSnippetsModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">' +
                '  <div class="modal-dialog modal-lg" role="document">' +
                '    <div class="modal-content">' +
                '      <div class="modal-header">' +
                '        <h5 class="modal-title">' + groupTitle + '</h5>' +
                '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>' +
                '      </div>' +
                '      <div class="modal-body" style="padding: 0;">' +
                '        <div class="row" style="margin: 0; height: 500px;">' +
                '          <div class="col-md-6" style="border-right: 1px solid #ddd; overflow-y: auto; padding: 15px;">' +
                '            <h6 style="margin-bottom: 10px;">Verfügbare Textbausteine</h6>' +
                '            <div id="snippetsList"></div>' +
                '          </div>' +
                '          <div class="col-md-6" style="overflow-y: auto; padding: 15px;">' +
                '            <h6 style="margin-bottom: 10px;">Vorschau</h6>' +
                '            <div id="snippetsPreview" style="background: #f8f9fa; padding: 12px; border-radius: 4px; border: 1px solid #ddd;"></div>' +
                '          </div>' +
                '        </div>' +
                '      </div>' +
                '      <div class="modal-footer">' +
                '        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>' +
                '        <button type="button" class="btn btn-primary" id="insertSnippetBtn" disabled>Einfügen</button>' +
                '      </div>' +
                '    </div>' +
                '  </div>' +
                '</div>';

            document.body.insertAdjacentHTML('beforeend', modalHtml);
            console.log('Modal HTML created and added to DOM');
        }

        function openModal() {
            console.log('openModal called. Snippets loaded:', loadedItems ? loadedItems.length : 0);
            createModal();

            var modalEl = document.getElementById('textSnippetsModal');
            if (!modalEl) {
                console.error('ERROR: Modal element not found in DOM!');
                return;
            }

            var snippetsList = document.getElementById('snippetsList');
            var snippetsPreview = document.getElementById('snippetsPreview');
            var insertBtn = document.getElementById('insertSnippetBtn');
            var selectedSnippet = null;

            // Befülle die Liste
            if (!loadedItems || loadedItems.length === 0) {
                snippetsList.innerHTML = '<p class="text-muted"><em>(keine Textbausteine vorhanden)</em></p>';
                console.log('No snippets to display');
            } else {
                snippetsList.innerHTML = '';
                console.log('Adding ' + loadedItems.length + ' snippets to list');

                loadedItems.forEach(function (item, index) {
                    var div = document.createElement('div');
                    div.className = 'snippet-item p-2 mb-2 border rounded';
                    div.style.cursor = 'pointer';
                    div.style.backgroundColor = '#fff';
                    div.style.transition = 'background-color 0.15s ease';
                    div.innerHTML = '<strong>' + escapeHtml(item.title) + '</strong>';
                    if (item.category) {
                        div.innerHTML += '<br><small class="text-muted">' + escapeHtml(item.category) + '</small>';
                    }

                    div.addEventListener('mouseover', function () {
                        if (!div.classList.contains('selected')) {
                            div.style.backgroundColor = '#f0f0f0';
                        }
                    });

                    div.addEventListener('mouseout', function () {
                        if (!div.classList.contains('selected')) {
                            div.style.backgroundColor = '#fff';
                        }
                    });

                    div.addEventListener('click', function () {
                        console.log('Snippet selected:', item.title);

                        // Alte Auswahl entfernen
                        document.querySelectorAll('.snippet-item').forEach(function (el) {
                            el.style.backgroundColor = '#fff';
                            el.classList.remove('selected');
                        });

                        // Neue Auswahl
                        div.style.backgroundColor = '#e7f3ff';
                        div.classList.add('selected');
                        selectedSnippet = item;
                        snippetsPreview.innerHTML = item.html;
                        insertBtn.disabled = false;
                    });

                    snippetsList.appendChild(div);
                });
            }

            // Insert-Button Handler
            insertBtn.onclick = function () {
                if (selectedSnippet) {
                    console.log('Inserting snippet content:', selectedSnippet.title);

                    currentEditor.focus();
                    currentEditor.fire('saveSnapshot');
                    currentEditor.insertHtml(selectedSnippet.html);
                    currentEditor.fire('saveSnapshot');

                    // Modal schließen
                    if (currentModal) {
                        currentModal.hide();
                    } else {
                        modalEl.classList.remove('show');
                        modalEl.style.display = 'none';
                    }
                } else {
                    console.warn('No snippet selected!');
                }
            };

            // Modal öffnen
            try {
                if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
                    currentModal = new window.bootstrap.Modal(modalEl, {
                        backdrop: true,
                        keyboard: true
                    });
                    console.log('Opening Bootstrap 5 Modal');
                    currentModal.show();
                } else {
                    console.log('Bootstrap Modal not available, using fallback');
                    // Backdrop erstellen
                    var backdrop = document.querySelector('.modal-backdrop');
                    if (!backdrop) {
                        backdrop = document.createElement('div');
                        backdrop.className = 'modal-backdrop fade show';
                        document.body.appendChild(backdrop);
                    }
                    // Modal anzeigen
                    modalEl.classList.add('show');
                    modalEl.setAttribute('aria-hidden', 'false');
                    modalEl.style.display = 'block';
                    document.body.classList.add('modal-open');
                }
            } catch (e) {
                console.error('ERROR opening modal:', e);
                // Fallback: Modal einfach anzeigen
                var backdrop = document.querySelector('.modal-backdrop');
                if (!backdrop) {
                    backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    document.body.appendChild(backdrop);
                }
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                document.body.classList.add('modal-open');
            }
        }

        // AJAX laden
        if (ajaxUrl) {
            console.log('Loading snippets from AJAX...');
            fetch(ajaxUrl)
                .then(function (r) {
                    console.log('AJAX Response received, status:', r.status);
                    return r.json();
                })
                .then(function (items) {
                    console.log('Snippets loaded successfully:', items.length, 'items');
                    loadedItems = items;
                })
                .catch(function (err) {
                    console.error('ERROR loading snippets:', err);
                    loadedItems = [];
                });
        } else {
            console.error('ERROR: AJAX URL not configured!');
            loadedItems = [];
        }

        // Button zur Toolbar hinzufügen
        var cmd = editor.addCommand('openTextSnippetsModal', {
            exec: function (editor) {
                console.log('Command executed: openTextSnippetsModal');
                openModal();
            }
        });

        editor.ui.addButton('TextSnippets', {
            label: groupTitle,
            title: groupTitle,
            command: 'openTextSnippetsModal',
            toolbar: 'insert'
        });


        console.log('TextSnippets Plugin initialized successfully');
    }
});

// Hilfsfunktion: HTML escapen
function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
