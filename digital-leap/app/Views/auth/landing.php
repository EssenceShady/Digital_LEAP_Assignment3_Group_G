<?php
/** @var string $title */
use Leap\Core\Database;

$tableCount = (int) Database::val('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()');
$roleCount = (int) Database::val('SELECT COUNT(*) FROM roles');
$permissionCount = (int) Database::val('SELECT COUNT(*) FROM permissions');
$hostCount = (int) Database::val('SELECT COUNT(*) FROM organisation WHERE status = ?', ['active']);
?>
<header class="home-nav">
    <a class="home-brand" href="<?= e(url('')) ?>">
        <img src="<?= e(asset('img/leap-wordmark-ink.png')) ?>" alt="LEAP — Limkokwing Entrepreneurship Acceleration Platform, Sierra Leone">
        <span>Digital LEAP<small>Internship management</small></span>
    </a>
    <nav class="home-links" aria-label="On this page">
        <a href="#workflow">Workflow</a>
        <a href="#diva">DIVA</a>
        <a href="#roles">Roles</a>
        <a href="#evidence">Evidence</a>
    </nav>
    <div class="home-actions">
        <a class="btn btn-quiet" href="<?= e(url('login')) ?>">Sign in</a>
        <a class="btn btn-gold" href="<?= e(url('register')) ?>">Create account</a>
    </div>
</header>

<section class="home-hero">
    <div class="home-hero-copy">
        <p class="home-kicker">Limkokwing University · Sierra Leone</p>
        <figure class="home-hero-media">
            <img src="<?= e(asset('img/campus-hero.jpg')) ?>" width="1536" height="1024"
                 alt="Illustration of students talking on a palm-lined campus walkway at golden hour, with the coast and hills beyond."
                 decoding="async" fetchpriority="high">
            <figcaption>Illustrative scene</figcaption>
        </figure>
        <h1>Every internship, from first application to published result.</h1>
        <p class="home-lead">One workspace for the whole internship: students apply and rank hosts, supervisors review weekly logbooks, mentors assess, and the office publishes a mark built from evidence. DIVA, the assistant inside the system, knows the page you are on and tells you what to do next.</p>
        <div class="home-cta">
            <a class="btn btn-gold" href="<?= e(url('login')) ?>">Explore the demo</a>
            <a class="btn btn-outline" href="<?= e(url('register')) ?>">Register as a student</a>
        </div>
        <dl class="home-proof">
            <div><dt>Roles</dt><dd><?= $roleCount ?></dd></div>
            <div><dt>Settings</dt><dd><?= $permissionCount ?> permissions</dd></div>
            <div><dt>Tables</dt><dd><?= $tableCount ?></dd></div>
            <div><dt>Hosts</dt><dd><?= $hostCount ?> active</dd></div>
        </dl>
    </div>

    <div class="home-hero-panel" aria-hidden="true">
        <div class="preview">
            <div class="preview-bar">
                <span class="preview-dots"><i></i><i></i><i></i></span>
                <span class="preview-url">digital-leap / coordinator / dashboard</span>
            </div>
            <div class="preview-body">
                <div class="preview-side">
                    <span class="preview-logo"></span>
                    <span class="is-active">Dashboard</span>
                    <span>Applications</span>
                    <span>Placements</span>
                    <span>Grades</span>
                    <span>Audit</span>
                </div>
                <div class="preview-main">
                    <div class="preview-head">
                        <div><strong>Coordinator dashboard</strong><small>2026/2027 · 30 November 2026</small></div>
                        <span class="preview-chip">Demo</span>
                    </div>
                    <div class="preview-kpis">
                        <div><strong>2</strong><span>applications</span></div>
                        <div><strong>3</strong><span>unassigned</span></div>
                        <div><strong>2</strong><span>overdue</span></div>
                    </div>
                    <div class="preview-row">
                        <span class="preview-avatar">AK</span>
                        <span class="preview-row-copy"><strong>Week 6 logbook</strong><small>Mohamed Alie · Bright Future Mining</small></span>
                        <span class="preview-badge">Review</span>
                    </div>
                    <div class="preview-row">
                        <span class="preview-avatar preview-avatar-alt">JT</span>
                        <span class="preview-row-copy"><strong>Application to review</strong><small>Maria Kamara · health information</small></span>
                        <span class="preview-badge preview-badge-warn">Allocate</span>
                    </div>
                    <div class="preview-diva">
                        <span class="preview-diva-mark">D</span>
                        <p><strong>Two logbooks are waiting.</strong> Open the supervisor queue to review week 6 before Friday.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="home-trust" aria-label="System qualities">
    <span>Runs on XAMPP · no internet</span>
    <span>Every action audited</span>
    <span><?= $roleCount ?> roles, <?= $permissionCount ?> permissions</span>
    <span>Prepared statements · utf8mb4</span>
