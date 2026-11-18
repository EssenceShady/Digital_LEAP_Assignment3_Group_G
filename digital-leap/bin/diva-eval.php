<?php
declare(strict_types=1);

/**
 * DIVA evaluation: the original 31 cases plus routing and basis regressions.
 *
 * Usage:
 *   php bin/diva-eval.php              # first active user of each role
 *   php bin/diva-eval.php --user=6      # all questions as one signed-in user
 *   php bin/diva-eval.php --answers
 *
 * All database access runs in a read-only transaction. No login or save calls.
 * The optional local model may rephrase answers if enabled by the bootstrap.
 * Intent, tier, identity and content failures produce a nonzero exit status.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Controllers\DivaController;
use Leap\Services\Diva;

set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, 'Evaluation failed: ' . $error->getMessage() . "\n");
    exit(1);
});
$pdo = Database::pdo();
$pdo->exec('START TRANSACTION READ ONLY');
register_shutdown_function(static function () use ($pdo): void {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
});

$fixedUser = null;
$showAnswers = false;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--user=')) {
        $fixedUser = (int) substr($argument, 7);
    } elseif ($argument === '--answers') {
        $showAnswers = true;
    }
}

$questions = [
    ['student', 'When is my next logbook entry due?', 'deadline.logbook', 'rules'],
    ['student', 'What does Form E mark for the internship report?', 'weighting', 'rules'],
    ['student', 'How far through my placement am I?', 'progress', 'rules'],
    ['student', 'Can I see my results?', 'results', 'rules'],
    ['student', 'Where is my placement?', 'placement', 'rules'],
    ['student', 'What is Form D?', 'evaluation', 'rules'],
    ['student', 'How many weeks does the internship run?', 'internship.duration', 'rules'],
    ['student', 'What are the objectives of LEAP?', 'none', 'retrieval', null,
        ['contains' => ['Quoted from the internship guidebook, page 4']]],
    ['student', 'How do I apply for an internship?', 'apply', 'rules'],
    ['student', 'Who chooses the host organisation?', 'allocation', 'rules'],
    ['student', 'What are the two internship types?', 'internship.type', 'rules'],
    ['supervisor', 'What is waiting for me to review?', 'queue', 'rules'],
    ['supervisor', 'How is the logbook weighted?', 'weighting.logbook', 'rules'],
    ['supervisor', 'What should I check before approving a logbook entry?', 'logbook.status', 'retrieval'],
    ['supervisor', 'What is the report assessment weight?', 'weighting', 'rules'],
    ['mentor', 'What is waiting for me to review?', 'queue', 'rules'],
    ['mentor', 'What are the report criteria?', 'weighting', 'rules'],
    ['mentor', 'How do I record a site visit?', 'none', 'retrieval'],
    ['coordinator', 'How many placements are active?', 'overview', 'rules'],
    ['coordinator', 'What is pending for me?', 'queue', 'rules'],
    ['coordinator', 'What are the assessment weights?', 'weighting', 'rules'],
    ['coordinator', 'Show me the placement overview', 'overview', 'rules'],
    ['management', 'How many placements are active?', 'overview', 'rules'],
    ['management', 'How many marks are published?', 'overview', 'rules'],
    ['admin', 'How many accounts are there?', 'accounts', 'rules'],
    ['admin', 'What can you do?', 'capabilities', 'rules'],
    ['student', 'Hello!', 'greeting', 'rules'],
    ['student', 'What is the capital of France?', 'none', 'rules'],
    ['student', 'And the report?', 'deadline.report', 'rules', 'When is my next logbook entry due?',
        ['carried' => 'When is my next logbook entry due?', 'sameAs' => 'When is the report due?', 'contains' => ['report']]],
    ['student', 'Tell me more', 'followup.more', 'retrieval', 'What are the objectives of LEAP?',
        ['carried' => 'What are the objectives of LEAP?', 'contains' => ['related excerpts', 'limited to the guidebook pages']]],
    ['supervisor', 'Why?', 'followup.why', 'rules', 'How is the logbook weighted?',
        ['carried' => 'How is the logbook weighted?', 'contains' => ['implemented weighting.logbook rule'], 'not' => ['read your live records', 'not from a guess']]],
    ['student', 'Hi, when is my logbook due?', 'deadline.logbook', 'rules', null,
        ['sameAs' => 'When is my logbook due?', 'contains' => ['logbook']]],
    ['student', 'Hello! When is my report due?', 'deadline.report', 'rules', null,
        ['sameAs' => 'When is my report due?', 'contains' => ['report']]],
    ['student', 'Thanks, when is my logbook due?', 'deadline.logbook', 'rules', null,
        ['sameAs' => 'When is my logbook due?', 'contains' => ['logbook']]],
    ['student', 'Hey — what are the assessment weights?', 'weighting', 'rules', null,
        ['contains' => ['200', '75 marks', '25 marks']]],
    ['student', 'Thanks!', 'gratitude', 'rules', null, ['contains' => ['welcome']]],
    ['student', 'Why?', 'clarify', 'rules', null, ['contains' => ['no previous answer', 'unavailable']]],
    ['student', 'Tell me more', 'clarify', 'rules', null, ['contains' => ['Which internship topic']]],
    ['student', 'And France?', 'none', 'rules', 'When is my next logbook entry due?',
        ['contains' => ['sufficiently supported answer'], 'not' => ['Your logbook has']]],
    ['student', 'And France?', 'none', 'rules', 'How many weeks does the internship run?',
        ['contains' => ['sufficiently supported answer'], 'not' => ['16 weeks']]],
    ['student', 'And the report?', 'deadline.report', 'rules', 'Is my logbook late?',
        ['carried' => 'Is my logbook late?', 'sameAs' => 'When is the report due?', 'contains' => ['report']]],
    ['student', 'What about the logbook?', 'deadline.logbook', 'rules', 'When is my report due?',
        ['carried' => 'When is my report due?', 'sameAs' => 'When is the logbook due?', 'contains' => ['logbook']]],
    ['student', 'And the career fair?', 'deadline.career_fair', 'rules', 'Is my logbook late?',
        ['carried' => 'Is my logbook late?', 'sameAs' => 'When is the Career Fair due?', 'contains' => ['Career Fair']]],
    ['supervisor', 'What about the report?', 'weighting', 'rules', 'How is the logbook weighted?',
        ['carried' => 'How is the logbook weighted?', 'contains' => ['Internship Report — 75 marks', 'eight criteria']]],
    ['supervisor', 'And the logbook?', 'weighting.logbook', 'rules', 'What is the report assessment weight?',
        ['carried' => 'What is the report assessment weight?', 'contains' => ['logbook 50', 'quarter of the final mark']]],
    ['supervisor', 'And the career fair?', 'weighting', 'rules', 'How is the logbook weighted?',
        ['carried' => 'How is the logbook weighted?', 'contains' => ['Career Fair — 25 marks'], 'not' => ['report is due']]],
    ['student', 'When is it due?', 'deadline.logbook', 'rules', 'My logbook',
        ['carried' => 'My logbook', 'sameAs' => 'When is the logbook due?']],
    ['student', 'When is it due?', 'deadline.report', 'rules', 'My report',
        ['carried' => 'My report', 'sameAs' => 'When is the report due?']],
    ['student', 'When is it due?', 'deadline.career_fair', 'rules', 'When is my Career Fair report due?',
        ['carried' => 'When is my Career Fair report due?', 'sameAs' => 'When is the Career Fair due?']],
    ['student', 'When is it due?', 'deadline.report', 'rules', ['Is my logbook late?', 'And the report?'],
        ['carried' => 'And the report?', 'sameAs' => 'When is the report due?']],
    ['student', 'When is it due?', 'clarify', 'rules', null, ['contains' => ['Which subject do you mean']]],
    ['student', 'And?', 'clarify', 'rules', null, ['contains' => ['Which subject do you mean']]],
    ['student', 'What about it?', 'clarify', 'rules', 'What are the assessment weights?',
        ['contains' => ['Which subject do you mean']]],
    ['student', 'And the report?', 'clarify', 'rules', ['Is my logbook late?', 'What is the capital of France?'],
        ['contains' => ['Which subject do you mean']]],
    ['student', 'When is it due?', 'clarify', 'rules', ['Is my logbook late?', 'And France?'],
        ['contains' => ['Which subject do you mean']]],
    ['student', 'Tell me more', 'clarify', 'rules', ['What are the objectives of LEAP?', 'And France?'],
        ['contains' => ['no supported recent topic']]],
    ['student', 'Why?', 'followup.why', 'rules', ['How is the logbook weighted?', 'What is the capital of France?'],
        ['carried' => 'What is the capital of France?', 'contains' => ['No implemented intent rule matched', 'sufficiently supported excerpts'], 'not' => ['implemented weighting.logbook rule']]],
    ['student', 'Why?', 'followup.why', 'rules', ['How is the logbook weighted?', 'Thanks!'],
        ['carried' => 'Thanks!', 'contains' => ['Recognised thanks'], 'not' => ['implemented weighting.logbook rule']]],
    ['student', 'Why?', 'followup.why', 'rules', [['question' => 'Legacy answer', 'intent' => 'weighting', 'tier' => 'rules', 'answer' => 'Saved answer without basis.']],
        ['carried' => 'Legacy answer', 'contains' => ['recorded basis', 'unavailable'], 'not' => ['live records', 'Nothing was guessed']]],
    ['student', 'Tell me more', 'followup.more', 'retrieval', ['What are the objectives of LEAP?', 'Thanks!'],
        ['carried' => 'What are the objectives of LEAP?', 'contains' => ['related excerpts', 'limited to the guidebook pages']]],
    ['student', 'And the report?', 'deadline.report', 'rules', ['Is my logbook late?', 'Hello!'],
        ['carried' => 'Is my logbook late?', 'sameAs' => 'When is the report due?']],
    ['student', 'What are your limitations?', 'capabilities', 'rules', null,
        ['contains' => ['What I cannot do', 'change internship records', 'send messages']]],
    ['student', 'What can you not do?', 'capabilities', 'rules', null,
        ['contains' => ['What I cannot do', 'change internship records']]],
    ['student', 'When is it due?', 'clarify', 'rules', 'When are the report and logbook due?',
        ['contains' => ['Which subject do you mean']]],
    ['student', "  Hello,  when is my logbook due?\n", 'deadline.logbook', 'rules', null,
        ['sameAs' => 'When is my logbook due?']],
    ['student', 'And the logbook?', 'weighting.logbook', 'rules', ['How is the logbook weighted?', 'What about the report?'],
        ['carried' => 'What about the report?', 'contains' => ['quarter of the final mark']]],
    ['student', 'Why?', 'followup.why', 'rules', 'What are the objectives of LEAP?',
        ['carried' => 'What are the objectives of LEAP?', 'contains' => ['ranked the available guidebook pages']]],
    ['student', 'And the report?', 'clarify', 'rules', ['Is my logbook late?', 'And France?', 'Why?'],
        ['contains' => ['Which subject do you mean']]],
    ['student', 'When is it due?', 'clarify', 'rules', ['Is my logbook late?', 'Thanks!', 'Thanks!', 'Thanks!', 'Thanks!', 'Thanks!', 'Thanks!'],
        ['contains' => ['Which subject do you mean']]],
    ['student', 'What is DIVA?', 'capabilities', 'rules', null,
        ['contains' => ['Digital Internship Virtual Assistant', 'guidebook']]],
    ['student', 'Who are you?', 'capabilities', 'rules', null,
        ['contains' => ['DIVA', 'What I cannot do']]],
    ['admin', 'What can DIVA do?', 'capabilities', 'rules', null,
        ['contains' => ['live records', 'guidebook']]],
    ['student', 'Tell me about yourself', 'capabilities', 'rules', null,
        ['contains' => ['Digital Internship Virtual Assistant']]],
    ['student', 'How can I get a job?', 'none', 'rules', null,
        ['contains' => ['sufficiently supported answer', 'will not quote it'], 'not' => ['page 21', 'discipline']]],
];

/** @return array{uid:int,role:string,label:string,perms:list<string>} */
function contextFor(string $role, ?int $fixedUser): array
{
    if ($fixedUser !== null) {
        $row = Database::one(
            'SELECT u.user_id, u.first_name, u.last_name, r.role_key
             FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ?',
            [$fixedUser]
        );
        if ($row === null) {
            fwrite(STDERR, "No user with id {$fixedUser}.\n");
            exit(1);
        }
    } else {
        $row = Database::one(
            "SELECT u.user_id, u.first_name, u.last_name, r.role_key
             FROM users u JOIN roles r ON r.role_id = u.role_id
             WHERE u.status = 'active' AND r.role_key = ?
             ORDER BY u.user_id LIMIT 1",
            [$role]
        );
        if ($row === null) {
            fwrite(STDERR, "No active {$role} account to run these questions as.\n");
            exit(1);
        }
    }

    $perms = Database::all(
        'SELECT p.perm_key FROM role_permissions rp
         JOIN permissions p ON p.permission_id = rp.permission_id
         WHERE rp.role_id = (SELECT role_id FROM users WHERE user_id = ?)',
        [(int) $row['user_id']]
    );
    $keys = array_map(static fn (array $perm): string => (string) $perm['perm_key'], $perms);

    return [
        'uid' => (int) $row['user_id'],
        'role' => (string) $row['role_key'],
        'label' => trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
        'perms' => $keys,
    ];
}

