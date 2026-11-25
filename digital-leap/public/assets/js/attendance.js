'use strict';

(function () {
    var forms = document.querySelectorAll('form[data-attendance-form], form[data-sitecheckin-form]');
    if (!forms.length) { return; }

    var supported = !!navigator.geolocation;
    var pending = false;

    function say(form, text) {
        var local = form.querySelector('[data-checkin-status]');
        if (local) {
            local.textContent = text;
            return;
        }
        var shared = document.querySelector('[data-attendance-status]');
        if (shared) { shared.textContent = text; }
    }

    function setBusy(form, busy) {
        form.querySelectorAll('button').forEach(function (button) {
            button.disabled = busy;
        });
    }

    forms.forEach(function (form) {
        // Site check-ins are proof of presence, so a missing location is a
        // refusal, not a fallback. Student attendance may fall back to attested.
        var strict = form.hasAttribute('data-sitecheckin-form');

        form.addEventListener('submit', function (event) {
            if (form.getAttribute('data-ready') === '1') { return; }
            event.preventDefault();
            if (pending) { return; }

            if (!navigator.onLine) {
                say(form, strict
                    ? 'You are offline. Reconnect to record this visit.'
                    : 'You are offline. Reconnect to record attendance.');
                return;
            }

            var proceed = function () {
                pending = false;
                form.setAttribute('data-ready', '1');
                form.submit();
            };

            if (!supported) {
                if (strict) {
                    say(form, 'This browser cannot share a location, so a site visit cannot be verified. Use a device with location services on.');
                    return;
                }
                say(form, 'This browser cannot share a location; sending an attested check-in.');
                proceed();
                return;
            }

            pending = true;
            setBusy(form, true);
            say(form, 'Reading your location\u2026');

            navigator.geolocation.getCurrentPosition(function (position) {
                form.querySelector('[name="lat"]').value = position.coords.latitude.toFixed(6);
                form.querySelector('[name="lng"]').value = position.coords.longitude.toFixed(6);
                var accuracy = position.coords.accuracy;
                if (typeof accuracy === 'number' && isFinite(accuracy)) {
                    var field = form.querySelector('[name="accuracy"]');
                    if (field) { field.value = String(Math.round(accuracy)); }
                }
                say(form, 'Location captured. Recording\u2026');
                proceed();
            }, function () {
                if (strict) {
                    pending = false;
                    setBusy(form, false);
                    say(form, 'Location was not shared, so the visit was not recorded. Allow location and try again.');
                    return;
                }
                say(form, 'Location was not shared; recording an attested check-in.');
                proceed();
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
        });
    });
})();