</section>

<section class="home-section" id="workflow">
    <div class="home-section-head">
        <p class="home-kicker">The workflow</p>
        <h2>One record from application to transcript.</h2>
        <p>The same placement record follows the student through every stage, so nobody retypes a name, a date or a mark.</p>
    </div>
    <ol class="home-steps">
        <li>
            <span class="home-step-no">01</span>
            <strong>Apply on one page</strong>
            <p>Upload the CV, choose up to five host organisations, explain the fit and submit the ranked list.</p>
        </li>
        <li>
            <span class="home-step-no">02</span>
            <strong>The office allocates</strong>
            <p>The coordinator sees every preference with its MoU status and capacity, then places the student. The ranking is advice.</p>
        </li>
        <li>
            <span class="home-step-no">03</span>
            <strong>Weekly logbook</strong>
            <p>The student submits a week; the host supervisor reads one focused entry and approves or returns it with a comment.</p>
        </li>
        <li>
            <span class="home-step-no">04</span>
            <strong>Assessment on evidence</strong>
            <p>The logbook, Form D and the student's self-evaluation make up Practical Internship; the report and the career fair add their own marks.</p>
        </li>
        <li>
            <span class="home-step-no">05</span>
            <strong>Publish and follow up</strong>
            <p>The grade is computed, moderated, released, and the career fair and visit record stay attached to the placement.</p>
        </li>
    </ol>
</section>

<section class="home-diva" id="diva">
    <div class="home-diva-copy">
        <p class="home-kicker">DIVA · the assistant inside the system</p>
        <h2>It knows your dashboard, your deadline and the guidebook.</h2>
        <p>DIVA is not a chat window bolted on the side. It reads your own live record — the placement, the logbook week, the pending report — and answers with the guidebook page it used and the exact next action to take.</p>
        <ul class="home-ticks">
            <li>Answers stay inside your permissions, so a student never sees office-only data.</li>
            <li>Works offline from the guidebook. No cloud service, no API key, no data leaving the campus.</li>
            <li>Points to the action, not just the rule: “Open Logbook → New entry”.</li>
        </ul>
        <a class="btn btn-outline" href="<?= e(url('login')) ?>">Talk to DIVA in the demo</a>
    </div>
    <div class="home-chat" aria-hidden="true">
        <div class="home-chat-head">
            <span class="home-chat-mark">D</span>
            <span><strong>DIVA</strong><small>guidebook + live placement</small></span>
        </div>
        <div class="chat-bubble chat-user">What do I do after week 12?</div>
        <div class="chat-bubble chat-diva">
            Your logbook is approved for weeks 1–12 at Bright Future Mining. Next is the internship report, then Form D from your supervisor.
            <span class="chat-cite">Guidebook p.14 · Form D p.20</span>
        </div>
        <div class="chat-bubble chat-user">What is still missing for my mark?</div>
        <div class="chat-bubble chat-diva">
            Three of four evidence items are in. The supervisor evaluation has not been submitted yet.
            <span class="chat-cite">Live placement record</span>
        </div>
        <div class="chat-input">Ask DIVA anything about your internship…</div>
    </div>
</section>