$controller = new DivaController();
$hydrate = new ReflectionMethod($controller, 'hydrate');
$hydrate->setAccessible(true);
$contexts = [];
$tiers = [];
$answers = [];
$intentMatches = 0;
$tierMatches = 0;
$identityMatches = 0;
$contentMatches = 0;
$failures = [];

echo "DIVA evaluation set — " . date('Y-m-d H:i') . "\n";
echo str_repeat('=', 104) . "\n";
printf("%-3s %-11s %-40s %-17s %-10s %-7s %-6s %s\n", '#', 'Role', 'Question', 'Intent', 'Tier', 'Pages', 'Conf', 'Match');
echo str_repeat('-', 112) . "\n";

foreach ($questions as $index => $row) {
    [$role, $question, $expectedIntent, $expectedTier] = $row;
    $before = $row[4] ?? null;
    $checks = $row[5] ?? [];
    $context = $fixedUser !== null
        ? ($contexts['fixed'] ??= contextFor($role, $fixedUser))
        : ($contexts[$role] ??= contextFor($role, null));
    $_SESSION['uid'] = $context['uid'];
    $_SESSION['role'] = $context['role'];
    $_SESSION['name'] = $context['label'];
    $_SESSION['perms'] = $context['perms'];

    $identityOk = Auth::id() === $context['uid'] && Auth::role() === $context['role']
        && Auth::name() === $context['label'] && $_SESSION['perms'] === $context['perms'];
    foreach ($context['perms'] as $permission) {
        $identityOk = $identityOk && Auth::can($permission);
    }
    if (!$identityOk) {
        throw new RuntimeException('Auth identity does not match the chosen context for row ' . ($index + 1));
    }
    $identityMatches++;
    if ($fixedUser !== null && $expectedIntent === 'logbook.status' && $context['role'] === 'student') {
        $expectedTier = 'rules';
    }

    $history = [];
    foreach ($before === null ? [] : (is_array($before) ? $before : [$before]) as $turn) {
        if (is_array($turn)) {
            $history[] = $turn;
            continue;
        }
        $answer = Diva::ask($turn, '', $history);
        $restored = $hydrate->invoke($controller, $answer + [
            'turn_id' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'meta_json' => json_encode($answer, JSON_THROW_ON_ERROR),
        ]);
        if ($restored['resolved'] !== $answer['resolved'] || $restored['reasoning'] !== $answer['reasoning']) {
            throw new RuntimeException('Conversation hydration lost the resolved topic or recorded basis.');
        }
        $history[] = $restored;
    }
    $result = Diva::ask($question, '', $history);
    $answers[$index + 1] = $result['answer'];
    $tiers[$result['tier']] = ($tiers[$result['tier']] ?? 0) + 1;
    $intentOk = $result['intent'] === $expectedIntent;
    $tierOk = $result['tier'] === $expectedTier;
    $intentMatches += $intentOk ? 1 : 0;
    $tierMatches += $tierOk ? 1 : 0;
    $errors = [];
    if (!$intentOk) {
        $errors[] = 'expected intent ' . $expectedIntent;
    }
    if (!$tierOk) {
        $errors[] = 'expected tier ' . $expectedTier;
    }
    $contentErrors = [];
    if ($result['question'] !== $question) {
        $contentErrors[] = 'original question changed';
    }
    if ($result['carried'] !== ($checks['carried'] ?? null)) {
        $contentErrors[] = 'wrong carried question: ' . json_encode($result['carried']);
    }
    foreach ($checks['contains'] ?? [] as $text) {
        if (stripos($result['answer'], $text) === false) {
            $contentErrors[] = 'answer missing ' . $text;
        }
    }
    foreach ($checks['not'] ?? [] as $text) {
        if (stripos($result['answer'], $text) !== false) {
            $contentErrors[] = 'answer contains ' . $text;
        }
    }
    if (isset($checks['sameAs'])) {
        $canonical = Diva::ask($checks['sameAs']);
        if ($result['answer'] !== $canonical['answer']) {
            $contentErrors[] = 'answer differs from explicit canonical question';
        }
    }
    $basis = implode("\n", $result['reasoning']);
    foreach (['read your live records', 'not from a guess', 'Nothing was guessed', 'None of them (', 'facts above were passed to it unchanged'] as $claim) {
        if (stripos($basis, $claim) !== false) {
            $contentErrors[] = 'unsupported basis claim: ' . $claim;
        }
    }
    if ($result['intent'] === 'followup.why' && $history !== []) {
        foreach ($history[array_key_last($history)]['reasoning'] ?? [] as $step) {
            if (!str_contains($result['answer'], $step)) {
                $contentErrors[] = 'missing recorded basis from immediate previous answer';
            }
        }
    }
    if ($result['intent'] === 'followup.more' && $result['tier'] === 'retrieval') {
        preg_match_all('/Page (\d+):/', $result['answer'], $returnedPages);
        if (array_map('intval', $returnedPages[1]) !== array_column($result['sources'], 'page')
            || str_contains($result['answer'], 'everything the guidebook says')) {
            $contentErrors[] = 'expansion does not match returned pages';
        }
    }
    $contentMatches += $contentErrors === [] ? 1 : 0;
    $errors = array_merge($errors, $contentErrors);
    if ($errors !== []) {
        $failures[$index + 1] = $errors;
    }
    $pages = implode(',', array_map(static fn (array $source): int => (int) $source['page'], $result['sources']));

    printf(
        "%-3d %-11s %-40s %-17s %-10s %-7s %-6s %s\n",
        $index + 1,
        $context['role'],
        mb_strimwidth(($before !== null ? '↳ ' : '') . preg_replace('/\s+/', ' ', $question), 0, 38, '..'),
        $result['intent'],
        $result['tier'],
        $pages !== '' ? $pages : '-',
        $result['confidence'],
        $errors === [] ? 'PASS' : 'FAIL: ' . implode('; ', $errors)
    );
}

