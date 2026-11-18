<?php
/** @var array $student */
/** @var array $prefs */
/** @var array $orgs */
/** @var bool $submitted */
/** @var array|null $placement */
/** @var array|null $cv */
/** @var string $year */

$hasCv = $cv !== null;
$hasRanked = $prefs !== [];
$steps = [
    ['label' => 'CV on file', 'done' => $hasCv, 'note' => $hasCv ? 'Reviewers can open your CV' : 'Start here'],
    ['label' => 'Rank organisations', 'done' => $hasRanked, 'note' => count($prefs) . ' of 5 added'],
    ['label' => 'Submit your list', 'done' => $submitted, 'note' => $submitted ? 'With the internship office' : 'Locked until you submit'],
    ['label' => 'Placement allocated', 'done' => $placement !== null, 'note' => $placement !== null ? $placement['org_name'] : 'Done by the coordinator'],
];
$current = 0;
foreach ($steps as $i => $step) {
    if (!$step['done']) { $current = $i; break; }
    $current = $i;
}
$submittedAt = null;
foreach ($prefs as $pref) {
    if (!empty($pref['submitted_at'])) { $submittedAt = $pref['submitted_at']; break; }
}
$cvIsImage = $hasCv && in_array((string) $cv['mime_type'], ['image/jpeg', 'image/png'], true);
$cvCanPreview = $hasCv && ($cvIsImage || (string) $cv['mime_type'] === 'application/pdf');
$cvSize = $hasCv
    ? ((int) $cv['file_size'] >= 1048576
        ? number_format((int) $cv['file_size'] / 1048576, 1) . ' MB'
        : max(1, (int) round((int) $cv['file_size'] / 1024)) . ' KB')
    : '';
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Academic year <?= e($year) ?></p>
        <h1>Apply for an internship</h1>
        <p>Three steps: put your CV on file, rank up to five host organisations, then submit the list for the internship office.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('student/placement')) ?>">Placement status</a>
        <a class="btn btn-quiet" href="<?= e(url('profile')) ?>">My profile</a>
    </div>
</div>

<?php if ($placement !== null): ?>
    <div class="alert alert-info" role="status">
        <span>A placement has been allocated for <?= e($placement['academic_year']) ?> at <?= e($placement['org_name']) ?>. Your application is complete.</span>
        <a href="<?= e(url('student/placement')) ?>">View the placement</a>
    </div>
<?php elseif ($submitted): ?>
    <div class="alert alert-success" role="status">
        <span>Your ranked list was submitted<?= $submittedAt !== null ? ' on ' . e(fmt_dt($submittedAt)) : '' ?>. The internship office now reviews it. You will be notified when a placement is proposed.</span>
    </div>
<?php endif; ?>

<ol class="apply-steps" aria-label="Application progress">
    <?php foreach ($steps as $i => $step): ?>
        <?php
        $state = $step['done'] ? 'is-done' : ($i === $current ? 'is-current' : 'is-todo');
        ?>
        <li class="apply-step <?= $state ?>">
            <span class="apply-step-num" aria-hidden="true"><?= $step['done'] ? '&#10003;' : $i + 1 ?></span>
            <span class="apply-step-copy">
                <strong><?= e($step['label']) ?></strong>
                <span><?= e($step['note']) ?></span>
            </span>
        </li>
    <?php endforeach; ?>
</ol>

