/* Digital LEAP boot splash.
 *
 * Loaded synchronously from <head> so the overlay exists from the first paint.
 * It runs once per tab: the marker is written to sessionStorage straight away,
 * and the overlay is display:none unless html has .leap-booting, so ordinary
 * in-app navigations never flash it. With no storage or no JavaScript the
 * splash is simply skipped and the page behaves exactly as before.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var KEY = 'leap.booted';
    var booted = false;

    try { booted = window.sessionStorage.getItem(KEY) === '1'; } catch (error) { booted = true; }
    if (booted) { return; }

    var calm = root.getAttribute('data-reduce-motion') === 'on'
        || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    var MIN = calm ? 450 : 1000;
    var MAX = calm ? 1400 : 2600;
    var started = Date.now();
    var finished = false;
    var leaving = false;

    root.classList.add('leap-booting');
    try { window.sessionStorage.setItem(KEY, '1'); } catch (error) { /* no storage */ }

    function finish() {
        if (finished) { return; }
        finished = true;
        window.setTimeout(leave, Math.max(0, MIN - (Date.now() - started)));
    }

    function leave() {
        if (leaving) { return; }
        leaving = true;
        var overlay = document.querySelector('[data-leap-boot]');
        root.classList.remove('leap-booting');
        if (!overlay) { return; }
        overlay.classList.add('is-leaving');
        window.setTimeout(function () {
            overlay.hidden = true;
        }, calm ? 0 : 460);
    }

    if (document.readyState === 'complete') {
        finish();
    } else {
        window.addEventListener('load', finish, { once: true });
        window.setTimeout(finish, MAX);
    }
})();