echo str_repeat('-', 112) . "\n";
echo 'Tiers: ';
$parts = [];
foreach ($tiers as $tier => $count) {
    $parts[] = $tier . '=' . $count;
}
echo implode(', ', $parts) . "\n";
echo 'Intent matched: ' . $intentMatches . '/' . count($questions) . "\n";
echo 'Tier matched: ' . $tierMatches . '/' . count($questions) . "\n";
echo 'Identity matched: ' . $identityMatches . '/' . count($questions) . "\n";
echo 'Content/carried matched: ' . $contentMatches . '/' . count($questions) . "\n";
echo 'Cases passed: ' . (count($questions) - count($failures)) . '/' . count($questions) . "\n";
echo 'Local model: ' . Diva::localStatus() . "\n";

if ($fixedUser !== null) {
    echo 'Every question ran as user ' . $fixedUser . ' (' . $contexts['fixed']['label'] . ', ' . $contexts['fixed']['role'] . ").\n";
    echo "In fixed-student mode, the logbook-status case expects rules (own records), not non-student guidebook retrieval.\n";
} else {
    echo 'Contexts: ';
    $labels = [];
    foreach ($contexts as $role => $context) {
        $labels[] = $role . ' -> user ' . $context['uid'] . ' (' . $context['label'] . ')';
    }
    echo implode('; ', $labels) . "\n";
}

if ($showAnswers) {
    echo "\nAnswers\n" . str_repeat('=', 104) . "\n";
    foreach ($answers as $number => $answer) {
        echo "\n[" . $number . '] ' . mb_strimwidth($questions[$number - 1][1], 0, 80, '..') . "\n";
        echo mb_strimwidth($answer, 0, 900, "..\n") . "\n";
    }
}

$pdo->rollBack();
exit($failures === [] ? 0 : 1);
