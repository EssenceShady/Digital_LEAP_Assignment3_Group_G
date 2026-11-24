<?php
/** @var list<array{page:int,preview:string}> $contents */
/** @var bool $pdfAvailable */
/** @var int $pdfKb */

$pageCount = count($contents);
?>
<div class="page-head">
    <div>
        <h1>Internship guidebook</h1>
        <p>The guidebook for the Semester 8 internship: the programme, policies, forms, log book and assessment. Open it in your browser's PDF viewer or keep your own copy.</p>
    </div>
    <?php if ($pdfAvailable): ?>
        <div class="page-actions">
            <a class="btn btn-outline" href="<?= e(url('guidebook/pdf')) ?>" target="_blank" rel="noopener">Open the PDF</a>
            <a class="btn" href="<?= e(url('guidebook/pdf', ['download' => 1])) ?>">Download PDF (<?= (int) $pdfKb ?> KB)</a>
        </div>
    <?php endif; ?>
</div>

<?php if (!$pdfAvailable): ?>
    <section class="card">
        <div class="empty"><strong>The PDF is not on this server</strong>Pages open as plain text until an administrator restores the guidebook file.</div>
    </section>
<?php endif; ?>

<?php
// A page opens the guidebook PDF itself in the browser's viewer, landing on
// that page: the reading view is the document, not a text copy of it.
$pageHref = static function (int $page) use ($pdfAvailable): string {
    return $pdfAvailable
        ? url('guidebook/pdf') . '#page=' . $page
        : url('diva/source', ['page' => $page]);
};
?>
<section class="card">
    <div class="card-head"><div><h2>Open any page</h2><p>The guidebook in the PDF viewer, <?= $pageCount ?> pages in all. Choose a page to open the document at that page; you can keep scrolling, or download your own copy above.</p></div></div>
    <ul class="doc-list">
        <?php foreach ($contents as $entry): ?>
            <li class="doc-row">
                <div class="doc-main">
                    <a class="doc-name" href="<?= e($pageHref((int) $entry['page'])) ?>" target="_blank" rel="noopener">Page <?= (int) $entry['page'] ?></a>
                    <?php if ($entry['preview'] !== ''): ?>
                        <p class="doc-meta"><span><?= e($entry['preview']) ?></span></p>
                    <?php endif; ?>
                </div>
                <a class="btn btn-sm btn-quiet doc-action" href="<?= e($pageHref((int) $entry['page'])) ?>" target="_blank" rel="noopener">Open in PDF</a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
