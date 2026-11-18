<?php
/** @var array $threads */
/** @var array $groups */
/** @var int $unread */
?>
<div class="page-head">
    <div>
        <h1>Messages</h1>
        <p>Conversations about a placement stay with that placement. Students, academic mentors and coordinators only reach the people recorded on it.</p>
    </div>
    <?php if ($unread > 0): ?>
        <span class="badge badge-info"><?= (int) $unread ?> unread</span>
    <?php endif; ?>
</div>

<section class="card">
    <div class="card-head"><div><h2>Conversations</h2><p>Newest activity first.</p></div></div>
    <?php if ($threads === []): ?>
        <div class="empty"><strong>No conversations yet</strong>Start one below — it stays attached to the placement, not to a private inbox.</div>
    <?php else: ?>
        <div class="thread-list">
            <?php foreach ($threads as $thread): ?>
                <?php $peer = $thread['others'][0] ?? ['user_id' => 0, 'first_name' => (string) $thread['participants'], 'last_name' => '']; ?>
                <a class="thread-row<?= (int) $thread['unread'] > 0 ? ' is-unread' : '' ?>" href="<?= e(url('messages/thread', ['id' => (int) $thread['conversation_id']])) ?>">
                    <?= avatar_html($peer, 'thread-avatar') ?>
                    <span class="thread-copy">
                        <strong><?= e($thread['subject']) ?></strong>
                        <span class="thread-meta"><?= e($thread['participants']) ?> · <?= e($thread['org_name']) ?></span>
                        <span class="thread-preview">
                            <?php if ($thread['last_kind'] === 'system'): ?>
                                <em>System</em>
                            <?php elseif ($thread['last_sender'] !== null): ?>
                                <em><?= e($thread['last_sender']) ?>:</em>
                            <?php endif; ?>
                            <?= e(mb_strimwidth((string) $thread['last_body'], 0, 90, '…')) ?>
                        </span>
                    </span>
                    <span class="thread-side">
                        <small><?= e($thread['last_created'] !== null ? fmt_date($thread['last_created'], 'd M') : '') ?></small>
                        <?php if ((int) $thread['unread'] > 0): ?>
                            <span class="thread-badge"><?= (int) $thread['unread'] ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head"><div><h2>Start a conversation</h2><p>Choose the person recorded on your placement. A thread is reused if one already exists with them.</p></div></div>
    <?php if ($groups === []): ?>
        <div class="empty"><strong>No one to message yet</strong>A conversation needs an approved placement with a mentor and a coordinator.</div>
    <?php else: ?>
        <?php foreach ($groups as $group): ?>
            <div class="start-group">
                <p class="small"><strong><?= e($group['org_name']) ?></strong><span class="muted"> · <?= e($group['student']) ?></span></p>
                <?php foreach ($group['peers'] as $peer): ?>
                    <form class="start-peer" method="post" action="<?= e(url('messages/start')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="placement_id" value="<?= (int) $group['placement_id'] ?>">
                        <input type="hidden" name="peer_user_id" value="<?= (int) $peer['user_id'] ?>">
                        <span class="start-person">
                            <strong><?= e($peer['name']) ?></strong>
                            <small class="muted"><?= e($peer['role']) ?></small>
                        </span>
                        <label class="sr-only" for="topic-<?= (int) $group['placement_id'] ?>-<?= (int) $peer['user_id'] ?>">Topic</label>
                        <select id="topic-<?= (int) $group['placement_id'] ?>-<?= (int) $peer['user_id'] ?>" name="topic">
                            <option value="general">General</option>
                            <option value="logbook">Logbook</option>
                            <option value="report">Internship report</option>
                            <option value="visit">Site visit</option>
                            <option value="career_fair">Career Fair</option>
                        </select>
                        <button class="btn btn-sm btn-outline" type="submit">Write</button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
