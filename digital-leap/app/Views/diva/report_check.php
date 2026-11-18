<?php
/** @var array|null $result */

$statuses = [
    'present' => ['label' => 'Found', 'class' => 'badge-ok'],
    'thin' => ['label' => 'Thin', 'class' => 'badge-warn'],
    'missing' => ['label' => 'Not found', 'class' => 'badge-bad'],
    'manual' => ['label' => 'Check yourself', 'class' => 'badge-muted'],
];
?>
<div class="page-head">
    <div>
        <h1>Check my report</h1>
        <p>Compare a draft with the eight criteria printed on guidebook pages 24–25. DIVA never gives a mark and never rewrites your text.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('diva')) ?>">Back to DIVA</a>
    </div>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><div><h2>Upload or paste</h2><p>Word .docx works best because DIVA reads its headings and tables.</p></div></div>
        <form method="post" action="<?= e(url('diva/report-check')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="field">
                <label for="report">Word document (.docx, up to 5 MB)</label>
                <input id="report" name="report" type="file" accept=".docx">
                <span class="hint">The file is read once and not saved. PDF and old .doc files cannot be read offline in this build.</span>
            </div>
            <div class="field">
                <label for="report_text">Or paste the report text</label>
                <textarea id="report_text" name="report_text" rows="8" maxlength="300000" placeholder="Paste the report here if you cannot upload a .docx file."></textarea>
                <span class="hint">A file takes priority if both are given.</span>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Check the draft</button>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>What DIVA looks for</h2><p>These are the Form E criteria and their printed weights.</p></div></div>
        <div class="stat-list">
            <?php foreach (Leap\Services\Diva::RUBRIC as $criterion): ?>
                <div class="row">
                    <span><a href="<?= e(url('diva/source', ['page' => $criterion['page']])) ?>"><?= e($criterion['label']) ?></a></span>
                    <strong><?= (int) $criterion['weight'] ?>%</strong>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="hint">DIVA reports presence, absence and thin sections. The mentor still assesses the work and the coordinator still publishes the mark.</p>
    </section>
</div>

<?php if ($result !== null): ?>
    <section class="card diva-result">
        <div class="card-head">
            <div>
                <h2>Result</h2>
                <p>
                    <?= (int) $result['words'] ?> words
                    · <?= $result['format'] === 'docx' ? 'read from a .docx file' : 'read from pasted text' ?>
                    · this is advice, not a mark
                </p>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table table--stack">
                <thead>
                    <tr><th>Criterion</th><th class="right">Weight</th><th>What DIVA saw</th><th>Evidence in your text</th></tr>
                </thead>
                <tbody>
                <?php foreach ($result['criteria'] as $criterion): ?>
                    <?php $status = $statuses[$criterion['status']] ?? $statuses['missing']; ?>
                    <tr>
                        <td data-label="Criterion">
                            <a href="<?= e(url('diva/source', ['page' => (int) $criterion['page']])) ?>"><?= e($criterion['label']) ?></a>
                            <span class="small muted">page <?= (int) $criterion['page'] ?></span>
                        </td>
                        <td data-label="Weight" class="right"><?= (int) $criterion['weight'] ?>%</td>
                        <td data-label="What DIVA saw">
                            <span class="badge <?= e($status['class']) ?>"><?= e($status['label']) ?></span>
                            <span class="small muted"><?= e($criterion['guidance']) ?></span>
                        </td>
                        <td data-label="Evidence">
                            <?php if ($criterion['evidence'] !== ''): ?>
                                <span class="diva-evidence"><?= e($criterion['evidence']) ?></span>
                            <?php else: ?>
                                <span class="muted">Nothing matched this criterion.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>Presentation checks</h2><p>Mechanical checks on the draft. Judge the rest in print.</p></div></div>
        <ul class="diva-checks">
            <?php foreach ($result['layout'] as $check): ?>
                <?php $status = $statuses[$check['status']] ?? $statuses['manual']; ?>
                <li>
                    <span class="diva-check-label"><?= e($check['label']) ?></span>
                    <span class="badge <?= e($status['class']) ?>"><?= e($status['label']) ?></span>
                    <span class="small muted"><?= e($check['detail']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="hint"><?= e($result['note']) ?></p>
    </section>
<?php endif; ?>
