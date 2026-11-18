<?php
declare(strict_types=1);

/**
 * Build the DIVA knowledge corpus from the internship guidebook.
 *
 * This runs once, on a development machine that has pdftotext available
 * (poppler-utils). The generated storage/knowledge/guidebook.txt ships with
 * the application, so the assistant never reads or parses the PDF at runtime.
 *
 * Usage:
 *   php bin/build-diva-corpus.php "path/to/Internship_Guidebook_LEAP_Clean_Layout.pdf"
 */

$source = $argv[1] ?? '';
$target = $argv[2] ?? dirname(__DIR__) . '/storage/knowledge/guidebook.txt';

if ($source === '' || !is_file($source)) {
    fwrite(STDERR, "Guidebook PDF not found. Pass its path as the first argument.\n");
    exit(1);
}

$command = 'pdftotext -layout ' . escapeshellarg($source) . ' -';
$raw = shell_exec($command);
if (!is_string($raw) || trim($raw) === '') {
    fwrite(STDERR, "pdftotext did not return text. Install poppler-utils, or convert the guidebook to text and write it to {$target} with [[page:N]] markers.\n");
    exit(1);
}

$chunks = preg_split("/\f/", str_replace("\r", '', $raw)) ?: [];
$pages = [];
foreach ($chunks as $index => $chunk) {
    $number = $index + 1;
    if (preg_match('/Page\s+(\d+)\s+of\s+\d+/i', $chunk, $match)) {
        $number = (int) $match[1];
    }

    $text = preg_replace('/[ \t]+$/m', '', $chunk) ?? $chunk;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    $text = trim($text);
    if ($text === '') {
        continue;
    }
    $pages[$number] = $text;
}

ksort($pages);

$parts = [];
foreach ($pages as $number => $text) {
    $parts[] = '[[page:' . $number . "]]\n" . $text;
}
$corpus = implode("\n\n", $parts) . "\n";

if (!is_dir(dirname($target))) {
    mkdir(dirname($target), 0775, true);
}
file_put_contents($target, $corpus);

fwrite(STDOUT, 'Wrote ' . count($pages) . ' pages, ' . strlen($corpus) . " bytes to {$target}\n");
