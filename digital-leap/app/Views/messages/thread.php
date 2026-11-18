<?php
/** @var array $conversation */
/** @var array $members */
/** @var array $messages */
/** @var array $reactions */
/** @var int $lastId */
use Leap\Core\Auth;

$me = (int) Auth::id();
$names = array_map(static fn (array $m): string => $m['first_name'] . ' ' . $m['last_name'], $members);
?>
<div class="page-head">
    <div>
        <a class="link-quiet small" href="<?= e(url('messages')) ?>">&larr; All conversations</a>
        <h1><?= e($conversation['subject']) ?></h1>
        <p><?= e($conversation['org_name']) ?> · <?= e($conversation['student_first'] . ' ' . $conversation['student_last']) ?> · <?= e(implode(', ', $names)) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('messages')) ?>">Back to inbox</a>
    </div>
</div>

<section class="card chat-card">
    <div class="chat-stream" data-chat data-thread="<?= (int) $conversation['conversation_id'] ?>" data-last="<?= (int) $lastId ?>" data-poll="<?= e(url('messages/poll')) ?>" aria-live="polite">
        <?php if ($messages === []): ?>
            <div class="empty"><strong>Nothing said yet</strong>Write the first message below. Everything here stays inside the placement record.</div>
        <?php endif; ?>
        <?php foreach ($messages as $m): ?>
            <?php
            $isSystem = $m['kind'] === 'system';
            $isMine = (int) ($m['sender_id'] ?? 0) === $me;
            $deleted = $m['deleted_at'] !== null;
            $sender = trim((string) ($m['first_name'] ?? '') . ' ' . (string) ($m['last_name'] ?? ''));
            ?>
            <?php if ($isSystem): ?>
                <div class="chat-system" id="m-<?= (int) $m['message_id'] ?>">
                    <span><?= e($m['body']) ?></span>
                    <time datetime="<?= e($m['created_at']) ?>"><?= e(fmt_dt($m['created_at'])) ?></time>
                </div>
            <?php else: ?>
                <article class="chat-msg<?= $isMine ? ' is-mine' : '' ?>" id="m-<?= (int) $m['message_id'] ?>">
                    <?= avatar_html(['user_id' => (int) ($m['sender_id'] ?? 0), 'first_name' => (string) ($m['first_name'] ?? ''), 'last_name' => (string) ($m['last_name'] ?? '')], 'chat-avatar') ?>
                    <div class="chat-bubble">
                        <div class="chat-meta">
                            <strong><?= e($sender !== '' ? $sender : 'Former user') ?></strong>
                            <span class="small muted"><?= e(ucwords(str_replace('_', ' ', (string) ($m['role_key'] ?? '')))) ?> · <?= e(fmt_dt($m['created_at'])) ?></span>
                            <?php if ($m['edited_at'] !== null): ?>
                                <span class="chat-edited" title="Edited <?= e(fmt_dt($m['edited_at'])) ?>">edited</span>
                            <?php endif; ?>
                        </div>

                        <?php if ((int) ($m['parent_message_id'] ?? 0) > 0): ?>
                            <div class="chat-quote">
                                <strong><?= e(trim((string) ($m['parent_first'] ?? '') . ' ' . (string) ($m['parent_last'] ?? '')) ?: 'Earlier message') ?></strong>
                                <span><?= e($m['parent_deleted'] !== null ? 'Message deleted' : mb_strimwidth((string) ($m['parent_body'] ?? ''), 0, 120, '…')) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($deleted): ?>
                            <p class="chat-deleted">This message was deleted</p>
                        <?php elseif ((string) $m['body'] !== ''): ?>
                            <p class="chat-body"><?= nl2br(e($m['body'])) ?></p>
                        <?php endif; ?>

                        <?php if ($m['doc_id'] !== null): ?>
                            <a class="chat-file" href="<?= e(url('download', ['id' => (int) $m['doc_id']])) ?>">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>
                                <span><?= e($m['original_name']) ?><small><?= number_format((int) $m['file_size'] / 1024, 0) ?> KB</small></span>
                            </a>
                        <?php endif; ?>

                        <?php if ($m['reactions'] !== []): ?>
                            <div class="chat-reactions">
                                <?php foreach ($m['reactions'] as $r): ?>
                                    <form method="post" action="<?= e(url('messages/react')) ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="conversation_id" value="<?= (int) $conversation['conversation_id'] ?>">
                                        <input type="hidden" name="message_id" value="<?= (int) $m['message_id'] ?>">
                                        <input type="hidden" name="emoji" value="<?= e($r['emoji']) ?>">
                                        <button class="chat-reaction<?= $r['mine'] ? ' is-mine' : '' ?>" type="submit" aria-label="Toggle reaction <?= e($r['emoji']) ?>, currently <?= (int) $r['total'] ?>">
                                            <?= e($r['emoji']) ?> <span><?= (int) $r['total'] ?></span>
                                        </button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$deleted): ?>
                            <div class="chat-actions">
                                <button class="chat-action" type="button" data-reply data-id="<?= (int) $m['message_id'] ?>" data-name="<?= e($sender) ?>" data-msg="<?= e(mb_strimwidth((string) $m['body'], 0, 70, '…')) ?>">Reply</button>
                                <?php foreach (array_slice($reactions, 0, 3) as $emojiOption): ?>
                                    <form method="post" action="<?= e(url('messages/react')) ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="conversation_id" value="<?= (int) $conversation['conversation_id'] ?>">
                                        <input type="hidden" name="message_id" value="<?= (int) $m['message_id'] ?>">
                                        <input type="hidden" name="emoji" value="<?= e($emojiOption) ?>">
                                        <button class="chat-action chat-action-emoji" type="submit" aria-label="React with <?= e($emojiOption) ?>"><?= e($emojiOption) ?></button>
                                    </form>
                                <?php endforeach; ?>
                                <?php if ($isMine && $m['can_edit']): ?>
                                    <details class="chat-edit">
                                        <summary class="chat-action">Edit</summary>
                                        <form method="post" action="<?= e(url('messages/edit')) ?>" class="chat-edit-form">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="conversation_id" value="<?= (int) $conversation['conversation_id'] ?>">
                                            <input type="hidden" name="message_id" value="<?= (int) $m['message_id'] ?>">
                                            <label class="sr-only" for="edit-<?= (int) $m['message_id'] ?>">Corrected message</label>
                                            <textarea id="edit-<?= (int) $m['message_id'] ?>" name="body" rows="3" maxlength="4000" required><?= e($m['body']) ?></textarea>
                                            <button class="btn btn-sm" type="submit">Save correction</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                                <?php if ($isMine): ?>
                                    <form method="post" action="<?= e(url('messages/delete')) ?>" class="inline-form" data-confirm="Delete this message for everyone? The text is replaced with a marker so replies stay readable.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="conversation_id" value="<?= (int) $conversation['conversation_id'] ?>">
                                        <input type="hidden" name="message_id" value="<?= (int) $m['message_id'] ?>">
                                        <button class="chat-action chat-action-danger" type="submit">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <form class="chat-composer" method="post" action="<?= e(url('messages/send')) ?>" enctype="multipart/form-data" data-chat-form>
        <?= csrf_field() ?>
        <input type="hidden" name="conversation_id" value="<?= (int) $conversation['conversation_id'] ?>">
        <input type="hidden" name="client_token" value="<?= e(bin2hex(random_bytes(16))) ?>">
        <input type="hidden" name="parent_id" value="" data-reply-target>
        <div class="chat-reply-bar" data-reply-bar hidden>
            <span>Replying to <strong data-reply-name></strong><em data-reply-msg></em></span>
            <button class="chat-action" type="button" data-reply-cancel>Cancel</button>
        </div>
        <label class="sr-only" for="chat-body">Message</label>
        <textarea id="chat-body" name="body" rows="2" maxlength="4000" placeholder="Write a message…" data-chat-input></textarea>
        <div class="chat-composer-actions">
            <label class="btn btn-outline btn-sm file-btn">
                Attach a file
                <input type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
            </label>
            <span class="hint">PDF, Word or image, up to 5 MB.</span>
            <button class="btn" type="submit">Send</button>
        </div>
    </form>
</section>

<p class="small muted">Messages are part of the placement record. Editing is open for 15 minutes; deleting leaves a visible marker so replies stay readable. Every send, edit and delete is written to the audit log.</p>

<script src="<?= e(asset('js/chat.js')) ?>"></script>
