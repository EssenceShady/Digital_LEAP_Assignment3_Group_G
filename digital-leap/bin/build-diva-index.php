<?php
declare(strict_types=1);

/**
 * Build the offline DIVA index from storage/knowledge/guidebook.txt.
 *
 * The shipped JSON carries the guidebook text, its paragraphs as retrieval
 * chunks, the marking model and the synonym table, so the vanilla-JS engine on
 * the device can run the same route -> retrieve -> compose -> verify pipeline
 * with no server. Run it whenever the guidebook text changes:
 *
 *   php bin/build-diva-index.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use Leap\Services\Diva;

$source = dirname(__DIR__) . '/storage/knowledge/guidebook.txt';
$target = dirname(__DIR__) . '/public/assets/data/diva-index.json';

if (!is_file($source)) {
    fwrite(STDERR, "Missing {$source}. Run bin/build-diva-corpus.php first.\n");
    exit(1);
}

$raw = (string) file_get_contents($source);
$parts = preg_split('/^\[\[page:(\d+)\]\]$/m', $raw, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

$pages = [];
$chunks = [];
for ($i = 1; $i + 1 < count($parts); $i += 2) {
    $number = (int) $parts[$i];
    $text = trim($parts[$i + 1]);
    $text = (string) preg_replace('/^.*Internship GuideBook -- Limkokwing.*$/m', '', $text);
    $text = trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    if ($text === '') {
        continue;
    }
    $pages[] = ['page' => $number, 'text' => $text];

    foreach (preg_split('/\n\s*\n/', $text) ?: [] as $paragraph) {
        $paragraph = trim((string) preg_replace('/\s+/', ' ', $paragraph));
        if (strlen($paragraph) >= 40) {
            $chunks[] = ['page' => $number, 'text' => $paragraph];
        }
    }
}

$index = [
    'version' => 1,
    'built_at' => date('c'),
    'guidebook' => [
        'pages' => count($pages),
        'chars' => strlen($raw),
        'title' => 'Internship Guidebook — Limkokwing University of Creative Technology, Sierra Leone',
    ],
    'marks' => Diva::ASSESSMENT,
    'rubric' => array_map(static fn (array $criterion): array => [
        'key' => $criterion['key'],
        'label' => $criterion['label'],
        'weight' => $criterion['weight'],
        'page' => $criterion['page'],
    ], Diva::RUBRIC),
    'synonyms' => Diva::synonyms(),
    'stop' => Diva::stopwords(),
    'pages' => $pages,
    'chunks' => $chunks,
];

$json = json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($json)) {
    fwrite(STDERR, "Could not encode the index: " . json_last_error_msg() . "\n");
    exit(1);
}

if (!is_dir(dirname($target))) {
    mkdir(dirname($target), 0775, true);
}
file_put_contents($target, $json);

$gzip = gzencode($json, 9);
$limit = 300 * 1024;
$ok = is_string($gzip) && strlen($gzip) < $limit;

printf(
    "Wrote %s\n  pages %d, chunks %d\n  raw %s, gzip %s (limit %s)\n",
    $target,
    count($pages),
    count($chunks),
    number_format(strlen($json)) . ' B',
    is_string($gzip) ? number_format(strlen($gzip)) . ' B' : 'n/a',
    number_format($limit) . ' B'
);
exit($ok ? 0 : 1);
