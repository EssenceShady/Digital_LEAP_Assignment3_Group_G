<?php
/** @var array $entry */
/** @var array $comments */
/** @var array $attachments */
?>
<div class="page-head">
    <div>
        <h1>Week <?= (int) $entry['week_no'] ?> log book review (Form E)</h1>
        <p><?= e(fmt_date($entry['date_from'])) ?> – <?= e(fmt_date($entry['date_to'])) ?> · <?= e(fmt_hours($entry['hours'])) ?> hours · <?= status_badge($entry['status']) ?></p>
    </div>
    <div class="page-actions"><a class="btn btn-outline" href="<?= e(url('mentor/logbook')) ?>">Back to logbook</a></div>
</div>

<?php require APP_PATH . '/Views/shared/logbook_body.php'; ?>

<section class="card">
    <div class="card-head"><div><h2>Attachments</h2></div></div>
    <?php if ($attachments === []): ?>
        <div class="empty"><strong>No attachment</strong>The entry was submitted without supporting evidence.</div>
    <?php else: ?>
        <ul class="timeline">
            <?php foreach ($attachments as $doc): ?>
                <li><div><a href="<?= e(url('download', ['id' => (int) $doc['doc_id']])) ?>"><?= e($doc['original_name']) ?></a></div><div class="when"><?= number_format((int) $doc['file_size'] / 1024, 0) ?> KB · <?= review_label($doc['reviewed_at'] ?? null, $doc['reviewer_first'] ?? null, $doc['reviewer_last'] ?? null) ?></div></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require APP_PATH . '/Views/shared/check_ins.php'; ?>

<section class="card chat-card">
    <div class="card-head"><div><h2>Discussion</h2><p>All three parties see this thread.</p></div></div>
    <?php
    $replyFieldId = 'mentor-comment';
    $emptyTitle = 'No comments yet';
    $emptyBody = 'Start the conversation about this week.';
    require APP_PATH . '/Views/shared/comment_stream.php';
    ?>

    <div class="reply-banner" data-reply-banner hidden>
        <span>Replying to <strong data-reply-name></strong> — your comment will appear under theirs.</span>
        <button type="button" data-reply-cancel>Cancel reply</button>
    </div>
    <form class="chat-composer" method="post" action="<?= e(url('mentor/entry/comment')) ?>" data-comment-form>
        <?= csrf_field() ?>
        <input type="hidden" name="entry_id" value="<?= (int) $entry['entry_id'] ?>">
        <input type="hidden" name="parent_id" value="">
        <label class="sr-only" for="mentor-comment">Add a comment</label>
        <textarea id="mentor-comment" name="body" rows="2" maxlength="1000" required placeholder="Write a comment…"></textarea>
        <div class="chat-composer-actions">
            <span class="hint"><?= $entry['status'] === 'approved' ? 'Commenting on this approved week also marks it as mentor-reviewed.' : 'Your comment is shared with the student and host supervisor.' ?></span>
            <button class="btn" type="submit">Post comment</button>
        </div>
    </form>
</section>
