<?php
declare(strict_types=1);

/**
 * Email each newly recorded logbook day to the host supervisor and mentor.
 * Run from a terminal or cron once a day, after the working day:
 *   php bin/send_daily_records.php
 *
 * Options:
 *   --date=YYYY-MM-DD  treat this as the run date (default: today)
 *   --dry-run          list what would be sent without writing anything
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use Leap\Services\LogbookDigest;

$asOf = new DateTimeImmutable('today');
$dryRun = false;

foreach (array_slice($argv ?? [], 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }
    if (str_starts_with($arg, '--date=')) {
        $value = substr($arg, 7);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            fwrite(STDERR, 'Invalid --date: ' . $value . ' (expected YYYY-MM-DD)' . PHP_EOL);
            exit(2);
        }
        $asOf = $parsed;
        continue;
    }
    fwrite(STDERR, 'Unknown option: ' . $arg . PHP_EOL);
    exit(2);
}

$stats = LogbookDigest::sendDaily($asOf, $dryRun);

foreach ($stats['preview'] as $line) {
    echo $line . PHP_EOL;
}

printf(
    "%s as of %s | day records: %d | emails %s: %d | already sent: %d\n",
    $dryRun ? 'Dry run' : 'Daily records',
    $asOf->format('Y-m-d'),
    $stats['days'],
    $dryRun ? 'that would be queued' : 'queued',
    $stats['sent'],
    $stats['skipped']
);

exit(0);