<section class="home-section" id="roles">
    <div class="home-section-head">
        <p class="home-kicker">Six points of view</p>
        <h2>Each role sees only its own work.</h2>
        <p>Every screen is filtered by role and permission, so the student, the host and the office never step on each other.</p>
    </div>
    <div class="home-role-grid">
        <article class="home-role">
            <span class="home-role-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="m8.5 14.5 2 2 4-4"/></svg></span>
            <h3>Student</h3>
            <p>Apply, rank hosts, submit a week from a phone, download the signed log book, and read results only when they are released.</p>
        </article>
        <article class="home-role">
            <span class="home-role-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8"/><path d="M8 12h5"/><path d="m8 16 2 2 4-4"/></svg></span>
            <h3>Host supervisor</h3>
            <p>One queue of weeks to review, a single comment box, and Form D evaluation with a weighted total calculated for you.</p>
        </article>
        <article class="home-role">
            <span class="home-role-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><circle cx="17" cy="9" r="2.5"/><path d="M15.5 14.5A4.5 4.5 0 0 1 21 19"/></svg></span>
            <h3>Academic mentor</h3>
            <p>Follow each assigned student, log a site visit, read the returned weeks, and mark the internship report.</p>
        </article>
        <article class="home-role">
            <span class="home-role-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 6a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="M8 12h8"/><path d="M8 16h5"/></svg></span>
            <h3>Internship office</h3>
            <p>Allocate placements, approve or hold them, chase unassigned supervisors, moderate grades with a recorded reason.</p>
        </article>
        <article class="home-role">
            <span class="home-role-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/></svg></span>
            <h3>Management</h3>
            <p>Cohort indicators, organisation spread, completion and publication rates, exported without touching the database.</p>
        </article>
        <article class="home-role">
            <span class="home-role-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 4.4 3 8.4 7 10 4-1.6 7-5.6 7-10V6Z"/><path d="m9 12 2 2 4-4"/></svg></span>
            <h3>Administrator</h3>
            <p>Accounts, roles and permissions, settings, feedback and the append-only audit trail behind every decision.</p>
        </article>
    </div>
</section>

<section class="home-evidence" id="evidence">
    <div class="home-evidence-copy">
        <p class="home-kicker">Built for the way internships actually run</p>
        <h2>A grade you can defend.</h2>
        <p>Every submit, review, return, allocation and publication is written to an audit trail with the named user and the time. If a mark is questioned, the evidence behind it is one click away.</p>
    </div>
    <dl class="home-facts">
        <div><dt>Password storage</dt><dd>bcrypt</dd></div>
        <div><dt>Database access</dt><dd>Prepared statements</dd></div>
        <div><dt>Audit trail</dt><dd>Append-only</dd></div>
        <div><dt>External access</dt><dd>Single-use links</dd></div>
        <div><dt>Hosting</dt><dd>XAMPP, offline</dd></div>
        <div><dt>Interface</dt><dd>Mobile first</dd></div>
        <div><dt>Standards</dt><dd>SQL, HTML5, CSS3</dd></div>
        <div><dt>Licence</dt><dd>MIT</dd></div>
    </dl>
</section>

<section class="home-cta-band">
    <p class="home-kicker">Try the demonstration</p>
    <h2>Pick a role. See the real workflow.</h2>
    <p>Six demo accounts open the same sample cohort — students, placements, logbooks, visits and marks — from a different point of view.</p>
    <div class="home-role-chips">
        <span>Student</span><span>Supervisor</span><span>Mentor</span><span>Coordinator</span><span>Management</span><span>Administrator</span>
    </div>
    <div class="home-cta">
        <a class="btn btn-gold" href="<?= e(url('login')) ?>">Open the demo sign-in</a>
        <a class="btn btn-ghost-light" href="<?= e(url('register')) ?>">Create a student account</a>
    </div>
    <p class="home-fineprint">Seeded sample data, reset-ready. Credentials are listed on the sign-in screen and no external service is required.</p>
</section>
