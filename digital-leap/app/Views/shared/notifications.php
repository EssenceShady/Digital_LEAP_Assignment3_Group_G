<?php
/** @var array $items */
/** @var string $filter */
/** @var int $unread */
?>
<div class="page-head">
    <div>
        <h1>Notifications</h1>
        <p>Every approval, return, visit and result is recorded here with a link to the record.</p>
    </div>
    <?php if ($unread > 0): ?>
        <div class="page-actions">
            <form method="post" action="<?= e(url('notifications/read-all')) ?>" data-confirm="Mark all notifications as read?">
                <?= csrf_field() ?>
                <button class="btn btn-outline" type="submit">Mark all as read (<?= (int) $unread ?>)</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <div><h2>Inbox</h2><p><?= (int) $unread ?> unread of <?= count($items) ?> shown.</p></div>
        <div class="chip-row">
            <a class="chip<?= $filter === 'all' ? ' chip-active' : '' ?>" href="<?= e(url('notifications', ['filter' => 'all'])) ?>">All</a>
            <a class="chip<?= $filter === 'unread' ? ' chip-active' : '' ?>" href="<?= e(url('notifications', ['filter' => 'unread'])) ?>">Unread</a>
            <a class="chip<?= $filter === 'read' ? ' chip-active' : '' ?>" href="<?= e(url('notifications', ['filter' => 'read'])) ?>">Read</a>
        </div>
    </div>

    <?php if ($items === []): ?>
        <div class="empty">
            <strong>Nothing here yet</strong>
            Notifications arrive when an application moves, a logbook is returned, a visit is booked or marks are published.
        </div>
    <?php else: ?>
        <ul class="timeline">
            <?php foreach ($items as $item): ?>
                <li>
                    <div class="split">
                        <div>
                            <strong><?= e($item['title']) ?></strong>
                            <?php if ((int) $item['is_read'] === 0): ?>
                                <span class="badge badge-info">Unread</span>
                            <?php endif; ?>
                            <div class="small muted"><?= e($item['body']) ?></div>
                            <div class="when"><?= e(fmt_dt($item['created_at'])) ?></div>
                        </div>
                        <form method="post" action="<?= e(url('notifications/read')) ?>" class="inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="notification_id" value="<?= (int) $item['notification_id'] ?>">
                            <button class="btn btn-sm btn-quiet" type="submit">Open</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
