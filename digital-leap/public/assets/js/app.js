'use strict';

(function () {
    var EYE_OPEN = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3.2"/></svg>';
    var EYE_OFF = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5c2 0 3.7.7 5.2 1.7"/><path d="M21.5 12S18 18.5 12 18.5c-2 0-3.7-.7-5.2-1.7"/><path d="M4 4l16 16"/></svg>';

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) { window.location.reload(); }
    });

    function toast(message) {
        var stack = document.querySelector('[data-toasts]');
        if (!stack || !message) { return; }
        var node = document.createElement('div');
        node.className = 'toast';
        node.textContent = message;
        stack.appendChild(node);
        window.setTimeout(function () {
            if (node.parentNode) { node.parentNode.removeChild(node); }
        }, 4200);
    }

    function initSidebar() {
        var toggle = document.querySelector('[data-nav-toggle]');
        var sidebar = document.getElementById('sidebar');
        if (!toggle || !sidebar) { return; }
        var scrim = document.querySelector('[data-nav-close]');

        function syncInert() {
            var mobile = window.matchMedia('(max-width: 900px)').matches;
            var closed = mobile && !sidebar.classList.contains('open');
            sidebar.inert = closed;
            sidebar.setAttribute('aria-hidden', closed ? 'true' : 'false');
        }

        function setOpen(open) {
            sidebar.classList.toggle('open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('nav-open', open);
            if (scrim) { scrim.hidden = !open; }
            syncInert();
        }
        syncInert();
        toggle.addEventListener('click', function () {
            setOpen(!sidebar.classList.contains('open'));
        });
        if (scrim) { scrim.addEventListener('click', function () { setOpen(false); }); }
        sidebar.addEventListener('click', function (event) {
            if (window.matchMedia('(max-width: 900px)').matches && event.target.closest('a')) {
                setOpen(false);
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('open')) { setOpen(false); }
        });
        window.addEventListener('resize', function () {
            if (!window.matchMedia('(max-width: 900px)').matches) { setOpen(false); }
            syncInert();
        });
    }

    function initDropdown(toggleSelector, panelSelector) {
        var toggle = document.querySelector(toggleSelector);
        var panel = document.querySelector(panelSelector);
        if (!toggle || !panel) { return; }

        function setOpen(open) {
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            var open = panel.hidden;
            document.querySelectorAll('.dropdown').forEach(function (other) {
                if (other === panel) { return; }
                other.hidden = true;
                var otherToggle = document.querySelector('[aria-controls="' + other.id + '"]');
                if (otherToggle) { otherToggle.setAttribute('aria-expanded', 'false'); }
            });
            setOpen(open);
        });
        panel.addEventListener('click', function (event) { event.stopPropagation(); });
        document.addEventListener('click', function () { setOpen(false); });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !panel.hidden) {
                setOpen(false);
                toggle.focus();
            }
        });
    }

    function initPasswords() {
        document.querySelectorAll('input[type="password"]').forEach(function (input) {
            if (input.closest('.password-wrap')) { return; }
            var wrap = document.createElement('span');
            wrap.className = 'password-wrap';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'password-toggle';
            button.setAttribute('aria-label', 'Show password');
            button.setAttribute('aria-pressed', 'false');
            button.innerHTML = EYE_OPEN;
            wrap.appendChild(button);

            button.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.innerHTML = show ? EYE_OFF : EYE_OPEN;
                button.setAttribute('aria-pressed', show ? 'true' : 'false');
                button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                input.focus({ preventScroll: true });
            });
        });
    }

    var PASSWORD_RULE = 'Use 8 or more characters with an uppercase letter, a lowercase letter and a number.';

    function passwordScore(value) {
        var score = 0;
        if (value.length >= 8) { score++; }
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) { score++; }
        if (/\d/.test(value)) { score++; }
        if (/[^A-Za-z0-9]/.test(value) && value.length >= 10) { score++; }
        return score;
    }

    function passwordFeedback(value) {
        var missing = [];
        if (value.length < 8) { missing.push('8 or more characters'); }
        if (!/[A-Z]/.test(value)) { missing.push('an uppercase letter'); }
        if (!/[a-z]/.test(value)) { missing.push('a lowercase letter'); }
        if (!/\d/.test(value)) { missing.push('a number'); }
        if (missing.length > 0) { return 'Needs ' + missing.join(', ') + '.'; }
        return /[^A-Za-z0-9]/.test(value)
            ? 'Meets the policy. The symbol makes it stronger.'
            : 'Meets the policy. Symbols are optional but make it stronger.';
    }

    function initPasswordMeters() {
        document.querySelectorAll('input[autocomplete="new-password"]').forEach(function (input) {
            if (/confirm/i.test(input.name) || input.type !== 'password') { return; }
            var meter = document.createElement('div');
            meter.className = 'pw-meter';
            meter.setAttribute('data-score', '0');
            meter.innerHTML = '<div class="pw-meter-track" aria-hidden="true"><span></span></div>' +
                '<span class="pw-meter-label">' + PASSWORD_RULE + '</span>';
            (input.closest('.field') || input.parentNode).appendChild(meter);

            var labels = ['Too short', 'Weak', 'Fair', 'Strong', 'Very strong'];

            function update() {
                var value = input.value || '';
                var score = value.length === 0 ? 0 : passwordScore(value);
                meter.setAttribute('data-score', String(score));
                meter.querySelector('.pw-meter-track span').style.width = (score * 25) + '%';
                meter.querySelector('.pw-meter-label').textContent = value.length === 0
                    ? PASSWORD_RULE
                    : labels[score] + ' — ' + passwordFeedback(value);
            }
            input.addEventListener('input', update);
            update();
        });
    }

    function initDemoRoles() {
        var email = document.getElementById('email');
        var password = document.getElementById('password');
        if (!email || !password) { return; }

        document.querySelectorAll('[data-demo-email]').forEach(function (button) {
            button.addEventListener('click', function () {
                email.value = button.getAttribute('data-demo-email');
                password.value = button.getAttribute('data-demo-password') || '';
                document.querySelectorAll('.demo-role.is-selected').forEach(function (node) {
                    node.classList.remove('is-selected');
                    node.removeAttribute('aria-pressed');
                });
                button.classList.add('is-selected');
                button.setAttribute('aria-pressed', 'true');
                password.focus();
                toast('Demo ' + (button.getAttribute('data-demo-label') || '') + ' credentials loaded. Press Sign in.');
            });
        });
    }

    function initClickRows() {
        document.querySelectorAll('[data-href]').forEach(function (node) {
            if (!node.hasAttribute('tabindex') && !node.closest('a, button')) {
                node.setAttribute('tabindex', '0');
                node.setAttribute('role', 'link');
            }
            function go() {
                var href = node.getAttribute('data-href');
                if (href) { window.location.href = href; }
            }
            node.addEventListener('click', function (event) {
                if (event.target.closest('a, button, input, select, textarea, label')) { return; }
                go();
            });
            node.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    go();
                }
            });
        });
    }

    function initAlerts() {
        document.querySelectorAll('[data-alert-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                var alert = button.closest('.alert');
                if (alert) { alert.parentNode.removeChild(alert); }
            });
        });

        document.querySelectorAll('[data-dismiss]').forEach(function (node) {
            window.setTimeout(function () { node.style.display = 'none'; }, 7000);
        });

        var strip = document.querySelector('[data-demo-strip]');
        if (!strip) { return; }
        var key = 'leap.demo.strip.hidden';
        try {
            if (window.localStorage.getItem(key) === '1') { strip.hidden = true; }
        } catch (error) { /* storage may be blocked offline */ }
        var close = strip.querySelector('[data-demo-dismiss]');
        if (close) {
            close.addEventListener('click', function () {
                strip.hidden = true;
                try { window.localStorage.setItem(key, '1'); } catch (error) { /* ignore */ }
            });
        }
    }

    function initTour() {
        var role = (document.body.getAttribute('data-role') || '').trim();
        var route = (document.body.getAttribute('data-page') || '').trim();

        var welcome = {
            student: 'You are signed in as a student. This workspace takes you from the first CV upload to a released mark — apply, log each week, write the report, then read the result.',
            supervisor: 'You are signed in as a host supervisor. Everything here is about the students placed with your organisation: their weeks, their evaluation, their progress.',
            mentor: 'You are signed in as an academic mentor. Your students, their logbooks, site visits, the report mark and the final grade live here.',
            coordinator: 'You are signed in as the internship office. Applications, allocations, host organisations, visits and grade publication are all on this account.',
            management: 'You are signed in as management. This account reads the cohort: completion, organisations, grades and audit evidence.',
            administrator: 'You are signed in as the system administrator. Accounts, roles, permissions, settings and the audit trail are managed here.'
        };
        var navCopy = {
            student: 'Your sidebar is the whole internship in order: Dashboard, Apply for Internship, My Placement, Logbook, Internship Report, Career Fair, Results and DIVA.',
            supervisor: 'Your sidebar is short on purpose: the review queue, the evaluation form and the students placed with you.',
            mentor: 'Your sidebar holds the students assigned to you, their logbooks, your site visits, the evaluation view and the reports you mark.',
            coordinator: 'Your sidebar is the office workflow: Applications, Placements, Host organisations, Visits and the Grade sheet.',
            management: 'Your sidebar links to the indicators, the grade summary and the exports you need for reporting.',
            administrator: 'Your sidebar covers Users, Roles, Settings, Audit log, Feedback and the Mail log.'
        };
        var kpiCopy = {
            student: 'Each card is a shortcut to the record behind it. The first card shows your placement state; the next entry tile opens the week that is due.',
            supervisor: 'These cards count what is waiting on you. Open a card to reach the queue behind the number.',
            mentor: 'The cards summarise your students and the reviews waiting for you. Click a card to open the list.',
            coordinator: 'This is the office to-do list: applications to review, placements to approve, supervisors to assign, logbooks overdue and grades waiting for release.',
            management: 'Cohort indicators. Each card links to the detail behind the number.',
            administrator: 'System health at a glance: accounts, permissions and recent activity.'
        };

        var pageSteps = {
            'student/apply': [
                { target: '[data-tour="apply-cv"]', title: 'Your CV', body: 'Upload once and reviewers see the latest file. Open the preview to check what they will read, and replace it here if it changes.' },
                { target: '[data-tour="apply-ranked"]', title: 'Rank up to five hosts', body: 'Add hosts with a short reason for each. Use the arrows to set the order, then submit the list. The office sees every choice, not just the first.' },
                { target: '[data-tour="apply-add"]', title: 'Add another host', body: 'Only active hosts with a signed or pending MoU appear here. Explain why the placement fits you — the coordinator reads this before allocating.' }
            ]
        };

        var pageCopy = {
            'student/placement': { title: 'Your placement record', body: 'Host, dates, mentor and supervisor, plus the placement letter and progress. If anything is wrong, message the internship office from here.' },
            'student/logbook': { title: 'One entry per week', body: 'Submit a week, then read the supervisor comment. A returned week shows what to fix; an approved week counts towards your assessment.' },
            'student/logbook/new': { title: 'Write the week down', body: 'Dates, hours, what you did and what you learned. It is the evidence behind your practical mark, so keep it factual.' },
            'student/report': { title: 'The internship report', body: 'Upload the report your mentor will mark. You can replace the file until the mentor starts the review.' },
            'student/career-fair': { title: 'Career fair and visits', body: 'Career fair participation and site visits stay attached to your placement record so the office can see the full picture.' },
            'student/results': { title: 'Results appear when released', body: 'Your mark and feedback stay hidden until the internship office publishes them. Nothing is visible early, by design.' },
            'diva': { title: 'DIVA, your in-system assistant', body: 'Ask about the guidebook, your placement or the next action. DIVA answers inside your permissions and cites the page it used.' },
            'messages': { title: 'Conversations by placement', body: 'Each thread is tied to a placement, so the student, mentor and supervisor discuss the same record. Unread counts follow you to the sidebar.' },
            'supervisor/queue': { title: 'The review queue', body: 'Every submitted week waits here. Open one, read it, then approve or return it with a single clear comment.' },
            'supervisor/evaluation': { title: 'Form D evaluation', body: 'Score each criterion; the weighted total is calculated for you. Submit once and the record is locked for the office.' },
            'mentor/students': { title: 'Your students', body: 'Each card opens the student record: progress, logbook status, visits and the report waiting for your mark.' },
            'mentor/logbook': { title: 'Read the weeks', body: 'You see the same entries as the supervisor, including returned weeks and comments, so a visit conversation starts from evidence.' },
            'mentor/visits': { title: 'Site visits', body: 'Record the date, who you met and what you observed. The visit is part of the placement evidence.' },
            'mentor/reports': { title: 'Reports to mark', body: 'Open the uploaded report, record the mark and a short comment; the office sees it immediately.' },
            'coordinator/applications': { title: 'The applications queue', body: 'Open a student to read the CV and every ranked host with its MoU status and capacity. You allocate; the ranking is advice.' },
            'coordinator/placements': { title: 'The placement register', body: 'Every placement for the year with its status, mentor, supervisor and the letter you can print.' },
            'coordinator/organisations': { title: 'Host organisations', body: 'MoU status, expiry and capacity by year. A host at capacity or with an expired MoU cannot receive another student.' },
            'coordinator/grades': { title: 'The grade sheet', body: 'Compute from evidence, moderate with a recorded reason, then publish. Students only see the result after publication.' },
            'management/kpis': { title: 'Cohort indicators', body: 'Completion, placement spread, publication and audit figures with an export for the report.' },
            'admin/users': { title: 'Accounts', body: 'Create, invite, suspend and reset users. Every change is written to the audit trail with your name.' },
            'admin/roles': { title: 'Roles and permissions', body: 'Each role holds a fixed set of permissions. Change the set here and the sidebar, pages and DIVA answers follow.' },
            'admin/settings': { title: 'Settings', body: 'Weights, academic year and system options are read by every calculation, so change them deliberately.' },
            'admin/audit': { title: 'Audit log', body: 'Append-only. Every allocation, review, return and publication is recorded with the actor and time.' }
        };

        var steps = [{
            target: '[data-tour="brand"]',
            title: 'Welcome to Digital LEAP',
            body: welcome[role] || 'This is the internship workspace for Limkokwing University, Sierra Leone. The logo always returns you to your dashboard.'
        }];

        if (pageSteps[route]) {
            steps = steps.concat(pageSteps[route]);
        } else if (pageCopy[route]) {
            steps.push({
                target: document.querySelector('main .card') ? 'main .card' : '.page-head',
                title: pageCopy[route].title,
                body: pageCopy[route].body
            });
        }

        if (route === 'dashboard') {
            steps.push({
                target: '[data-tour="tasks"], [data-tour="kpis"]',
                title: 'Start with what is due',
                body: kpiCopy[role] || 'Every card is a shortcut to the record behind the number.'
            });
        }

        steps.push({
            target: '[data-tour="nav"]',
            title: 'Your role, your tools',
            body: navCopy[role] || 'The sidebar only shows what your role is allowed to use.'
        });
        steps.push({
            target: '[data-tour="notifications"]',
            title: 'Never miss a hand-off',
            body: 'The bell collects approvals, comments, due-date reminders and released results. Read the latest five without leaving the page.'
        });
        steps.push({
            target: '[data-tour="profile"]',
            title: 'Make it yours',
            body: 'Click your photo to edit your details, change your password and upload a profile picture. Everything stays inside your permission set.'
        });

        var triggers = document.querySelectorAll('[data-tour-start]');
        var prompt = document.querySelector('[data-tour-prompt]');
        if (!triggers.length && !prompt) { return; }

        var layer = null;
        var spot = null;
        var bubble = null;
        var index = 0;
        var lastFocus = null;

        function targets() {
            var list = [];
            steps.forEach(function (step) {
                var node = document.querySelector(step.target);
                if (!node) { return; }
                var rect = node.getBoundingClientRect();
                var laidOut = rect.width > 1 && rect.height > 1 &&
                    rect.right > 8 && rect.left < window.innerWidth - 8;
                if (laidOut) { list.push({ step: step, node: node }); }
            });
            return list;
        }

        function build() {
            layer = document.createElement('div');
            layer.className = 'tour-layer';
            spot = document.createElement('div');
            spot.className = 'tour-spot';
            bubble = document.createElement('div');
            bubble.className = 'tour-bubble';
            bubble.setAttribute('role', 'dialog');
            bubble.setAttribute('aria-modal', 'true');
            bubble.setAttribute('aria-label', 'Guided tour');
            layer.appendChild(spot);
            layer.appendChild(bubble);
            document.body.appendChild(layer);
            document.body.classList.add('tour-open');
        }

        function position(ensure) {
            var list = targets();
            if (!list.length) { finish(); return; }
            if (index >= list.length) { index = list.length - 1; }
            var item = list[index];
            var pad = 8;
            var rect = item.node.getBoundingClientRect();
            if (ensure && (rect.top < 12 || rect.bottom > window.innerHeight - 12)) {
                var root = document.documentElement;
                var previous = root.style.scrollBehavior;
                root.style.scrollBehavior = 'auto';
                var room = Math.max(12, (window.innerHeight - Math.min(rect.height, window.innerHeight - 24)) / 2);
                window.scrollTo(0, Math.max(0, window.scrollY + rect.top - room));
                root.style.scrollBehavior = previous;
                rect = item.node.getBoundingClientRect();
            }
            var top = Math.max(6, rect.top - pad);
            var left = Math.max(6, rect.left - pad);
            var width = Math.max(24, Math.min(window.innerWidth - 12, rect.width + pad * 2));
            var height = Math.max(24, Math.min(window.innerHeight - 12, rect.height + pad * 2));

            spot.style.top = top + 'px';
            spot.style.left = left + 'px';
            spot.style.width = width + 'px';
            spot.style.height = height + 'px';

            bubble.innerHTML = '';
            var progress = document.createElement('span');
            progress.className = 'tour-step';
            progress.textContent = 'Step ' + (index + 1) + ' of ' + list.length;
            var heading = document.createElement('h3');
            heading.textContent = item.step.title;
            var copy = document.createElement('p');
            copy.textContent = item.step.body;

            var actions = document.createElement('div');
            actions.className = 'tour-actions';
            var skip = document.createElement('button');
            skip.type = 'button';
            skip.className = 'link-quiet';
            skip.textContent = 'Skip tour';
            skip.addEventListener('click', finish);
            var right = document.createElement('div');
            right.className = 'tour-actions-right';

            if (index > 0) {
                var back = document.createElement('button');
                back.type = 'button';
                back.className = 'btn btn-quiet btn-sm';
                back.textContent = 'Back';
                back.addEventListener('click', function () { index--; position(true); });
                right.appendChild(back);
            }
            var next = document.createElement('button');
            next.type = 'button';
            next.className = 'btn btn-sm';
            next.textContent = index === list.length - 1 ? 'Finish' : 'Next';
            next.addEventListener('click', function () {
                if (index === list.length - 1) { finish(); return; }
                index++;
                position(true);
            });
            right.appendChild(next);
            actions.appendChild(skip);
            actions.appendChild(right);

            bubble.appendChild(progress);
            bubble.appendChild(heading);
            bubble.appendChild(copy);
            bubble.appendChild(actions);

            var bubbleTop = top + height + 12;
            if (bubbleTop + 260 > window.innerHeight) { bubbleTop = Math.max(14, top - 260); }
            var bubbleLeft = Math.min(Math.max(14, left), window.innerWidth - Math.min(360, window.innerWidth - 28) - 14);
            bubble.style.top = bubbleTop + 'px';
            bubble.style.left = bubbleLeft + 'px';
            next.focus({ preventScroll: true });
        }

        function start() {
            if (!targets().length) { toast('This page has nothing to point at yet. Open a section and try again.'); return; }
            lastFocus = document.activeElement;
            index = 0;
            if (!layer) { build(); }
            position(true);
        }

        function finish() {
            document.body.classList.remove('tour-open');
            if (layer && layer.parentNode) { layer.parentNode.removeChild(layer); }
            layer = null;
            spot = null;
            bubble = null;
            if (lastFocus && lastFocus.focus) { lastFocus.focus({ preventScroll: true }); }
        }

        triggers.forEach(function (button) { button.addEventListener('click', start); });

        if (prompt) {
            var promptStart = prompt.querySelector('[data-tour-prompt-start]');
            var promptSkip = prompt.querySelector('[data-tour-prompt-skip]');
            var closePrompt = function () {
                if (prompt && prompt.parentNode) { prompt.parentNode.removeChild(prompt); }
                prompt = null;
            };
            if (promptStart) {
                promptStart.focus({ preventScroll: true });
                promptStart.addEventListener('click', function () {
                    closePrompt();
                    start();
                });
            }
            if (promptSkip) {
                promptSkip.addEventListener('click', closePrompt);
            }
            prompt.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { closePrompt(); }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (!layer) { return; }
            var list = targets();
            if (event.key === 'Escape') { finish(); }
            if (event.key === 'ArrowRight') { index = Math.min(list.length - 1, index + 1); position(true); }
            if (event.key === 'ArrowLeft') { index = Math.max(0, index - 1); position(true); }
        });

        var reposition = function () { if (layer) { position(false); } };
        window.addEventListener('resize', reposition);
        window.addEventListener('scroll', reposition, { passive: true });
    }

    function initConfirmations() {
        document.querySelectorAll('form[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.confirm(form.getAttribute('data-confirm'))) { event.preventDefault(); }
            });
        });
        document.querySelectorAll('button[data-confirm]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                if (!window.confirm(button.getAttribute('data-confirm'))) { event.preventDefault(); }
            });
        });
    }

    function initPrint() {
        document.querySelectorAll('[data-print]').forEach(function (button) {
            button.addEventListener('click', function () { window.print(); });
        });
    }

    function initBars() {
        window.requestAnimationFrame(function () {
            document.querySelectorAll('[data-width]').forEach(function (bar) {
                var width = parseFloat(bar.getAttribute('data-width') || '0');
                bar.style.width = Math.max(0, Math.min(100, width)) + '%';
            });
            document.querySelectorAll('[data-height]').forEach(function (bar) {
                var height = parseFloat(bar.getAttribute('data-height') || '0');
                bar.style.height = Math.max(0, Math.min(100, height)) + '%';
            });
        });
    }

    function initComments() {
        var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var banner = document.querySelector('[data-reply-banner]');
        var form = document.querySelector('[data-comment-form]');
        var field = form ? form.querySelector('input[name="parent_id"]') : null;
        var target = document.getElementById('comment-body') || document.getElementById('mentor-comment') || document.getElementById('supervisor-comment');

        function clearReply(restoreFocus) {
            if (field) { field.value = ''; }
            if (banner) { banner.hidden = true; }
            document.querySelectorAll('.chat-msg.is-target, .comment.is-target').forEach(function (node) {
                node.classList.remove('is-target');
            });
            if (target) {
                target.setAttribute('placeholder', target.getAttribute('data-original-placeholder') || '');
                if (restoreFocus) { target.focus({ preventScroll: true }); }
            }
        }

        document.querySelectorAll('[data-reply-to]').forEach(function (button) {
            button.addEventListener('click', function () {
                var node = document.getElementById(button.getAttribute('data-reply-to'));
                if (!node) { return; }
                var id = button.getAttribute('data-comment-id');
                var author = button.getAttribute('data-comment-author') || 'this comment';
                if (field && id) { field.value = id; }
                if (banner) {
                    var name = banner.querySelector('[data-reply-name]');
                    if (name) { name.textContent = author; }
                    banner.hidden = false;
                }
                document.querySelectorAll('.chat-msg.is-target, .comment.is-target').forEach(function (item) {
                    item.classList.remove('is-target');
                });
                var comment = button.closest('.chat-msg') || button.closest('.comment');
                if (comment) { comment.classList.add('is-target'); }
                target = node;
                if (!target.getAttribute('data-original-placeholder')) {
                    target.setAttribute('data-original-placeholder', target.getAttribute('placeholder') || '');
                }
                target.setAttribute('placeholder', 'Replying to ' + author + '…');
                if (form) {
                    form.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'center' });
                }
                window.setTimeout(function () { target.focus({ preventScroll: true }); }, reduced ? 0 : 220);
            });
        });

        document.querySelectorAll('[data-reply-cancel]').forEach(function (button) {
            button.addEventListener('click', function () { clearReply(true); });
        });
        if (form) {
            form.addEventListener('reset', function () { clearReply(false); });
        }
    }

    function initLoading() {
        var bar = document.querySelector('[data-loadbar]');
        var watchdog = null;

        function fill() { return bar ? bar.firstElementChild : null; }

        function start() {
            if (!bar) { return; }
            window.clearTimeout(watchdog);
            bar.classList.remove('is-done');
            var node = fill();
            if (node) {
                node.style.transition = 'none';
                node.style.width = '0';
                void node.offsetWidth;
                node.style.transition = '';
            }
            bar.classList.add('is-active');
            document.body.classList.add('is-navigating');
            watchdog = window.setTimeout(stop, 15000);
        }

        function stop() {
            if (!bar) { return; }
            window.clearTimeout(watchdog);
            bar.classList.remove('is-active');
            bar.classList.remove('is-done');
            document.body.classList.remove('is-navigating');
            var node = fill();
            if (node) { node.style.width = '0'; }
        }

        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }
            var link = event.target.closest('a[href]');
            if (!link) { return; }
            if (link.target && link.target !== '_self') { return; }
            if (link.hasAttribute('download') || link.hasAttribute('data-no-loading')) { return; }
            var href = link.getAttribute('href') || '';
            if (!href || href.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(href)) { return; }
            var parsed;
            try { parsed = new URL(link.href, window.location.href); } catch (error) { return; }
            if (parsed.origin !== window.location.origin) { return; }
            if (parsed.pathname === window.location.pathname && parsed.search === window.location.search) { return; }
            start();
        }, true);

        document.addEventListener('submit', function (event) {
            if (event.defaultPrevented) { return; }
            var form = event.target;
            if (!form || form.tagName !== 'FORM' || form.hasAttribute('data-no-loading')) { return; }
            if (form.target && form.target !== '_self') { return; }
            if (form.dataset.submitting === '1') {
                event.preventDefault();
                return;
            }
            form.dataset.submitting = '1';
            var button = event.submitter || form.querySelector('button[type="submit"]');
            if (button && !button.classList.contains('is-loading')) {
                button.classList.add('is-loading');
                button.setAttribute('aria-busy', 'true');
            }
            start();
        });

        window.addEventListener('pageshow', function () {
            document.querySelectorAll('form[data-submitting="1"]').forEach(function (form) {
                form.removeAttribute('data-submitting');
                form.querySelectorAll('.is-loading').forEach(function (button) {
                    button.classList.remove('is-loading');
                    button.removeAttribute('aria-busy');
                });
            });
            stop();
        });

        window.addEventListener('load', function () {
            if (!bar || !bar.classList.contains('is-active')) { return; }
            bar.classList.add('is-done');
            window.setTimeout(stop, 220);
        });
    }

    function initFeedback() {
        var layer = document.querySelector('[data-feedback-layer]');
        if (!layer) { return; }
        var card = layer.querySelector('.feedback-card');
        var form = layer.querySelector('[data-feedback-form]');
        var done = layer.querySelector('[data-feedback-done]');
        var error = layer.querySelector('[data-feedback-error]');
        var lastFocus = null;

        function visibleFocusable() {
            return Array.prototype.filter.call(
                card.querySelectorAll('button, input, textarea, select, a[href]'),
                function (node) { return node.offsetParent !== null; }
            );
        }

        function reset() {
            if (!form) { return; }
            form.reset();
            form.hidden = false;
            if (done) { done.hidden = true; }
            if (error) { error.hidden = true; }
            delete form.dataset.sent;
            form.querySelectorAll('.is-loading').forEach(function (button) {
                button.classList.remove('is-loading');
                button.removeAttribute('aria-busy');
            });
        }

        function open() {
            if (form && form.dataset.sent === '1') { reset(); }
            lastFocus = document.activeElement;
            layer.hidden = false;
            document.body.classList.add('feedback-open');
            var first = form && !form.hidden
                ? form.querySelector('input[name="rating"]:checked') || form.querySelector('input[name="rating"]')
                : (done ? done.querySelector('button') : null);
            if (first) { first.focus({ preventScroll: true }); }
        }

        function close() {
            layer.hidden = true;
            document.body.classList.remove('feedback-open');
            if (lastFocus && lastFocus.focus) { lastFocus.focus({ preventScroll: true }); }
        }

        document.querySelectorAll('[data-feedback-open]').forEach(function (button) {
            button.addEventListener('click', open);
        });
        layer.querySelectorAll('[data-feedback-close]').forEach(function (button) {
            button.addEventListener('click', close);
        });
        layer.addEventListener('click', function (event) {
            if (event.target === layer) { close(); }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !layer.hidden) { close(); }
            if (event.key !== 'Tab' || layer.hidden) { return; }
            var items = visibleFocusable();
            if (!items.length) { return; }
            var first = items[0];
            var last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        if (!form) { return; }
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (error) { error.hidden = true; }
            if (!form.checkValidity()) { form.reportValidity(); return; }
            var button = form.querySelector('button[type="submit"]');
            if (button) {
                button.classList.add('is-loading');
                button.setAttribute('aria-busy', 'true');
            }
            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().catch(function () {
                    return { ok: false, message: 'The server returned an unexpected response. Try again.' };
                }).then(function (body) {
                    return { ok: response.ok, body: body };
                });
            }).then(function (result) {
                if (!result.ok || !result.body.ok) {
                    throw new Error(result.body.message || 'Your feedback could not be saved. Try again.');
                }
                form.dataset.sent = '1';
                form.hidden = true;
                if (done) {
                    done.hidden = false;
                    var closeButton = done.querySelector('button');
                    if (closeButton) { closeButton.focus({ preventScroll: true }); }
                }
                toast(result.body.message || 'Thank you. Your feedback has been recorded.');
            }).catch(function (failure) {
                if (error) {
                    error.textContent = failure.message || 'Your feedback could not be saved. Try again.';
                    error.hidden = false;
                }
            }).finally(function () {
                if (button) {
                    button.classList.remove('is-loading');
                    button.removeAttribute('aria-busy');
                }
            });
        });
    }

    function initAvatarPreview() {
        var form = document.querySelector('[data-avatar-form]');
        if (!form) { return; }
        var input = form.querySelector('[data-avatar-input]');
        var preview = form.querySelector('[data-avatar-preview]');
        var hint = form.querySelector('[data-avatar-hint]');
        if (!input) { return; }
        var defaultHint = hint ? hint.textContent : '';
        var hero = form.closest('.profile-hero');
        var big = hero ? hero.querySelector('.profile-avatar-large') : null;
        var current = big ? big.querySelector('[data-avatar-current]') : null;
        var initials = big ? big.querySelector('[data-avatar-initials]') : null;
        var initialsHtml = initials ? initials.outerHTML : '';
        var originalSrc = current ? current.getAttribute('src') : null;
        var created = false;

        function showBig(url) {
            if (!big) { return; }
            if (!current) {
                current = document.createElement('img');
                current.setAttribute('data-avatar-current', '');
                current.alt = 'Your profile photo';
                big.insertBefore(current, big.firstChild);
                created = true;
            }
            if (initials) { initials.hidden = true; }
            current.src = url;
        }

        function resetBig() {
            if (big && current && originalSrc) {
                current.src = originalSrc;
            } else if (big && created && current) {
                current.remove();
                current = null;
                created = false;
                if (initialsHtml) {
                    big.insertAdjacentHTML('afterbegin', initialsHtml);
                    initials = big.querySelector('[data-avatar-initials]');
                }
            }
            if (initials) { initials.hidden = false; }
        }

        function fail(message) {
            if (hint) { hint.textContent = message; hint.classList.add('error'); }
            if (preview) { preview.classList.remove('has-image'); preview.removeAttribute('src'); }
            resetBig();
            input.value = '';
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) { return; }
            if (hint) { hint.classList.remove('error'); }
            if (file.size > 8 * 1024 * 1024) {
                fail('That photo is larger than 8 MB. Choose a smaller image.');
                return;
            }
            if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) {
                fail('Choose a JPG, PNG or WebP image.');
                return;
            }
            if (hint) {
                var size = file.size >= 1024 * 1024
                    ? (file.size / (1024 * 1024)).toFixed(1) + ' MB'
                    : Math.max(1, Math.round(file.size / 1024)) + ' KB';
                hint.textContent = file.name + ' · ' + size + ' · ready to save';
            }
            if (window.URL && window.URL.createObjectURL) {
                var url = window.URL.createObjectURL(file);
                if (preview) {
                    preview.src = url;
                    preview.classList.add('has-image');
                }
                showBig(url);
            }
        });

        form.addEventListener('reset', function () {
            if (hint) { hint.textContent = defaultHint; hint.classList.remove('error'); }
            if (preview) { preview.classList.remove('has-image'); preview.removeAttribute('src'); }
            resetBig();
        });
    }

    function initEvaluation() {
        var scoreInputs = document.querySelectorAll('[data-score]');
        if (!scoreInputs.length) { return; }
        var totalNode = document.getElementById('eval-total');
        var recalc = function () {
            var total = 0;
            var complete = true;
            scoreInputs.forEach(function (input) {
                var max = parseFloat(input.getAttribute('max') || '0');
                var weight = parseFloat(input.getAttribute('data-weight') || '0');
                var value = input.value === '' ? null : parseFloat(input.value);
                if (value === null || isNaN(value)) { complete = false; return; }
                if (value > max) { value = max; input.value = String(max); }
                if (value < 0) { value = 0; input.value = '0'; }
                total += (value / max) * weight;
            });
            if (totalNode) { totalNode.textContent = total.toFixed(2) + '%'; }
            var submit = document.getElementById('eval-submit');
            if (submit) { submit.disabled = !complete; }
        };
        scoreInputs.forEach(function (input) { input.addEventListener('input', recalc); });
        recalc();
    }

    function initOrgChoice() {
        var other = document.querySelector('[data-org-other]');
        if (!other) { return; }
        var radios = Array.prototype.slice.call(document.querySelectorAll('input[type="radio"][name="org_id"]'));
        other.addEventListener('change', function () {
            if (other.value === '') { return; }
            radios.forEach(function (radio) { radio.checked = false; });
        });
        radios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (radio.checked) { other.value = ''; }
            });
        });
    }

    function initDiva() {
        var root = document.querySelector('[data-diva]');
        if (!root) { return; }
        var stream = root.querySelector('[data-diva-stream]');
        var form = root.querySelector('[data-diva-form]');
        var field = form.querySelector('textarea');
        var count = root.querySelector('[data-diva-count]');
        var status = root.querySelector('[data-diva-status]');
        var search = root.querySelector('[data-diva-search]');
        var history = root.querySelector('.diva-history');
        if (window.matchMedia('(max-width: 900px)').matches) { history.open = false; }
        var questions = stream.querySelectorAll('.chat-msg.is-mine');
        if (questions.length) {
            var latest = questions[questions.length - 1];
            stream.scrollTop = latest.offsetTop - stream.offsetTop;
        }
        field.addEventListener('input', function () {
            count.textContent = Array.from(field.value).length;
            field.setCustomValidity('');
        });
        field.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                if (form.dataset.submitting !== '1') { form.requestSubmit(); }
            }
        });
        form.addEventListener('submit', function (event) {
            if (!field.value.trim()) {
                event.preventDefault();
                field.setCustomValidity('Type a question first.');
                field.reportValidity();
            }
        });
        document.addEventListener('submit', function (event) {
            if (!event.defaultPrevented && root.contains(event.target) && (event.target === form || event.target.hasAttribute('data-diva-prompt'))) {
                status.hidden = false;
            }
        });
        window.addEventListener('pageshow', function () { status.hidden = true; });
        search.addEventListener('input', function () {
            var query = search.value.trim().toLocaleLowerCase();
            var shown = 0;
            root.querySelectorAll('[data-diva-history-item]').forEach(function (item) {
                item.hidden = item.textContent.toLocaleLowerCase().indexOf(query) === -1;
                if (!item.hidden) { shown++; }
            });
            root.querySelector('[data-diva-history-empty]').hidden = shown > 0;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initDropdown('[data-notif-toggle]', '#notif-panel');
        initDropdown('[data-user-toggle]', '#user-menu');
        initPasswords();
        initPasswordMeters();
        initDemoRoles();
        initClickRows();
        initAlerts();
        initTour();
        initConfirmations();
        initLoading();
        initPrint();
        initBars();
        initComments();
        initDiva();
        initFeedback();
        initAvatarPreview();
        initEvaluation();
        initOrgChoice();
    });
})();
