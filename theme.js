/*mode sombre*/
(function () {
    'use strict';

    var KEY = 'sapientia-theme';          
    var LEGACY_KEYS = ['admin-dark-mode', 'user-dark-mode']; 
    var query = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function read() {
        try {
            var value = localStorage.getItem(KEY);
            if (value) return value;
            for (var i = 0; i < LEGACY_KEYS.length; i++) {
                var old = localStorage.getItem(LEGACY_KEYS[i]);
                if (old) {
                    value = (old === 'enabled') ? 'dark' : 'light';
                    localStorage.setItem(KEY, value);
                }
                localStorage.removeItem(LEGACY_KEYS[i]);
            }
            return value;
        } catch (e) { return null; }   
    }

    function save(value) {
        try { localStorage.setItem(KEY, value); } catch (e) {}
    }

    function apply(isDark) {
        document.body.classList.toggle('dark-mode', isDark);
        var button = document.getElementById('dark-toggle');
        if (button) {
            button.setAttribute('aria-pressed', String(isDark));
            button.setAttribute('title', isDark ? 'Passer en mode clair' : 'Passer en mode sombre');
        }
    }

    function currentPreference() {
        var saved = read();
        if (saved) return saved === 'dark';
        return !!(query && query.matches);
    }

    apply(currentPreference());

    var toggle = document.getElementById('dark-toggle');
    if (toggle) {
        toggle.addEventListener('click', function () {
            var next = !document.body.classList.contains('dark-mode');
            save(next ? 'dark' : 'light');
            apply(next);
        });
    }

    // Synchronisation entre onglets/pages ouvertes
    window.addEventListener('storage', function (e) {
        if (e.key === KEY) apply(currentPreference());
    });

    //suivre le système en direct
    if (query && query.addEventListener) {
        query.addEventListener('change', function () {
            if (!read()) apply(currentPreference());
        });
    }
})();
