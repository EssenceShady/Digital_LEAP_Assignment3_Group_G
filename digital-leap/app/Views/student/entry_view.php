<?php
/** @var array $entry */
/** @var array $comments */
/** @var array $attachments */
/** @var string $threadRoute */
/** @var string $backRoute */
/** @var array<string, bool> $sentDays */
/** @var string|null $sendDayRoute */
?>
<div class="page-head">
    <div>
        <h1>Week <?= (int) $entry['week_no'] ?> log book entry (Form E)</h1>
        <p><?= e(fmt_date($entry['date_from'])) ?> – <?= e(fmt_date($entry['date_to'])) ?> · <?= e(fmt_hours($entry['hours'])) ?> hours</p>
    </div>
    <div class="page-actions">
        <?php if (in_array($entry['status'], ['draft', 'returned'], true)): ?>
            <a class="btn" href="<?= e(url('student/logbook/new', ['week' => (int) $entry['week_no']])) ?>">Edit entry</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= e(url($backRoute)) ?>">Back to logbook</a>
    </div>
</div>

<?php require APP_PATH . '/Views/shared/logbook_body.php'; ?>

<section class="card">
    <div class="card-head"><div><h2>Attachments</h2><p>PDF, Word, JPG or PNG up to 5 MB. <?= status_badge($entry['status']) ?></p></div></div>
    <?php if ($attachments === []): ?>
        <div class="empty"><strong>No attachment</strong>Add a photo or signed sheet if it supports your entry.</div>
    <?php else: ?>
        <ul class="timeline">
            <?php foreach ($attachments as $doc): ?>
                <li>
                    <div><a href="<?= e(url('download', ['id' => (int) $doc['doc_id']])) ?>"><?= e($doc['original_name']) ?></a></div>
                    <div class="when"><?= e(fmt_date($doc['uploaded_at'])) ?> · <?= number_format((int) $doc['file_size'] / 1024, 0) ?> KB · <?= review_label($doc['reviewed_at'] ?? null, $doc['reviewer_first'] ?? null, $doc['reviewer_last'] ?? null) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if (in_array($entry['status'], ['draft', 'returned', 'submitted'], true)): ?>
        <form method="post" action="<?= e(url('student/logbook/attach')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="entry_id" value="<?= (int) $entry['entry_id'] ?>">
            <div class="field">
                <label for="attachment">Add a file</label>
                <input id="attachment" name="attachment" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
            </div>
            <div class="form-actions"><button class="btn btn-outline" type="submit">Upload</button></div>
        </form>
    <?php endif; ?>
</section>

<?php if ($entry['submitted_at'] !== null): ?>
    <p class="small muted">Submitted <?= e(fmt_dt($entry['submitted_at'])) ?><?= $entry['reviewed_at'] ? ' · reviewed ' . e(fmt_dt($entry['reviewed_at'])) : '' ?></p>
<?php endif; ?>

<section class="card chat-card">
    <div class="card-head"><div><h2>Discussion</h2><p>Only you, your host supervisor and your academic mentor can see this thread.</p></div></div>
    <?php
    $replyFieldId = 'comment-body';
    $emptyTitle = 'No comments yet';
    $emptyBody = 'Your supervisor writes here when an entry is approved or returned.';
    require APP_PATH . '/Views/shared/comment_stream.php';
    ?>

    <div class="reply-banner" data-reply-banner hidden>
        <span>Replying to <strong data-reply-name></strong> — your comment will appear under theirs.</span>
        <button type="button" data-reply-cancel>Cancel reply</button>
    </div>
    <form class="chat-composer" method="post" action="<?= e(url($threadRoute)) ?>" data-comment-form>
        <?= csrf_field() ?>
        <input type="hidden" name="entry_id" value="<?= (int) $entry['entry_id'] ?>">
        <input type="hidden" name="parent_id" value="">
        <label class="sr-only" for="comment-body">Add a comment</label>
        <textarea id="comment-body" name="body" rows="2" maxlength="1000" required placeholder="Write a comment…"></textarea>
        <div class="chat-composer-actions">
            <span class="hint">Keep it specific so everyone knows what to change.</span>
            <button class="btn" type="submit">Post comment</button>
        </div>
    </form>
</section>
