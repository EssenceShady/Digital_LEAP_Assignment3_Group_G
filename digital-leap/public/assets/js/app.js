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
            if (/confirm/i.test(input.name) || input.type !== 'password' || input.hasAttribute('data-no-meter')) { return; }
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
            target: '[data-tour="feedback"]',
            title: 'Feedback has a home',
            body: 'Send feedback sits in your account menu, and Share feedback at the foot of this sidebar. Both open the same short form and reach the internship office.'
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
            if (ensure) {
                var root = document.documentElement;
                var previous = root.style.scrollBehavior;
                root.style.scrollBehavior = 'auto';
                // The sidebar is its own scroller, so a window scroll alone leaves
                // the feedback button below the fold on a 900 px viewport.
                item.node.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                rect = item.node.getBoundingClientRect();
                if (rect.top < 12 || rect.bottom > window.innerHeight - 12) {
                    var room = Math.max(12, (window.innerHeight - Math.min(rect.height, window.innerHeight - 24)) / 2);
                    window.scrollTo(0, Math.max(0, window.scrollY + rect.top - room));
                    rect = item.node.getBoundingClientRect();
                }
                root.style.scrollBehavior = previous;
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

    function initAccountTabs() {
        var shell = document.querySelector('[data-account-tabs]');
        if (!shell) { return; }
        var tabs = Array.prototype.slice.call(shell.querySelectorAll('[data-account-tab]'));
        var panels = Array.prototype.slice.call(shell.querySelectorAll('[data-account-panel]'));
        if (!tabs.length || !panels.length) { return; }

        function activate(name, focus) {
            var matched = false;
            tabs.forEach(function (tab) {
                var on = tab.getAttribute('data-account-tab') === name;
                if (on) { matched = true; }
                tab.classList.toggle('is-active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
                tab.setAttribute('tabindex', on ? '0' : '-1');
            });
            if (!matched) { return; }
            panels.forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-account-panel') !== name;
            });
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#' + name);
            }
            if (focus) {
                var active = shell.querySelector('[data-account-tab="' + name + '"]');
                if (active) { active.focus({ preventScroll: true }); }
            }
        }

        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () {
                activate(tab.getAttribute('data-account-tab'), false);
            });
            tab.addEventListener('keydown', function (event) {
                var next = null;
                if (event.key === 'ArrowRight') { next = (index + 1) % tabs.length; }
                if (event.key === 'ArrowLeft') { next = (index - 1 + tabs.length) % tabs.length; }
                if (event.key === 'Home') { next = 0; }
                if (event.key === 'End') { next = tabs.length - 1; }
                if (next === null) { return; }
                event.preventDefault();
                activate(tabs[next].getAttribute('data-account-tab'), true);
            });
        });

        var hash = (window.location.hash || '').replace('#', '');
        if (hash === 'security' || hash === 'account') {
            activate(hash, false);
        }
    }

    function initAvatarEditor() {
        var form = document.querySelector('[data-avatar-form]');
        if (!form) { return; }
        var input = form.querySelector('[data-avatar-input]');
        var dialog = document.querySelector('[data-avatar-dialog]');
        if (!input || !dialog) { return; }

        var canvas = dialog.querySelector('[data-photo-canvas]');
        var ctx = canvas ? canvas.getContext('2d') : null;
        if (!ctx) { return; }

        var zoomInput = dialog.querySelector('[data-photo-zoom]');
        var errorNode = dialog.querySelector('[data-photo-error]');
        var avatar = form.querySelector('.account-avatar');
        var titleNode = form.querySelector('[data-avatar-title]');
        var hint = form.querySelector('[data-avatar-hint]');
        var saveButton = form.querySelector('[data-avatar-save]');
        var defaultHint = hint ? hint.textContent : '';
        var defaultTitle = titleNode ? titleNode.textContent : '';
        var size = canvas.width || 640;
        var state = {
            image: null, baseScale: 1, zoom: 1, x: 0, y: 0,
            dragging: false, startX: 0, startY: 0, originX: 0, originY: 0,
            objectUrl: null, lastFocus: null
        };
        var appliedFile = null;
        var previewUrl = null;

        function dims() {
            var scale = state.baseScale * state.zoom;
            return {
                w: state.image.naturalWidth * scale,
                h: state.image.naturalHeight * scale
            };
        }

        function clamp() {
            var d = dims();
            state.x = d.w <= size ? (size - d.w) / 2 : Math.min(0, Math.max(size - d.w, state.x));
            state.y = d.h <= size ? (size - d.h) / 2 : Math.min(0, Math.max(size - d.h, state.y));
        }

        function paint() {
            if (!state.image) { return; }
            var d = dims();
            ctx.clearRect(0, 0, size, size);
            ctx.fillStyle = '#0b1512';
            ctx.fillRect(0, 0, size, size);
            ctx.drawImage(state.image, state.x, state.y, d.w, d.h);

            var radius = size / 2;
            ctx.save();
            ctx.beginPath();
            ctx.rect(0, 0, size, size);
            ctx.arc(radius, radius, radius, 0, Math.PI * 2, true);
            ctx.fillStyle = 'rgba(4, 12, 8, 0.58)';
            ctx.fill('evenodd');
            ctx.restore();

            ctx.beginPath();
            ctx.arc(radius, radius, radius - 1.5, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.9)';
            ctx.lineWidth = 3;
            ctx.stroke();
        }

        function showError(message) {
            if (!errorNode) { return; }
            errorNode.textContent = message;
            errorNode.hidden = false;
        }

        function clearError() {
            if (errorNode) { errorNode.textContent = ''; errorNode.hidden = true; }
        }

        function openDialog() {
            state.lastFocus = document.activeElement;
            if (typeof dialog.showModal === 'function') {
                if (!dialog.open) { dialog.showModal(); }
            } else {
                dialog.setAttribute('open', '');
            }
        }

        function closeDialog() {
            if (typeof dialog.close === 'function' && dialog.open) {
                dialog.close();
            } else {
                dialog.removeAttribute('open');
            }
            if (state.lastFocus && typeof state.lastFocus.focus === 'function') {
                state.lastFocus.focus({ preventScroll: true });
            }
        }

        function setInputFile(file) {
            try {
                var transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
                return true;
            } catch (error) {
                return false;
            }
        }

        function updatePreview(blob) {
            if (!avatar) { return; }
            if (previewUrl && window.URL) { window.URL.revokeObjectURL(previewUrl); }
            previewUrl = window.URL && window.URL.createObjectURL ? window.URL.createObjectURL(blob) : '';
            var current = avatar.querySelector('[data-avatar-current]');
            if (current) {
                current.src = previewUrl;
            } else {
                var initials = avatar.querySelector('[data-avatar-initials]');
                var image = document.createElement('img');
                image.setAttribute('data-avatar-current', '');
                image.alt = 'Your profile photo';
                image.src = previewUrl;
                if (initials) {
                    avatar.replaceChild(image, initials);
                } else {
                    avatar.insertBefore(image, avatar.firstChild);
                }
            }
            if (titleNode) { titleNode.textContent = 'Profile photo'; }
            if (hint) {
                hint.textContent = 'Photo ready. Choose Save photo to apply it.';
                hint.classList.remove('error');
            }
            if (saveButton) { saveButton.disabled = false; }
        }

        function fail(message) {
            if (hint) {
                hint.textContent = message;
                hint.classList.add('error');
            }
            input.value = '';
        }

        function restoreSelection() {
            clearError();
            if (appliedFile && setInputFile(appliedFile)) {
                return;
            }
            input.value = '';
            if (saveButton) { saveButton.disabled = true; }
            if (hint) {
                hint.textContent = defaultHint;
                hint.classList.remove('error');
            }
            if (titleNode) { titleNode.textContent = defaultTitle; }
        }

        function cancelEdit() {
            closeDialog();
            restoreSelection();
        }

        function setZoom(next) {
            if (!state.image) { return; }
            next = Math.max(1, Math.min(3, next));
            var ratio = next / state.zoom;
            var centre = size / 2;
            state.x = centre - (centre - state.x) * ratio;
            state.y = centre - (centre - state.y) * ratio;
            state.zoom = next;
            if (zoomInput) { zoomInput.value = String(next); }
            clamp();
            paint();
        }

        function openEditor(file) {
            clearError();
            if (state.objectUrl && window.URL) { window.URL.revokeObjectURL(state.objectUrl); }
            state.objectUrl = window.URL && window.URL.createObjectURL ? window.URL.createObjectURL(file) : '';
            var image = new Image();
            image.onload = function () {
                if (!image.naturalWidth || !image.naturalHeight) {
                    fail('That image could not be read. Choose another photo.');
                    return;
                }
                state.image = image;
                state.baseScale = Math.max(size / image.naturalWidth, size / image.naturalHeight);
                state.zoom = 1;
                state.x = (size - image.naturalWidth * state.baseScale) / 2;
                state.y = (size - image.naturalHeight * state.baseScale) / 2;
                if (zoomInput) { zoomInput.value = '1'; }
                paint();
                openDialog();
                canvas.focus({ preventScroll: true });
            };
            image.onerror = function () {
                fail('That image could not be opened. Choose a JPG, PNG or WebP photo.');
            };
            image.src = state.objectUrl;
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
            openEditor(file);
        });

        if (zoomInput) {
            zoomInput.addEventListener('input', function () {
                setZoom(parseFloat(zoomInput.value) || 1);
            });
        }

        var zoomOut = dialog.querySelector('[data-photo-zoom-out]');
        var zoomIn = dialog.querySelector('[data-photo-zoom-in]');
        if (zoomOut) {
            zoomOut.addEventListener('click', function () { setZoom(state.zoom - 0.15); });
        }
        if (zoomIn) {
            zoomIn.addEventListener('click', function () { setZoom(state.zoom + 0.15); });
        }

        canvas.addEventListener('pointerdown', function (event) {
            if (!state.image) { return; }
            event.preventDefault();
            state.dragging = true;
            state.startX = event.clientX;
            state.startY = event.clientY;
            state.originX = state.x;
            state.originY = state.y;
            canvas.classList.add('is-dragging');
            if (canvas.setPointerCapture) {
                try { canvas.setPointerCapture(event.pointerId); } catch (error) { /* ignore */ }
            }
        });

        canvas.addEventListener('pointermove', function (event) {
            if (!state.dragging || !state.image) { return; }
            var rect = canvas.getBoundingClientRect();
            var factor = rect.width > 0 ? size / rect.width : 1;
            state.x = state.originX + (event.clientX - state.startX) * factor;
            state.y = state.originY + (event.clientY - state.startY) * factor;
            clamp();
            paint();
        });

        function endDrag(event) {
            if (!state.dragging) { return; }
            state.dragging = false;
            canvas.classList.remove('is-dragging');
            if (canvas.releasePointerCapture && event && event.pointerId !== undefined) {
                try { canvas.releasePointerCapture(event.pointerId); } catch (error) { /* ignore */ }
            }
        }

        canvas.addEventListener('pointerup', endDrag);
        canvas.addEventListener('pointercancel', endDrag);

        canvas.addEventListener('keydown', function (event) {
            if (!state.image) { return; }
            var step = event.shiftKey ? 48 : 16;
            var moved = true;
            switch (event.key) {
                case 'ArrowLeft': state.x += step; break;
                case 'ArrowRight': state.x -= step; break;
                case 'ArrowUp': state.y += step; break;
                case 'ArrowDown': state.y -= step; break;
                default: moved = false;
            }
            if (!moved) { return; }
            event.preventDefault();
            clamp();
            paint();
        });

        dialog.querySelectorAll('[data-photo-cancel]').forEach(function (button) {
            button.addEventListener('click', cancelEdit);
        });

        dialog.addEventListener('cancel', function (event) {
            event.preventDefault();
            cancelEdit();
        });

        var applyButton = dialog.querySelector('[data-photo-apply]');
        if (applyButton) {
            applyButton.addEventListener('click', function () {
                if (!state.image) { return; }
                var output = document.createElement('canvas');
                output.width = size;
                output.height = size;
                var outCtx = output.getContext('2d');
                if (!outCtx) { showError('Your browser could not process the photo. Try another image.'); return; }
                var d = dims();
                outCtx.fillStyle = '#ffffff';
                outCtx.fillRect(0, 0, size, size);
                outCtx.drawImage(state.image, state.x, state.y, d.w, d.h);

                output.toBlob(function (blob) {
                    if (!blob) {
                        showError('The photo could not be processed. Try another image.');
                        return;
                    }
                    var file;
                    try {
                        file = new File([blob], 'profile-photo.jpg', { type: 'image/jpeg' });
                    } catch (error) {
                        showError('Your browser cannot prepare the edited photo. Try another image.');
                        return;
                    }
                    if (!setInputFile(file)) {
                        showError('Your browser cannot attach the edited photo. Try another image.');
                        return;
                    }
                    appliedFile = file;
                    updatePreview(blob);
                    closeDialog();
                    toast('Photo ready. Choose Save photo to apply it.');
                    if (saveButton) { saveButton.focus({ preventScroll: true }); }
                }, 'image/jpeg', 0.9);
            });
        }

        form.addEventListener('submit', function () {
            if (saveButton && !saveButton.disabled) {
                saveButton.classList.add('is-loading');
                saveButton.setAttribute('aria-busy', 'true');
            }
        });

        window.addEventListener('beforeunload', function () {
            if (state.objectUrl && window.URL) { window.URL.revokeObjectURL(state.objectUrl); }
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
            // Open at the newest answer, not at the last question's old offset.
            // On a phone the page scrolls and the composer sits over the bottom
            // edge, so the answer must stop above it, not behind it.
            if (stream.scrollHeight > stream.clientHeight + 4) {
                stream.scrollTop = stream.scrollHeight;
            } else {
                var lastTurn = stream.lastElementChild;
                var doc = document.documentElement;
                if (lastTurn && doc.scrollHeight > window.innerHeight + 4) {
                    var covered = form && form.getBoundingClientRect().top > 0
                        ? window.innerHeight - form.getBoundingClientRect().top + 16
                        : 0;
                    var target = window.scrollY + lastTurn.getBoundingClientRect().bottom - window.innerHeight + covered;
                    window.scrollTo(0, Math.max(0, Math.min(target, doc.scrollHeight - window.innerHeight)));
                }
            }
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
            // The offline engine handles the submit first and empties the box;
            // without this guard its cleared field would be marked invalid and
            // block the next question.
            if (event.defaultPrevented) { return; }
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

    function initDivaShortcut() {
        var fab = document.querySelector('.diva-fab');
        if (!fab) { return; }
        document.addEventListener('keydown', function (event) {
            if (!event.altKey || !event.shiftKey || event.ctrlKey || event.metaKey) { return; }
            if (String(event.key || '').toLowerCase() !== 'd') { return; }
            var node = event.target;
            if (node && (node.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(node.tagName))) { return; }
            event.preventDefault();
            window.location.assign(fab.getAttribute('href'));
        });
    }

    function initLogbookDays() {
        document.querySelectorAll('table.logbook-day-table').forEach(function (table) {
            table.querySelectorAll('tbody tr').forEach(function (row) {
                var field = row.querySelector('input[type="date"]');
                var label = row.querySelector('td.day-col');
                if (!field || !label) { return; }
                var sync = function () {
                    var date = new Date(field.value + 'T00:00:00');
                    if (!field.value || isNaN(date.getTime())) { return; }
                    label.textContent = date.toLocaleDateString('en-GB', { weekday: 'short' });
                };
                field.addEventListener('change', sync);
                field.addEventListener('input', sync);
                sync();
            });
        });
    }

    /* --- installable offline shell --------------------------------------- */

    function leapRoot() {
        var meta = document.querySelector('meta[name="leap-base"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    function initServiceWorker() {
        if (!('serviceWorker' in navigator)) { return; }
        // Browsers only allow service workers in a secure context. On plain
        // HTTP (a lab machine reached by IP, for example) stay silent.
        if (!window.isSecureContext) { return; }
        var root = leapRoot();
        navigator.serviceWorker.register(root + '/sw.js', { scope: (root || '') + '/' }).catch(function () {
            /* the app keeps working online without it */
        });

        document.querySelectorAll('form[action*="logout"]').forEach(function (form) {
            form.addEventListener('submit', function () {
                // Saved DIVA answers can hold personal figures, so the next
                // person at this device must not find them.
                try {
                    var keys = Object.keys(window.localStorage);
                    keys.forEach(function (key) {
                        if (key.indexOf('leap.offline.diva.') === 0) { window.localStorage.removeItem(key); }
                    });
                } catch (error) { /* ignore */ }
                var reg = navigator.serviceWorker.controller;
                if (reg) { reg.postMessage({ type: 'leap-purge' }); }
            });
        });
    }

    function initNetStatus() {
        var pills = document.querySelectorAll('[data-net-status]');
        if (!pills.length) { return; }

        // navigator.onLine only reports the device interface. A phone on Wi-Fi
        // with the placement server down still reads "online", so the pill also
        // tracks whether the last probe of this same origin succeeded.
        var reachable = true;
        var probing = false;
        window.LeapServerReachable = true;

        function paint() {
            var online = navigator.onLine && reachable;
            var pending = window.LeapOffline && window.LeapOffline.pendingCount ? window.LeapOffline.pendingCount() : 0;
            var state = !online ? 'offline' : (pending > 0 ? 'syncing' : 'online');
            var text = !online ? 'Offline' : (pending > 0 ? 'Sending ' + pending : 'Online');
            document.querySelectorAll('[data-net-status]').forEach(function (pill) {
                pill.setAttribute('data-state', state);
                var label = pill.querySelector('.net-text');
                if (label) { label.textContent = text; }
                pill.setAttribute('aria-label', !online
                    ? 'Offline. Drafts are saved on this device.'
                    : (pending > 0 ? pending + ' item(s) waiting to send.' : 'Online and up to date.'));
            });
        }

        function probe() {
            if (probing || !navigator.onLine) { return; }
            probing = true;
            var done = false;
            var finish = function (ok) {
                if (done) { return; }
                done = true;
                probing = false;
                window.LeapServerReachable = ok;
                if (reachable !== ok) {
                    reachable = ok;
                    paint();
                    if (ok && window.LeapOffline && window.LeapOffline.flush) { window.LeapOffline.flush(); }
                }
            };
            var abort = ('AbortController' in window) ? new AbortController() : null;
            var timer = setTimeout(function () { if (abort) { abort.abort(); } finish(false); }, 6000);
            fetch(location.pathname + location.search, {
                method: 'HEAD', cache: 'no-store', credentials: 'same-origin',
                signal: abort ? abort.signal : undefined
            }).then(function () {
                clearTimeout(timer);
                finish(true);
            }).catch(function () {
                clearTimeout(timer);
                finish(false);
            });
        }

        window.LeapPaintNet = paint;
        window.addEventListener('online', function () {
            reachable = true;
            window.LeapServerReachable = true;
            paint();
            probe();
            if (window.LeapOffline && window.LeapOffline.flush) { window.LeapOffline.flush(); }
        });
        window.addEventListener('offline', function () {
            reachable = false;
            window.LeapServerReachable = false;
            paint();
        });
        document.addEventListener('leap-sync', paint);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) { probe(); }
        });
        paint();
        probe();
    }

    function initReveal() {
        var targets = Array.prototype.slice.call(document.querySelectorAll('[data-reveal]'));
        if (!targets.length || !('IntersectionObserver' in window)) { return; }
        if (document.documentElement.getAttribute('data-reduce-motion') === 'on') { return; }
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
        document.documentElement.classList.add('js-reveal');
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) { return; }
                entry.target.classList.add('is-in');
                observer.unobserve(entry.target);
            });
        }, { rootMargin: '0px 0px -6% 0px', threshold: 0.1 });
        targets.forEach(function (target) { observer.observe(target); });
    }

    function initInstallPrompt() {
        var box = document.querySelector('[data-install-prompt]');
        var triggers = Array.prototype.slice.call(document.querySelectorAll('[data-install-trigger]'));
        if (!box && !triggers.length) { return; }
        var KEY = 'leap.install.dismissed';
        var deferred = null;
        var dismissed = function () {
            try { return window.localStorage.getItem(KEY) === '1'; } catch (error) { return false; }
        };
        var standalone = function () {
            if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) { return true; }
            return window.navigator.standalone === true;
        };
        var markInstalled = function () {
            if (box) { box.hidden = true; }
            triggers.forEach(function (trigger) { trigger.hidden = true; });
            try { window.localStorage.setItem(KEY, '1'); } catch (error) { /* private mode */ }
        };

        // Firefox desktop has no install button of its own, so the menu item
        // explains the manual route instead of pretending to prompt.
        var manualSteps = function () {
            var ua = (window.navigator.userAgent || '').toLowerCase();
            if (ua.indexOf('firefox') > -1) {
                return 'Firefox on the desktop has no install button of its own. Open Chrome or Edge to install Digital LEAP as an app. The offline pages still work in Firefox: keep this tab open and everything you have loaded stays readable when the server is down.';
            }
            if (ua.indexOf('safari') > -1 && ua.indexOf('chrome') === -1) {
                return 'In Safari use File → Add to Dock on macOS, or Share → Add to Home Screen on iPhone and iPad.';
            }
            return 'In Chrome or Edge the install icon sits at the right end of the address bar; the same action is in the browser menu as "Install Digital LEAP". Reload this page if the icon has not appeared yet.';
        };

        // The panel a trigger writes into is the nearest block that holds one,
        // so the top-bar menu and the settings page each keep their own copy.
        var helpFor = function (trigger) {
            var node = trigger.parentNode;
            while (node && node.nodeType === 1) {
                var found = node.querySelector('[data-install-help]');
                if (found) { return found; }
                node = node.parentNode;
            }
            return null;
        };

        var paintTrigger = function () {
            triggers.forEach(function (trigger) {
                if (standalone()) { trigger.hidden = true; return; }
                trigger.hidden = false;
                trigger.textContent = deferred
                    ? (trigger.getAttribute('data-install-label') || 'Install this app')
                    : (trigger.getAttribute('data-install-label-manual') || 'How to install this app');
            });
        };

        if (standalone()) { markInstalled(); }

        // The landing photograph and the auth form are what the visitor came
        // for, so the bar waits: past the hero on the landing, and never on
        // the auth pages, where it would sit over the fields or the submit.
        // The offer returns on the landing and on the signed-in shell.
        var boxPending = false;
        var revealBox = function () {
            if (boxPending || dismissed()) { return; }
            boxPending = true;
            var bodyClass = document.body.classList;
            if (bodyClass.contains('auth-shell')) { return; }
            if (!bodyClass.contains('landing-shell') || window.scrollY >= window.innerHeight * 0.7) {
                box.hidden = false;
                return;
            }
            var timer = 0;
            var show = function () {
                window.clearTimeout(timer);
                window.removeEventListener('scroll', onScroll);
                box.hidden = false;
            };
            var onScroll = function () {
                if (window.scrollY >= window.innerHeight * 0.7) { show(); }
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            timer = window.setTimeout(show, 12000);
        };

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            deferred = event;
            paintTrigger();
            if (box && !dismissed()) { revealBox(); }
        });

        if (box) {
            var accept = box.querySelector('[data-install-accept]');
            var dismiss = box.querySelector('[data-install-dismiss]');
            if (accept) {
                accept.addEventListener('click', function () {
                    if (!deferred) {
                        box.hidden = true;
                        triggers.forEach(function (trigger) {
                            var help = helpFor(trigger);
                            if (help) { help.textContent = manualSteps(); help.hidden = false; }
                        });
                        return;
                    }
                    deferred.prompt();
                    if (deferred.userChoice && deferred.userChoice.then) {
                        deferred.userChoice.then(function () { deferred = null; box.hidden = true; paintTrigger(); });
                    } else {
                        deferred = null;
                        box.hidden = true;
                        paintTrigger();
                    }
                });
            }
            if (dismiss) {
                dismiss.addEventListener('click', function () {
                    box.hidden = true;
                    try { window.localStorage.setItem(KEY, '1'); } catch (error) { /* private mode */ }
                });
            }
        }

        triggers.forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                if (deferred) {
                    deferred.prompt();
                    if (deferred.userChoice && deferred.userChoice.then) {
                        deferred.userChoice.then(function () { deferred = null; paintTrigger(); });
                    }
                    return;
                }
                var help = helpFor(trigger);
                if (help) {
                    help.textContent = manualSteps();
                    help.hidden = false;
                }
            });
        });

        paintTrigger();
        window.addEventListener('appinstalled', markInstalled);
    }

    function initSettings() {
        var form = document.querySelector('[data-settings-form]');
        if (form) {
            var root = document.documentElement;
            form.querySelectorAll('[data-preview-size]').forEach(function (input) {
                input.addEventListener('change', function () {
                    if (input.checked) { root.setAttribute('data-text-size', input.value); }
                });
            });
            var motion = form.querySelector('[data-preview-motion]');
            if (motion) {
                motion.addEventListener('change', function () {
                    if (motion.checked) { root.setAttribute('data-reduce-motion', 'on'); }
                    else { root.removeAttribute('data-reduce-motion'); }
                });
            }
        }

        var panel = document.querySelector('[data-device-panel]');
        if (!panel) { return; }
        var uid = panel.getAttribute('data-user') || '';

        function put(name, text) {
            var slot = panel.querySelector('[data-device-' + name + ']');
            if (slot) { slot.textContent = text; }
        }
        function readJson(key, fallback) {
            try { return JSON.parse(window.localStorage.getItem(key) || 'null') || fallback; } catch (error) { return fallback; }
        }
        function count(n, word) {
            return n === 1 ? '1 ' + word : n + ' ' + word + 's';
        }
        function divaKeys() {
            return ['leap.offline.diva.snap.' + uid, 'leap.offline.diva.pending.' + uid];
        }
        function divaTotal() {
            var saved = readJson(divaKeys()[0], {});
            var waiting = readJson(divaKeys()[1], []);
            return Object.keys(saved).length + waiting.length;
        }
        function paintCounts() {
            put('diva', count(divaTotal(), 'answer'));
            var pending = window.LeapOffline && window.LeapOffline.pendingCount ? window.LeapOffline.pendingCount() : 0;
            put('pending', count(pending, 'change'));
        }

        var standaloneNow = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
            || window.navigator.standalone === true;
        if (standaloneNow) {
            put('install', 'Installed and running as an app');
        } else if (!window.isSecureContext) {
            put('install', 'Needs HTTPS before it can be installed');
        } else if (!('serviceWorker' in navigator)) {
            put('install', 'This browser cannot install apps');
        } else {
            put('install', 'Not installed on this browser');
        }

        if ('caches' in window) {
            window.caches.keys().then(function (names) {
                var leap = names.filter(function (name) { return name.indexOf('leap-') === 0; });
                if (!leap.length) { put('cache', 'Nothing cached yet'); return null; }
                return Promise.all(leap.map(function (name) {
                    return window.caches.open(name).then(function (cache) {
                        return cache.keys().then(function (list) { return list.length; }, function () { return 0; });
                    }, function () { return 0; });
                })).then(function (sizes) {
                    var total = sizes.reduce(function (a, b) { return a + b; }, 0);
                    put('cache', count(total, 'response') + ' in ' + count(leap.length, 'cache'));
                });
            }).catch(function () { put('cache', 'Not available'); });
        } else {
            put('cache', 'Not available in this browser');
        }

        paintCounts();
        document.addEventListener('leap-sync', paintCounts);

        var clearDiva = panel.querySelector('[data-device-clear-diva]');
        if (clearDiva) {
            clearDiva.addEventListener('click', function (event) {
                if (event.defaultPrevented) { return; }
                divaKeys().forEach(function (key) {
                    try { window.localStorage.removeItem(key); } catch (error) { /* private mode */ }
                });
                paintCounts();
                toast('Saved DIVA answers on this device are cleared.');
            });
        }

        var clearShell = panel.querySelector('[data-device-clear-shell]');
        if (clearShell) {
            clearShell.addEventListener('click', function (event) {
                if (event.defaultPrevented) { return; }
                if (!('caches' in window)) { return; }
                window.caches.keys().then(function (names) {
                    // The shell cache is what makes the app work with no
                    // connection, so only the pages browsed away are dropped.
                    return Promise.all(names.filter(function (name) {
                        return name.indexOf('leap-runtime') === 0;
                    }).map(function (name) { return window.caches.delete(name); }));
                }).then(function () {
                    toast('Browsed pages are cleared. Open them again to re-save.');
                    window.caches.keys().then(function (names) {
                        put('cache', count(names.filter(function (n) { return n.indexOf('leap-') === 0; }).length, 'cache') + ' left');
                    });
                }).catch(function () { toast('The browser would not release the cache.'); });
            });
        }
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
        initSettings();
        initLoading();
        initPrint();
        initBars();
        initComments();
        initDiva();
        initDivaShortcut();
        initFeedback();
        initAccountTabs();
        initAvatarEditor();
        initEvaluation();
        initOrgChoice();
        initLogbookDays();
        initServiceWorker();
        initNetStatus();
        initInstallPrompt();
        initReveal();
    });
})();
