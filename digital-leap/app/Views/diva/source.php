<?php
/** @var array{page:int,text:string} $page */

$number = (int) $page['page'];
?>
<div class="page-head">
    <div>
        <h1>Guidebook page <?= $number ?></h1>
        <p>The internship guidebook text that DIVA searches. The original PDF stays the authority.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('diva')) ?>">Back to DIVA</a>
    </div>
</div>

<section class="card diva-source">
    <pre class="diva-source-text"><?= e($page['text']) ?></pre>
</section>

<div class="page-actions diva-pager">
    <?php if ($number > 1): ?>
        <a class="btn btn-sm btn-quiet" href="<?= e(url('diva/source', ['page' => $number - 1])) ?>">Page <?= $number - 1 ?></a>
    <?php endif; ?>
    <?php if ($number < 25): ?>
        <a class="btn btn-sm btn-quiet" href="<?= e(url('diva/source', ['page' => $number + 1])) ?>">Page <?= $number + 1 ?></a>
    <?php endif; ?>
</div>
