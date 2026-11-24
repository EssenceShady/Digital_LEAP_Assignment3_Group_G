<?php
/** @var array $entry */
/** @var array $days */
$sections = [
    'technical_skills' => 'Technical skills learned',
    'challenges' => 'Challenges experienced',
    'meeting_notes' => 'Meeting with mentor or industry supervisor',
    'reflection' => 'Reflection',
];
$showSend = isset($sendDayRoute) && $sendDayRoute !== '';
$sentDays = $sentDays ?? [];
$total = 0.0;
foreach ($days as $day) {
    $total += (float) ($day['hours'] ?? 0);
}
?>
<section class="card" id="days">
    <div class="card-head"><div><h2>Daily record</h2><p>One row for each day of the week, as in the paper log book.<?= $showSend ? ' Send a day when it is finished; the full week still goes on submit.' : '' ?></p></div></div>
    <?php if ($days === []): ?>
        <div class="empty"><strong>No daily rows</strong>This entry was written before daily records were required.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table logbook-day-table">
                <thead>
                    <tr>
                        <th class="day-col">Day</th>
                        <th class="date-col">Date</th>
                        <th>Tasks performed</th>
                        <th class="right hours-col">Hours</th>
                        <?php if ($showSend): ?><th class="right send-col">Send</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($days as $day): ?>
                        <tr>
                            <td class="day-col"><?= e(date('D', strtotime((string) $day['work_date']))) ?></td>
                            <td class="date-col"><?= e(fmt_date((string) $day['work_date'])) ?></td>
                            <td><?= nl2br(e((string) $day['tasks'])) ?></td>
                            <td class="right hours-col"><?= $day['hours'] === null ? '—' : e(rtrim(rtrim(number_format((float) $day['hours'], 1), '0'), '.')) ?></td>
                            <?php if ($showSend): ?>
                                <td class="right send-col">
                                    <?php if (isset($sentDays[(string) $day['work_date']])): ?>
                                        <span class="badge badge-ok">Sent</span>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(url($sendDayRoute)) ?>" data-confirm="Send this day to your supervisor and mentor now?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="entry_id" value="<?= (int) $entry['entry_id'] ?>">
                                            <input type="hidden" name="work_date" value="<?= e((string) $day['work_date']) ?>">
                                            <button class="btn btn-sm btn-quiet" type="submit">Send</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Week total</td>
                        <td class="right hours-col"><?= e(rtrim(rtrim(number_format($total, 1), '0'), '.')) ?></td>
                        <?php if ($showSend): ?><td class="send-col"></td><?php endif; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head"><div><h2>Weekly summary</h2><p>What the week was for and what it delivered.</p></div></div>
    <p><?= nl2br(e((string) $entry['activities'])) ?></p>
</section>

<?php
$filled = [];
foreach ($sections as $key => $label) {
    if (trim((string) ($entry[$key] ?? '')) !== '') {
        $filled[$key] = $label;
    }
}
?>
<?php if ($filled !== []): ?>
    <div class="grid grid-2">
        <?php foreach ($filled as $key => $label): ?>
            <section class="card">
                <div class="card-head"><div><h2><?= e($label) ?></h2></div></div>
                <p><?= nl2br(e((string) $entry[$key])) ?></p>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
