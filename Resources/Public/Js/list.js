/**
 * Kompakte Einsatzliste: Filter nach Kategorie und "nachts" ohne Neuladen.
 * Ohne JavaScript bleibt die vollständige Liste sichtbar (Filter ist dann ausgeblendet).
 */
(function () {
    'use strict';

    function init(root) {
        var bar = root.querySelector('[data-rr-filter]');
        if (!bar) { return; }
        var items = Array.prototype.slice.call(root.querySelectorAll('[data-rr-item]'));
        var months = Array.prototype.slice.call(root.querySelectorAll('[data-rr-month]'));
        var status = root.querySelector('[data-rr-status]');
        var empty = root.querySelector('[data-rr-empty]');
        var catButtons = Array.prototype.slice.call(bar.querySelectorAll('[data-rr-cat]'));
        var nightButton = bar.querySelector('[data-rr-night]');
        var state = { cat: '', night: false };

        function apply() {
            var shown = 0;
            items.forEach(function (li) {
                var ok = (!state.cat || li.getAttribute('data-rr-cat') === state.cat)
                    && (!state.night || li.getAttribute('data-rr-night') === '1');
                li.hidden = !ok;
                if (ok) { shown++; }
            });
            months.forEach(function (m) {
                var n = m.querySelectorAll('[data-rr-item]:not([hidden])').length;
                m.hidden = n === 0;
                var badge = m.querySelector('[data-rr-month-n]');
                if (badge) { badge.textContent = n + (n === 1 ? ' Einsatz' : ' Einsätze'); }
            });
            catButtons.forEach(function (b) {
                b.setAttribute('aria-pressed', String(b.getAttribute('data-rr-cat') === state.cat));
            });
            if (nightButton) { nightButton.setAttribute('aria-pressed', String(state.night)); }
            var filtered = state.cat !== '' || state.night;
            if (status) {
                status.textContent = filtered ? shown + ' von ' + items.length + ' Einsätzen' : '';
            }
            if (empty) { empty.hidden = shown !== 0; }
        }

        bar.addEventListener('click', function (e) {
            var btn = e.target.closest('button');
            if (!btn || !bar.contains(btn)) { return; }
            if (btn.hasAttribute('data-rr-night')) {
                state.night = !state.night;
            } else {
                var cat = btn.getAttribute('data-rr-cat') || '';
                state.cat = state.cat === cat ? '' : cat;
            }
            apply();
        });

        bar.hidden = false;
    }

    function boot() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-rr-list]'), init);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
