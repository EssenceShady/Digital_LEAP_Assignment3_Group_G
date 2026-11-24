'use strict';

(function () {
    var forms = document.querySelectorAll('form[data-attendance-form]');
    if (!forms.length) { return; }

    var status = document.querySelector('[data-attendance-status]');
    var supported = !!navigator.geolocation;
    var pending = false;

    function say(text) {
        if (status) { status.textContent = text; }
    }

    function setBusy(busy) {
        document.querySelectorAll('form[data-attendance-form] button').forEach(function (button) {
            button.disabled = busy;
        });
    }

    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.getAttribute('data-ready') === '1') { return; }
            event.preventDefault();
            if (pending) { return; }

            if (!navigator.onLine) {
                say('You are offline. Reconnect to record attendance.');
                return;
            }

            var proceed = function () {
                pending = false;
                form.setAttribute('data-ready', '1');
                form.submit();
            };

            if (!supported) {
                say('This browser cannot share a location; sending an attested check-in.');
                proceed();
                return;
            }

            pending = true;
            setBusy(true);
            say('Reading your location\u2026');

            navigator.geolocation.getCurrentPosition(function (position) {
                form.querySelector('[name="lat"]').value = position.coords.latitude.toFixed(6);
                form.querySelector('[name="lng"]').value = position.coords.longitude.toFixed(6);
                var accuracy = position.coords.accuracy;
                if (typeof accuracy === 'number' && isFinite(accuracy)) {
                    form.querySelector('[name="accuracy"]').value = String(Math.round(accuracy));
                }
                say('Location captured. Recording\u2026');
                proceed();
            }, function () {
                say('Location was not shared; recording an attested check-in.');
                proceed();
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
        });
    });
})();
