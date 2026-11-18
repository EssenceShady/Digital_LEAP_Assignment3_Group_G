<?php
/** @var array $entry */
/** @var array $comments */
/** @var array $attachments */
?>
<div class="page-head">
    <div>
        <h1>Week <?= (int) $entry['week_no'] ?> review</h1>
        <p><?= e(fmt_date($entry['date_from'])) ?> – <?= e(fmt_date($entry['date_to'])) ?> · <?= e(rtrim(rtrim(number_format((float) $entry['hours'], 1), '0'), '.')) ?> hours · <?= status_badge($entry['status']) ?></p>
    </div>
    <div class="page-actions"><a class="btn btn-outline" href="<?= e(url('supervisor/queue')) ?>">Back to queue</a></div>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><div><h2>Activities</h2></div></div>
        <p><?= nl2br(e($entry['activities'])) ?></p>
    </section>
    <section class="card">
        <div class="card-head"><div><h2>Attachments</h2></div></div>
        <?php if ($attachments === []): ?>
            <div class="empty"><strong>No attachment</strong>The student did not upload supporting evidence.</div>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($attachments as $doc): ?>
                    <li><div><a href="<?= e(url('download', ['id' => (int) $doc['doc_id']])) ?>"><?= e($doc['original_name']) ?></a></div><div class="when"><?= number_format((int) $doc['file_size'] / 1024, 0) ?> KB · <?= review_label($doc['reviewed_at'] ?? null, $doc['reviewer_first'] ?? null, $doc['reviewer_last'] ?? null) ?></div></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<section class="card chat-card">
    <div class="card-head"><div><h2>Discussion</h2><p>The student, you and the mentor share this thread.</p></div></div>
    <?php
    $replyFieldId = 'supervisor-comment';
    $emptyTitle = 'No comments yet';
    $emptyBody = 'Ask the student for detail or answer their question below.';
    require APP_PATH . '/Views/shared/comment_stream.php';
    ?>

    <div class="reply-banner" data-reply-banner hidden>
        <span>Replying to <strong data-reply-name></strong> — your comment will appear under theirs.</span>
        <button type="button" data-reply-cancel>Cancel reply</button>
    </div>
    <form class="chat-composer" method="post" action="<?= e(url('supervisor/entry/comment')) ?>" data-comment-form>
        <?= csrf_field() ?>
        <input type="hidden" name="entry_id" value="<?= (int) $entry['entry_id'] ?>">
        <input type="hidden" name="parent_id" value="">
        <label class="sr-only" for="supervisor-comment">Comment</label>
        <textarea id="supervisor-comment" name="body" rows="2" maxlength="1000" required placeholder="Write a comment…"></textarea>
        <div class="chat-composer-actions">
            <span class="hint">The student and mentor are notified when you post.</span>
            <button class="btn" type="submit">Post comment</button>
        </div>
    </form>
</section>

<?php if ($entry['status'] === 'submitted'): ?>
    <div class="grid grid-2">
        <section class="card">
            <div class="card-head"><div><h2>Approve</h2><p>Confirm the week is complete and accurate.</p></div></div>
            <form method="post" action="<?= e(url('supervisor/entry/approve')) ?>" data-confirm="Approve week <?= (int) $entry['week_no'] ?>? The student and mentor are notified.">
                <?= csrf_field() ?>
                <input type="hidden" name="entry_id" value="<?= (int) $entry['entry_id'] ?>">
                <div class="field">
                    <label for="approve-comment">Comment (optional)</label>
                    <textarea id="approve-comment" name="comment" rows="3" maxlength="1000"></textarea>
                    <span class="hint">A short note of encouragement or a highlight for the mentor.</span>
                </div>
                <div class="form-actions"><button class="btn" type="submit">Approve week</button></div>
            </form>
        </section>
        <section class="card">
            <div class="card-head"><div><h2>Return for rework</h2><p>The student must be told exactly what to change.</p></div></div>
            <form method="post" action="<?= e(url('supervisor/entry/return')) ?>" data-confirm="Return week <?= (int) $entry['week_no'] ?> to the student?">
                <?= csrf_field() ?>
                <input type="hidden" name="entry_id" value="<?= (int) $entry['entry_id'] ?>">
                <div class="field">
                    <label for="return-comment">Reason for returning</label>
                    <textarea id="return-comment" name="comment" rows="3" maxlength="1000" required></textarea>
                    <span class="hint">At least ten characters. This is saved in the discussion thread.</span>
                </div>
                <div class="form-actions"><button class="btn btn-danger" type="submit">Return entry</button></div>
            </form>
        </section>
    </div>
<?php else: ?>
    <div class="card"><div class="empty"><strong>No decision needed</strong>This entry is <?= e(str_replace('_', ' ', (string) $entry['status'])) ?>.</div></div>
<?php endif; ?>
