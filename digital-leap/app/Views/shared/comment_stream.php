<?php
/** @var list<array> $comments Flat logbook_comment rows with author, role and parent quote fields. */
/** @var string $replyFieldId Id of the textarea the reply buttons target. */
/** @var string $emptyTitle Heading shown when the thread is empty. */
/** @var string $emptyBody Line shown under the heading when the thread is empty. */
use Leap\Core\Auth;

$me = (int) Auth::id();
?>
<div class="chat-stream">
    <?php if ($comments === []): ?>
        <div class="empty"><strong><?= e($emptyTitle) ?></strong><?= e($emptyBody) ?></div>
    <?php endif; ?>
    <?php foreach ($comments as $comment): ?>
        <?php
        $author = trim((string) $comment['first_name'] . ' ' . (string) $comment['last_name']);
        $parts = explode(' ', $author);
        $initials = $author === '' ? '??' : strtoupper(substr((string) $parts[0], 0, 1) . substr((string) end($parts), 0, 1));
        $isMine = (int) $comment['user_id'] === $me;
        $parentAuthor = trim((string) ($comment['parent_first'] ?? '') . ' ' . (string) ($comment['parent_last'] ?? ''));
        ?>
        <article class="chat-msg<?= $isMine ? ' is-mine' : '' ?>" id="comment-<?= (int) $comment['comment_id'] ?>">
            <?= avatar_html(['user_id' => (int) $comment['user_id'], 'first_name' => (string) $comment['first_name'], 'last_name' => (string) $comment['last_name']], 'chat-avatar') ?>
            <div class="chat-bubble">
                <div class="chat-meta">
                    <strong><?= e($author) ?></strong>
                    <span class="small muted"><?= e($comment['role_name']) ?> · <?= e(fmt_dt($comment['created_at'])) ?></span>
                </div>
                <?php if ($parentAuthor !== ''): ?>
                    <div class="chat-quote">
                        <strong><?= e($parentAuthor) ?></strong>
                        <span><?= e(mb_strimwidth((string) ($comment['parent_body'] ?? ''), 0, 120, '…')) ?></span>
                    </div>
                <?php endif; ?>
                <p class="chat-body"><?= nl2br(e($comment['body'])) ?></p>
                <div class="chat-actions">
                    <button class="chat-action" type="button" data-reply-to="<?= e($replyFieldId) ?>" data-comment-id="<?= (int) $comment['comment_id'] ?>" data-comment-author="<?= e($author) ?>">Reply</button>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