<div class="apply-layout">
    <div class="apply-main">
        <section class="card" id="cv" data-tour="apply-cv">
            <div class="card-head">
                <div>
                    <span class="step-kicker">Step 1</span>
                    <h2>Your CV</h2>
                    <p>The internship office and host organisations read this before they consider you.</p>
                </div>
                <?= $hasCv ? '<span class="badge badge-ok">On file</span>' : '<span class="badge badge-muted">Not uploaded</span>' ?>
            </div>

            <?php if ($hasCv): ?>
                <div class="receipt">
                    <span class="receipt-icon" aria-hidden="true">CV</span>
                    <div class="receipt-body">
                        <strong><?= e($cv['original_name']) ?></strong>
                        <span class="receipt-meta">
                            <?= e($cvSize) ?>
                            <span aria-hidden="true">·</span>
                            Uploaded <?= e(fmt_dt($cv['uploaded_at'])) ?>
                        </span>
                        <span class="receipt-state"><?= review_label($cv['reviewed_at'] ?? null, $cv['reviewer_first'] ?? null, $cv['reviewer_last'] ?? null) ?></span>
                    </div>
                    <div class="receipt-actions">
                        <?php if ($cvCanPreview): ?>
                            <a class="btn btn-sm" href="<?= e(url('download', ['id' => (int) $cv['doc_id'], 'inline' => 1])) ?>" target="_blank" rel="noopener">Open preview</a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline" href="<?= e(url('download', ['id' => (int) $cv['doc_id']])) ?>">Download</a>
                    </div>
                </div>
                <?php if ($cvIsImage): ?>
                    <a class="cv-preview" href="<?= e(url('download', ['id' => (int) $cv['doc_id'], 'inline' => 1])) ?>" target="_blank" rel="noopener">
                        <img src="<?= e(url('download', ['id' => (int) $cv['doc_id'], 'inline' => 1])) ?>" alt="Preview of your uploaded CV, <?= e($cv['original_name']) ?>">
                        <span>Open full size</span>
                    </a>
                <?php endif; ?>
                <details class="disclosure">
                    <summary>Replace this CV</summary>
                    <form method="post" action="<?= e(url('profile/cv')) ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return" value="apply">
                        <div class="field">
                            <label for="cv-replace">Choose a new file (PDF, Word, JPG or PNG, up to 5 MB)</label>
                            <input type="file" id="cv-replace" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                            <span class="hint">Uploading a new file replaces the CV shown to reviewers. Your previous file stays in the audit history.</span>
                        </div>
                        <div class="form-actions">
                            <button class="btn btn-outline" type="submit">Upload replacement</button>
                        </div>
                    </form>
                </details>
            <?php else: ?>
                <div class="apply-callout">
                    <div>
                        <strong>No CV on file yet</strong>
                        <p>Upload a PDF, Word document, JPG or PNG under 5 MB. You will see the file you sent, its size and when you sent it.</p>
                    </div>
                    <form method="post" action="<?= e(url('profile/cv')) ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return" value="apply">
                        <div class="field">
                            <label for="cv-new">Choose your CV</label>
                            <input type="file" id="cv-new" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                        </div>
                        <div class="form-actions">
                            <button class="btn" type="submit">Upload CV</button>
                            <span class="hint">Nothing is sent anywhere outside this computer.</span>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </section>

        <section class="card" id="ranked" data-tour="apply-ranked">
            <div class="card-head">
                <div>
                    <span class="step-kicker">Step 2</span>
                    <h2>Rank your host organisations</h2>
                    <p>Order matters: your first choice is considered first. Five is the maximum.</p>
                </div>
                <?php if (!$submitted && $prefs !== [] && $placement === null): ?>
                    <form method="post" action="<?= e(url('student/preferences/submit')) ?>" data-confirm="Submit this ranked list for review? You will not be able to edit it afterwards.">
                        <?= csrf_field() ?>
                        <button class="btn" type="submit">Submit for review</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($prefs === []): ?>
                <div class="empty">
                    <strong>No organisations ranked yet</strong>
                    Use the form on the right to add your first choice. You can reorder the list before you submit.
                </div>
            <?php else: ?>
                <?php $locked = $placement !== null; ?>
                <ol class="rank-list">
                    <?php foreach ($prefs as $pref): ?>
                        <?php $rank = (int) $pref['preference_rank']; ?>
                        <li class="rank-item<?= $pref['status'] === 'allocated' ? ' is-current' : '' ?>">
                            <span class="rank-number" aria-hidden="true"><?= $rank ?></span>
                            <div class="rank-body">
                                <div class="rank-top">
                                    <div class="rank-title">
                                        <strong><?= e($pref['org_name']) ?></strong>
                                        <span class="rank-meta">
                                            Rank <?= $rank ?> of 5
                                            <span aria-hidden="true">·</span>
                                            <?= e($pref['sector']) ?>
                                            <span aria-hidden="true">·</span>
                                            <?= e($pref['district']) ?> district
                                            <span aria-hidden="true">·</span>
                                            capacity <?= (int) $pref['capacity'] ?>
                                        </span>
                                    </div>
                                    <div class="rank-status"><?= status_badge($pref['status']) ?></div>
                                </div>
                                <p class="rank-motivation">
                                    <span class="rank-motivation-label">Why this organisation</span>
                                    <?= e($pref['motivation']) ?>
                                </p>
                                <?php if (!$locked && $pref['status'] === 'draft'): ?>
                                    <div class="rank-actions">
                                        <form method="post" action="<?= e(url('student/preferences/move')) ?>" class="inline-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="application_id" value="<?= (int) $pref['application_id'] ?>">
                                            <input type="hidden" name="direction" value="up">
                                            <button class="btn btn-sm btn-quiet" type="submit"<?= $rank === 1 ? ' disabled' : '' ?> aria-label="Move <?= e($pref['org_name']) ?> up">
                                                <span aria-hidden="true">↑</span> Move up
                                            </button>
                                        </form>
                                        <form method="post" action="<?= e(url('student/preferences/move')) ?>" class="inline-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="application_id" value="<?= (int) $pref['application_id'] ?>">
                                            <input type="hidden" name="direction" value="down">
                                            <button class="btn btn-sm btn-quiet" type="submit"<?= $rank === count($prefs) ? ' disabled' : '' ?> aria-label="Move <?= e($pref['org_name']) ?> down">
                                                <span aria-hidden="true">↓</span> Move down
                                            </button>
                                        </form>
                                        <form method="post" action="<?= e(url('student/preferences/remove')) ?>" class="inline-form" data-confirm="Remove <?= e($pref['org_name']) ?> from your preference list?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="application_id" value="<?= (int) $pref['application_id'] ?>">
                                            <button class="btn btn-sm btn-danger" type="submit">Remove</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if ($locked): ?>
                    <p class="rank-lock-note">Preferences are locked because a placement exists. Reordering and removal are unavailable.</p>
                <?php elseif ($submitted): ?>
                    <p class="rank-lock-note">This list is submitted and locked. Contact the internship coordinator if something is wrong.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="card" id="status">
            <div class="card-head">
                <div>
                    <span class="step-kicker">Step 3</span>
                    <h2>What happens after you submit</h2>
                    <p>Every stage is recorded, so you can always see where your application is.</p>
                </div>
            </div>
            <ol class="apply-timeline">
                <li class="is-done">
                    <strong>You rank the organisations</strong>
                    <span>Add your CV and up to five host organisations, in the order you want them.</span>
                </li>
                <li class="<?= $submitted ? 'is-done' : '' ?>">
                    <strong>The internship office reviews your list</strong>
                    <span><?= $submitted ? 'Submitted' . ($submittedAt !== null ? ' ' . e(fmt_dt($submittedAt)) : '') . '. The coordinator checks capacity and your MoU-eligible choices.' : 'Your list reaches the coordinator only after you press Submit for review.' ?></span>
                </li>
                <li class="<?= $placement !== null ? 'is-done' : '' ?>">
                    <strong>A placement is proposed</strong>
                    <span><?= $placement !== null ? e($placement['org_name']) . ' · ' . e(fmt_date($placement['start_date'])) . ' to ' . e(fmt_date($placement['end_date'])) : 'The coordinator allocates one host organisation and one academic mentor. You are notified immediately.' ?></span>
                </li>
                <li class="<?= $placement !== null && $placement['status'] === 'active' ? 'is-done' : '' ?>">
                    <strong>Your logbook opens</strong>
                    <span><?= $placement !== null && $placement['status'] === 'active' ? 'Your placement is active. Start week 1 from the Logbook page.' : 'Once the placement is approved and active, weekly entries become available.' ?></span>
                </li>
            </ol>
        </section>
    </div>

    <aside class="apply-side">
        <?php if ($placement !== null): ?>
            <section class="card">
                <div class="card-head"><div><h2>Allocation complete</h2></div></div>
                <div class="empty"><strong>Your application is closed</strong>A placement exists, so the ranked list is read-only. Open the placement to see dates, mentor and supervisor.</div>
                <div class="form-actions"><a class="btn btn-block" href="<?= e(url('student/placement')) ?>">Open my placement</a></div>
            </section>
        <?php elseif (count($prefs) >= 5): ?>
            <section class="card">
                <div class="card-head"><div><h2>Add an organisation</h2></div></div>
                <div class="empty"><strong>Your list is full</strong>Remove one preference before adding another.</div>
            </section>
        <?php elseif ($orgs === []): ?>
            <section class="card">
                <div class="card-head"><div><h2>Add an organisation</h2></div></div>
                <div class="empty"><strong>No organisations available</strong>Only active organisations with a signed or pending MoU can be chosen. The coordinator is onboarding more.</div>
            </section>
        <?php else: ?>
            <section class="card" data-tour="apply-add">
                <div class="card-head">
                    <div>
                        <h2>Add an organisation</h2>
                        <p><?= count($orgs) ?> eligible host<?= count($orgs) === 1 ? '' : 's' ?> available.</p>
                    </div>
                </div>
                <form method="post" action="<?= e(url('student/preferences/add')) ?>">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="org_id">Organisation</label>
                        <select id="org_id" name="org_id" required>
                            <option value="">Choose an organisation</option>
                            <?php foreach ($orgs as $org): ?>
                                <option value="<?= (int) $org['org_id'] ?>"><?= e($org['name']) ?> — <?= e($org['district']) ?> (<?= e($org['sector']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint">Only active organisations with a signed or pending MoU are listed.</span>
                    </div>
                    <div class="field">
                        <label for="motivation">Why this organisation?</label>
                        <textarea id="motivation" name="motivation" rows="4" maxlength="600" required></textarea>
                        <span class="hint">At least ten characters. The coordinator reads this when reviewing applications.</span>
                    </div>
                    <div class="form-actions">
                        <button class="btn" type="submit">Add preference</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="card apply-help">
            <div class="card-head"><div><h2>Not sure what to do?</h2><p>DIVA reads your own application and explains the next step.</p></div></div>
            <div class="chip-row">
                <a class="chip" href="<?= e(url('diva', ['q' => 'How do I apply for an internship?'])) ?>">How do I apply?</a>
                <a class="chip" href="<?= e(url('diva', ['q' => 'What happens after I submit my preferences?'])) ?>">What happens next?</a>
                <a class="chip" href="<?= e(url('diva', ['q' => 'Which organisations can I choose?'])) ?>">Which hosts can I choose?</a>
            </div>
        </section>
    </aside>
</div>
