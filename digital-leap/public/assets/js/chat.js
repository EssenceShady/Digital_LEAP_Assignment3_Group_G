/* Placement messaging: reply targeting and 8-second polling. */
(function () {
    'use strict';

    var stream = document.querySelector('[data-chat]');
    if (!stream) {
        return;
    }

    var threadId = stream.getAttribute('data-thread');
    var pollUrl = stream.getAttribute('data-poll');
    var lastId = parseInt(stream.getAttribute('data-last') || '0', 10);

    var form = document.querySelector('[data-chat-form]');
    var input = document.querySelector('[data-chat-input]');
    var target = document.querySelector('[data-reply-target]');
    var bar = document.querySelector('[data-reply-bar]');
    var nameOut = document.querySelector('[data-reply-name]');
    var msgOut = document.querySelector('[data-reply-msg]');

    function endOfThread() {
        // Desktop scrolls the transcript. A phone scrolls the page and pins the
        // composer over its lower edge, so the newest message must come to rest
        // above that edge and not behind it.
        if (stream.scrollHeight > stream.clientHeight + 4) {
            stream.scrollTop = stream.scrollHeight;
            return;
        }
        var last = stream.lastElementChild;
        var doc = document.documentElement;
        if (!last || doc.scrollHeight <= window.innerHeight + 4) {
            return;
        }
        var covered = form && form.getBoundingClientRect().top > 0
            ? window.innerHeight - form.getBoundingClientRect().top + 16
            : 0;
        var target = window.scrollY + last.getBoundingClientRect().bottom - window.innerHeight + covered;
        window.scrollTo(0, Math.max(0, Math.min(target, doc.scrollHeight - window.innerHeight)));
    }

    window.requestAnimationFrame(endOfThread);

    document.querySelectorAll('[data-reply]').forEach(function (button) {
        button.addEventListener('click', function () {
            target.value = button.getAttribute('data-id');
            nameOut.textContent = button.getAttribute('data-name') || 'this message';
            msgOut.textContent = button.getAttribute('data-msg') ? ' · ' + button.getAttribute('data-msg') : '';
            bar.hidden = false;
            input.focus();
        });
    });

    var cancel = document.querySelector('[data-reply-cancel]');
    if (cancel) {
        cancel.addEventListener('click', function () {
            target.value = '';
            bar.hidden = true;
            input.focus();
        });
    }

    if (form) {
        form.addEventListener('submit', function () {
            var button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.textContent = 'Sending…';
            }
        });
    }

    function poll() {
        if (document.hidden || !pollUrl) {
            return;
        }
        fetch(pollUrl + '&id=' + encodeURIComponent(threadId) + '&after=' + lastId, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            credentials: 'same-origin'
        }).then(function (response) {
            return response.ok ? response.json() : null;
        }).then(function (data) {
            if (!data || !data.messages || data.messages.length === 0) {
                return;
            }
            lastId = parseInt(data.messages[data.messages.length - 1].id, 10) || lastId;
            // The server renders quotes, reactions and attachments; reload rather
            // than rebuild that markup in the browser. Skip while composing.
            if (document.activeElement !== input && !input.value) {
                window.location.reload();
            }
        }).catch(function () {
            // Offline or server restart: the next poll tries again.
        });
    }

    window.setInterval(poll, 8000);
})();
