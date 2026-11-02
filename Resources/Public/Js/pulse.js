/**
 * Einsatz-Puls: relative Zeitangabe zum letzten Alarm (cache-sicher im Browser
 * berechnet) und sanftes Springen + Hervorheben beim Klick auf ein Heatmap-Feld.
 */
(function () {
    'use strict';

    function relative(ts) {
        var now = new Date();
        var then = new Date(ts * 1000);
        var startOf = function (d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime(); };
        var days = Math.round((startOf(now) - startOf(then)) / 86400000);
        if (days < 0) { return null; }
        if (days === 0) {
            var hours = Math.floor((now - then) / 3600000);
            return hours < 1 ? 'vor wenigen Minuten' : (hours === 1 ? 'vor einer Stunde' : 'vor ' + hours + ' Stunden');
        }
        if (days === 1) { return 'gestern'; }
        if (days < 60) { return 'vor ' + days + ' Tagen'; }
        return null;
    }

    function initSince() {
        var nodes = document.querySelectorAll('[data-rr-since]');
        for (var i = 0; i < nodes.length; i++) {
            var text = relative(parseInt(nodes[i].getAttribute('data-rr-since'), 10));
            var target = nodes[i].querySelector('.rr-pulse__since');
            if (text && target) {
                target.textContent = text;
                nodes[i].hidden = false;
            }
        }
    }

    function initJumps() {
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        document.addEventListener('click', function (e) {
            var link = e.target.closest && e.target.closest('.rr-pulse a[href^="#einsatz-"]');
            if (!link) { return; }
            var id = link.getAttribute('href').slice(1);
            var row = document.getElementById(id);
            if (!row) { return; }
            e.preventDefault();
            row.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
            row.classList.remove('rr-flash');
            void row.offsetWidth;
            row.classList.add('rr-flash');
            if (history.replaceState) { history.replaceState(null, '', '#' + id); }
        });
    }

    // Auf schmalen Bildschirmen die Heatmap am aktuellen Rand öffnen
    function initScroll() {
        var boxes = document.querySelectorAll('.rr-pulse__scroll');
        for (var i = 0; i < boxes.length; i++) {
            var box = boxes[i];
            if (box.scrollWidth <= box.clientWidth) { continue; }
            var mark = box.querySelector('.rr-pulse__day--today');
            var hits = box.querySelectorAll('.rr-pulse__day--hit');
            if (!mark && hits.length) { mark = hits[hits.length - 1]; }
            if (mark) {
                box.scrollLeft = Math.max(0, mark.offsetLeft - box.clientWidth + 48);
            }
        }
    }

    function init() { initSince(); initJumps(); initScroll(); }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
