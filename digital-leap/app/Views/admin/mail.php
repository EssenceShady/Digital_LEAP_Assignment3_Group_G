<?php
/** @var array $mails */
/** @var string $status */
/** @var array $counts */
?>
<div class="page-head">
    <div>
        <h1>Mail queue</h1>
        <p>Invitations, reset links and notifications waiting for delivery. In log mode mail is appended to storage/logs/mail.log.</p>
    </div>
    <div class="page-actions">
        <form method="post" action="<?= e(url('admin/mail/process')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <button class="btn" type="submit">Process queue now</button>
        </form>
    </div>
</div>

<div class="chip-row">
    <a class="chip<?= $status === '' ? ' chip-active' : '' ?>" href="<?= e(url('admin/mail')) ?>">All</a>
    <a class="chip<?= $status === 'queued' ? ' chip-active' : '' ?>" href="<?= e(url('admin/mail', ['status' => 'queued'])) ?>">Queued (<?= (int) $counts['queued'] ?>)</a>
    <a class="chip<?= $status === 'sent' ? ' chip-active' : '' ?>" href="<?= e(url('admin/mail', ['status' => 'sent'])) ?>">Sent (<?= (int) $counts['sent'] ?>)</a>
    <a class="chip<?= $status === 'failed' ? ' chip-active' : '' ?>" href="<?= e(url('admin/mail', ['status' => 'failed'])) ?>">Failed (<?= (int) $counts['failed'] ?>)</a>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table table--stack">
            <thead><tr><th>Recipient</th><th>Subject</th><th>Status</th><th>Attempts</th><th>Queued for</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($mails as $mail): ?>
                <tr>
                    <td data-label="Recipient">
                        <strong><?= e($mail['to_email']) ?></strong>
                        <?php if ($mail['first_name'] !== null): ?><div class="small muted"><?= e($mail['first_name'] . ' ' . $mail['last_name']) ?></div><?php endif; ?>
                    </td>
                    <td data-label="Subject">
                        <?= e($mail['subject']) ?>
                        <details class="mail-detail">
                            <summary>Body</summary>
                            <pre class="code-block code-block-sm"><?= e($mail['body']) ?></pre>
                        </details>
                        <?php if ($mail['last_error'] !== null): ?><div class="small diff diff-old"><?= e($mail['last_error']) ?></div><?php endif; ?>
                    </td>
                    <td data-label="Status"><?= status_badge($mail['status']) ?></td>
                    <td data-label="Attempts"><?= (int) $mail['attempts'] ?></td>
                    <td data-label="Queued for"><span class="small muted"><?= e(fmt_dt($mail['scheduled_at'])) ?></span></td>
                    <td class="right">
                        <?php if ($mail['status'] !== 'queued'): ?>
                            <form class="inline-form" method="post" action="<?= e(url('admin/mail/resend')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="queue_id" value="<?= (int) $mail['queue_id'] ?>">
                                <button class="btn btn-sm btn-outline" type="submit">Requeue</button>
                            </form>
                        <?php else: ?>
                            <span class="small muted">Waiting</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($mails === []): ?>
                <tr><td colspan="6" class="empty">No messages in this view.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
