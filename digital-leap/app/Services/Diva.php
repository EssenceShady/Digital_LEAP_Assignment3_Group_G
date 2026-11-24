<?php
declare(strict_types=1);

namespace Leap\Services;

use Leap\Core\Auth;
use Leap\Core\Database;

/**
 * DIVA — Digital Internship Virtual Assistant.
 *
 * Three tiers, in order: rules (intent + live records), retrieval over the
 * guidebook corpus, and an optional loopback-only local model. Every tier is
 * read-only. Nothing is sent anywhere unless DIVA is explicitly enabled in
 * config/config.php and points at 127.0.0.1 or localhost.
 */
final class Diva
{
    use DivaFacts;
    use DivaCritique;
    use DivaGuide;
    use DivaContract;

    public const RUBRIC = [
        ['key' => 'introduction', 'label' => 'Introduction', 'weight' => 5, 'page' => 24,
            'pattern' => '/introduction/i',
            'guidance' => 'Open with the purpose of the report and the internship, then say what the reader will find.'],
        ['key' => 'organisation', 'label' => 'The organisation (background)', 'weight' => 15, 'page' => 24,
            'pattern' => '/organi[sz]ation|company (profile|background)|about .*(ltd|limited)|background/i',
            'guidance' => 'Describe the host organisation: what it does, where it operates and how it is structured.'],
        ['key' => 'position', 'label' => 'Internship position', 'weight' => 20, 'page' => 24,
            'pattern' => '/position|role|duties|responsibilities|job description|attachment/i',
            'guidance' => 'Explain the position you held, how it relates to your field of study and the knowledge you gained.'],
        ['key' => 'difficulties', 'label' => 'Difficulties and problems', 'weight' => 10, 'page' => 24,
            'pattern' => '/difficult|challenge|problem|obstacle|constraint/i',
            'guidance' => 'Describe the difficulties you met and how you handled them.'],
        ['key' => 'environment', 'label' => 'The work environment', 'weight' => 10, 'page' => 24,
            'pattern' => '/environment|workplace|culture|safety|team|office/i',
            'guidance' => 'Describe the workplace, the team and the conditions you worked in.'],
        ['key' => 'evaluation', 'label' => 'Evaluation of the internship', 'weight' => 20, 'page' => 24,
            'pattern' => '/evaluation|assessment|lessons? learned|skills? gained|reflection|evaluate/i',
            'guidance' => 'Assess the internship as a learning experience: what it gave you and what could improve.'],
        ['key' => 'conclusion', 'label' => 'Conclusion', 'weight' => 5, 'page' => 24,
            'pattern' => '/conclusion|recommendation|way forward/i',
            'guidance' => 'Close with your conclusion and any recommendations.'],
        ['key' => 'layout', 'label' => 'Layout and presentation', 'weight' => 15, 'page' => 25,
            'pattern' => '/(^|\n)\s*(contents|table of contents|appendix|appendices|references|bibliography)\b/i',
            'guidance' => 'Page numbers, labelling, grammar, organisation, justification, additional materials and visuals.'],
    ];

    public const CONTRACT_VERSION = 1;

    /** The marking model, in marks out of 200 (guidebook pages 24-25). */
    public const ASSESSMENT = [
        'total' => 200,
        'practical' => 100,
        'logbook' => 50,
        'form_d' => 40,
        'self_evaluation' => 10,
        'report' => 75,
        'career_fair' => 25,
    ];

    private const INTENTS = [
        'greeting' => '/^(hi+|hello+|hey+|yo|good (morning|afternoon|evening|day)|how are (you|u)|how\'s it going)[\s\p{P}]*$/iu',
        'gratitude' => '/^(thanks|thank you|thx|cheers)[\s\p{P}]*$/iu',
        'critique' => '/check (my|the) report|review (my|the) report|report feedback|my report.*(feedback|critique)|critique/i',
        'capabilities' => '/^(help|what can you (not )?do|what do you do|who are you|what are you|what do you know|what are your capabilities)\b'
            . '|what can diva (not )?do|what(?:\'s| is) diva|who is diva|what does diva (mean|stand for)|'
            . 'tell me about (yourself|diva)|define diva|how (do|does) (you|diva) work|\blimitations\b/i',
        'page.help' => '/this page|what can i do here|where am i|what does (this|the) page|what can i do on this page|what is on this page/i',
        'next' => '/what (should|do|can) i do next|next step|where do i start|what now|what is next|what\'s next/i',
        'apply' => '/\bapply\b|application (process|status)|my application|upload.*cv|\bcv\b|resume|r[eé]sum[eé]|rank(ed)? (list|hosts?)|preference list|how many hosts/i',
        'allocation' => '/who (chooses|decides|picks)|allocat|does the host (choose|decide)|host organisation.*(choose|decide)/i',
        'internship.duration' => '/how many weeks|how long (is|does|will) .*(internship|placement|training)|duration of (the )?internship|internship duration|16 weeks|twelve weeks/i',
        'internship.type' => '/in[\s-]?house|external (internship|placement)|internship types?|types? of internship|two types|leap programme/i',
        'deadline.career_fair' => '/career\s*fair|participation report/i',
        'deadline.report' => '/report.*(deadline|due|when)|deadline.*report|when.*(submit|upload).*report/i',
        'deadline.logbook' => '/log\s*book.*(deadline|due|when|late)|week.*(due|deadline)|next entry/i',
        'weighting.logbook' => '/log\s*book.*(weight|percent|mark)|weight.*log\s*book|how.*log\s*book.*(assessed|graded)/i',
        'weighting' => '/weight(ing)?|form e|rubric|criteria|how.*(marked|assessed)|assessment.*percent/i',
        'evaluation' => '/form d\b|supervisor evaluation|self[- ]evaluation|mentor evaluation|evaluation form|how.*evaluat/i',
        'queue' => '/pending|waiting|queue|to review|to assess|unmarked/i',
        'accounts' => '/users|accounts|roles|audit log|login attempts/i',
        'overview' => '/overview|summary|how many (placements|applications|students|results|marks|accounts|users|records)|status of placements|kpi|indicator/i',
        'results' => '/\bresults?\b|\bgrade\b|\bmark(s)?\b|published|pass(ed)?/i',
        'report.status' => '/report.*(status|assessed|submitted|review|ready)|my report/i',
        'logbook.status' => '/log\s*book|diary|week \d+|rework|returned/i',
        'progress' => '/progress|how far|percent|complete|milestone/i',
        'placement' => '/placement|organisation|organization|where.*(work|placed)|supervisor|mentor/i',
    ];

    private const ELABORATE = '/^(tell me more|more( detail| details| please| on that)?|go on|go deeper|elaborate|expand( on that)?|explain( that| more| further)?|can you explain( that| more)?)\b[\s?.!]*$/i';

    private const WHY = '/^(why|why is that|how do you know|how did you (get|work out|know) (that|this)|where (does|did) (that|this) come from|what is (that|this) based on|show (your|me the) (reasoning|working)|sources?)\b[\s?.!]*$/i';

    private const FOLLOW_UP = '/^(and|but|so|also|what about|how about|what of|same for|then|ok(ay)?|is (it|that|this)|does (it|that|this)|when is (it|that)|for (it|that|this))\b/i';

    private const INTENT_LABELS = [
        'critique' => 'report draft check',
        'page.help' => 'help for a page',
        'next' => 'your next actions',
        'apply' => 'applying for an internship',
        'allocation' => 'how hosts are allocated',
        'internship.duration' => 'internship duration',
        'internship.type' => 'internship types',
        'deadline.career_fair' => 'Career Fair deadline',
        'deadline.report' => 'report deadline',
        'deadline.logbook' => 'logbook deadline',
        'weighting.logbook' => 'logbook weighting',
        'weighting' => 'assessment weights',
        'evaluation' => 'evaluation forms',
        'queue' => 'your review queue',
        'accounts' => 'accounts and audit',
        'overview' => 'system overview figures',
        'results' => 'results and grades',
        'report.status' => 'report status',
        'logbook.status' => 'logbook status',
        'progress' => 'placement progress',
        'placement' => 'your placement',
    ];

    private const STOP = [
        'the', 'a', 'an', 'of', 'to', 'in', 'on', 'for', 'and', 'or', 'is', 'are', 'was', 'were', 'be',
        'been', 'i', 'my', 'me', 'we', 'our', 'you', 'your', 'it', 'this', 'that', 'with', 'at', 'by',
        'from', 'as', 'do', 'does', 'did', 'how', 'what', 'when', 'where', 'which', 'who', 'can',
        'could', 'should', 'would', 'will', 'about', 'please', 'tell', 'give', 'show', 'need', 'want',
        'if', 'not', 'no', 'yes', 'am', 'so', 'than', 'then', 'there', 'their', 'has', 'have', 'had',
        // The corpus is the guidebook: its own name, and the words people use
        // to ask what it says, carry no retrieval signal.
        'guidebook', 'say', 'says', 'according',
    ];

    private const MAX_UPLOAD = 5242880;

    private static ?array $corpus = null;
    private static ?array $routeTable = null;
    private static ?string $localOutcome = null;
    private static ?array $capture = null;
    private static ?int $captureFor = null;

    /**
     * @param list<array{question:string,intent:string,tier:string,answer?:string,reasoning?:list<string>,sources?:list<array>}> $history
     *        earlier turns of the same conversation, oldest first
     */
    public static function ask(string $question, string $from = '', array $history = []): array
    {
        $working = trim((string) preg_replace('/\s+/', ' ', $question));
        if ($working === '') {
            return [
                'question' => $question,
                'intent' => 'empty',
                'tier' => 'rules',
                'confidence' => 'high',
                'context' => self::context($from),
                'answer' => 'Ask me about a deadline, your placement, the logbook, the report, your results, or where to find a page. I answer from this system and from the internship guidebook.',
                'sources' => [],
                'links' => self::links([self::link('Find a page', 'diva'), self::link('Check my report', 'diva/report-check')]),
                'followups' => self::followUps('capabilities', $from),
                'reasoning' => [],
                'keywords' => [],
                'carried' => null,
                'mode' => 'online',
                'slots' => self::slots('', 'empty', self::context($from)),
                'evidence' => [['type' => 'rule', 'value' => 'empty question']],
                'next_action' => ['label' => 'Find a page', 'route' => 'diva', 'params' => []],
            ];
        }

        while (preg_match('/^(?:hi+|hello+|hey+|thanks|thank you|thx|cheers)\b[\s\p{P}]*/iu', $working, $opening)) {
            $stripped = substr($working, strlen($opening[0]));
            if ($stripped === '') {
                break;
            }
            $working = $stripped;
        }
        $context = self::context($from);
        $previous = self::topicalTurn($history);
        $immediate = $history === [] ? null : $history[array_key_last($history)];
        $keywords = self::keywords($working);
        $steps = [];
        $sources = [];
        $links = [];
        $tier = 'rules';
        $answer = '';
        $carried = null;

        $steps[] = $keywords === []
            ? 'Read your message. It has no subject words of its own.'
            : 'Read your message and picked out the words that carry the meaning: ' . implode(', ', $keywords) . '.';

        if (preg_match(self::WHY, $working)) {
            if ($immediate === null) {
                $intent = 'clarify';
                $answer = 'There is no previous answer in this conversation, so its recorded basis is unavailable. Which answer would you like me to explain?';
                $steps[] = 'The explanation rule found no previous answer to explain.';
            } else {
                $intent = 'followup.why';
                $carried = $immediate['question'];
                $steps[] = 'The explanation rule replays only the basis recorded with the immediately previous answer.';
                $answer = self::whyAnswer($immediate);
                $sources = array_slice($immediate['sources'] ?? [], 0, 3);
            }
        } elseif (preg_match(self::ELABORATE, $working)) {
            if ($previous === null) {
                $intent = 'clarify';
                $answer = 'Which internship topic would you like more detail on? There is no supported recent topic to expand.';
                $steps[] = 'The expansion rule found no supported recent topical answer.';
            } else {
                $intent = 'followup.more';
                $carried = $previous['question'];
                $topic = $previous['resolved'] ?? $previous['question'];
                $deeper = self::retrieve($topic, 4);
                $steps[] = 'The expansion rule searched for related guidebook excerpts on the recent supported topic.';
                if (!self::supportedMatch($topic, $deeper)) {
                    $answer = 'I could not find additional supported guidebook excerpts for that topic in this search. This does not establish that no further information exists.';
                    $steps[] = 'This search returned no sufficiently supported excerpts to add.';
                } else {
                    $answer = self::deeperAnswer($previous['question'], $deeper);
                    $sources = $deeper;
                    $tier = 'retrieval';
                    $steps[] = 'Kept related excerpts from pages ' . implode(', ', array_map(static fn (array $s): int => (int) $s['page'], $deeper)) . '; these are not an exhaustive account of the guidebook.';
                }
                $links = self::navLinks($topic);
            }
        } else {
            $intent = self::intent($working);
            $resolved = self::resolveFollowUp($working, $previous);
            if ($resolved !== null) {
                $working = $resolved['question'];
                $intent = $resolved['intent'];
                if ($intent !== 'clarify') {
                    $carried = $previous['question'];
                    $steps[] = 'The follow-up rule resolved the recent subject and question family to: ' . $working;
                }
            } elseif ($intent === null && self::readsAsFollowUp($working, $keywords)
                && preg_match('/\b(it|that|this|they|them|those|these|one)\b|^(and|but|so|also|what about|how about|what of|same for|then|ok(ay)?)[\s?.!]*$/i', $working)) {
                $intent = 'clarify';
            }

            if ($intent === 'clarify') {
                $answer = 'Which subject do you mean, and what would you like to know about it — its deadline, weighting or status? Please name the report, logbook, Career Fair or another internship topic.';
                $steps[] = 'The clarification rule found no unambiguous subject and question family for this follow-up.';
            } elseif ($intent === 'greeting') {
                $steps[] = 'Recognised a greeting.';
                $greeting = self::greetingAnswer();
                $answer = $greeting['answer'];
                $links = $greeting['links'];
            } elseif ($intent === 'gratitude') {
                $steps[] = 'Recognised thanks.';
                $answer = 'You are welcome. Anything else about the internship — a deadline, your logbook, the report or the assessment — I am here.';
                $links = [self::link('Open my dashboard', 'dashboard'), self::link('Check my report', 'diva/report-check')];
            } elseif ($intent === 'capabilities') {
                $steps[] = 'Recognised a question about what I can do, so I listed the pages your role can open.';
                $answer = self::capabilitiesAnswer();
                $links = [self::link('Open my dashboard', 'dashboard'), self::link('Check a report draft', 'diva/report-check')];
            } elseif ($intent === 'page.help' && ($context !== null || self::resolvePage($working) !== null)) {
                $page = self::resolvePage($working) ?? $context;
                $steps[] = 'Matched the rule for help with a page, and looked up the ' . $page['title'] . ' page in the page guide.';
                $answer = 'You are looking at the ' . $page['title'] . " page.\n\n" . $page['body']
                    . "\n\nWhat you can do here: " . $page['ability']
                    . "\n\nBefore it is useful: " . $page['prereq'];
                $links = [self::link($page['label'], (string) $page['route'])];
                $next = self::nextActions();
                if ($next !== []) {
                    $links[] = self::link($next[0]['cta'], $next[0]['route'], $next[0]['params']);
                }
            } elseif ($intent === 'next') {
                $actions = self::nextActions();
                $steps[] = 'The implemented next-steps rule for a ' . self::roleLabel() . ' account returned ' . count($actions) . ' open item' . (count($actions) === 1 ? '' : 's') . '.';
                if ($actions === []) {
                    $answer = 'The next-steps check found no open items. Ask me about a page, a deadline, the assessment weights or the guidebook.';
                    $links = [self::link('Open my dashboard', 'dashboard')];
                } else {
                    $lines = [];
                    foreach ($actions as $action) {
                        $lines[] = '· ' . $action['title'] . ' — ' . $action['detail'];
                    }
                    $answer = "Here is what is waiting on you now:\n\n" . implode("\n", $lines);
                    foreach (array_slice($actions, 0, 2) as $action) {
                        $links[] = self::link($action['cta'], $action['route'], $action['params']);
                    }
                }
            } else {
                $facts = self::factsFor($intent);
                $sources = self::retrieve($working, 3);

                if ($facts !== null) {
                    $steps[] = 'Answered using the implemented ' . $intent . ' rule (' . (self::INTENT_LABELS[$intent] ?? $intent) . ') for a ' . self::roleLabel() . ' account.';
                    $answer = $facts['answer'];
                    $links = $facts['links'] ?? [];
                    $tier = 'rules';
                    // Attach only cited pages, not unrelated search hits as provenance.
                    $sources = self::citedSources($answer);
                    if ($sources !== []) {
                        $steps[] = 'The answer quotes the guidebook, so I attached page ' . implode(', ', array_map(static fn (array $s): int => (int) $s['page'], $sources)) . ' as the source.';
                    }
                } elseif (!self::supportedMatch($working, $sources)) {
                    $steps[] = $intent === null
                        ? 'No implemented intent rule matched this question.'
                        : 'The ' . $intent . ' intent matched, but its rule returned no answer for this account.';
                    $steps[] = 'The guidebook search did not provide sufficiently supported excerpts, so I cannot supply a supported answer to this question.';
                    $out = self::outOfScopeAnswer();
                    $answer = $out['answer'];
                    $links = $out['links'];
                    $sources = [];
                    $tier = 'rules';
                    $intent = null;
                } else {
                    $steps[] = ($intent === null ? 'No implemented intent rule matched' : 'The ' . $intent . ' rule returned no answer for this account')
                        . ', so I ranked the available guidebook pages by word overlap (TF-IDF).';
                    $top = $sources[0];
                    $steps[] = 'Best match: page ' . $top['page'] . ', which contains ' . (int) round(((float) ($top['coverage'] ?? 0)) * 100) . '% of your subject words'
                        . (isset($sources[1]) ? '; runner-up page ' . $sources[1]['page'] . '.' : '.');
                    $answer = self::guideAnswer($working, $sources);
                    $links = self::navLinks($working);
                    $tier = 'retrieval';
                }
            }
        }

        if ($sources !== [] && !in_array($intent, ['followup.why'], true)) {
            $links[] = self::link('Guidebook page ' . $sources[0]['page'], 'diva/source', ['page' => $sources[0]['page']]);
        }

        $confidence = self::confidence($tier, $sources);
        if ($tier === 'retrieval' && $confidence === 'low') {
            $answer .= "\n\nNote: the guidebook match is weak, so check the source before relying on this.";
        }
        $steps[] = self::confidenceReason($tier, $confidence, $sources);

        // Contract stages: slots are filled from the routed intent, evidence
        // names what the answer may be checked against, and verification stops
        // any quotation or figure that cannot be traced.
        $finalIntent = $intent ?? 'none';
        $slots = self::slots($working, $finalIntent, $context, $tier);
        $evidence = self::evidenceFor($finalIntent, $tier, $answer, $sources);
        $check = self::verify($answer, $evidence, $sources, $working, $slots);
        if (!$check['ok']) {
            $steps[] = 'Verification stopped the draft: ' . $check['reason'] . '.';
            $answer = self::refusalAnswer($check);
            $sources = [];
            $evidence = [['type' => 'rule', 'value' => 'verification.refused']];
            $confidence = 'low';
        }

        $local = self::localCompletion($question, $answer, $sources);
        if ($local !== null) {
            $localCheck = self::verify($local, $evidence, $sources, $working, $slots);
            if ($localCheck['ok']) {
                $answer = $local;
                $tier = 'local';
                $steps[] = 'The local model rephrased the draft. Match confidence is retained; the rephrased answer has not been independently verified.';
            } else {
                $steps[] = 'The local model changed a figure or quotation, so the verified draft was kept.';
            }
        }

        $next = $links[0] ?? null;

        return [
            'question' => $question,
            'resolved' => $working,
            'intent' => $intent ?? 'none',
            'tier' => $tier,
            'confidence' => $confidence,
            'context' => $context,
            'answer' => $answer,
            'sources' => $sources,
            'links' => self::links($links),
            'followups' => self::followUps($intent ?? 'none', $from),
            'reasoning' => $steps,
            'keywords' => $keywords,
            'carried' => $carried,
            'mode' => 'online',
            'slots' => $slots,
            'evidence' => $evidence,
            'next_action' => $next === null ? null : ['label' => $next['label'], 'route' => $next['route'], 'params' => $next['params']],
        ];
    }

    /** Small talk may be skipped, but an unsupported turn breaks topic continuity. */
    private static function topicalTurn(array $history): ?array
    {
        $history = array_slice($history, -6);
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $turn = $history[$i];
            $intent = (string) ($turn['intent'] ?? 'none');
            if (in_array($intent, ['greeting', 'gratitude', 'capabilities', 'empty', 'followup.why', 'followup.more'], true)) {
                continue;
            }
            if ($intent === 'clarify' || ($intent === 'none'
                && (empty($turn['sources']) || ($turn['tier'] ?? 'rules') === 'rules'))
                || (($turn['tier'] ?? 'rules') === 'retrieval' && empty($turn['sources']))) {
                return null;
            }
            return $turn;
        }
        return null;
    }

    private static function readsAsFollowUp(string $question, array $keywords): bool
    {
        if (preg_match(self::FOLLOW_UP, $question)) {
            return true;
        }
        return count($keywords) <= 2 && preg_match('/\b(it|that|this|they|them|those|these|one)\b/i', $question) === 1;
    }

    private static function resolveFollowUp(string $question, ?array $previous): ?array
    {
        $named = preg_match('/^(?:and|what about|how about|same for)\s+(?:(?:the|my)\s+)?(report|log\s*book|career\s*fair)[\s?.!]*$/i', $question, $match) === 1;
        $due = preg_match('/^(?:and\s+)?when is (?:it|that|this) due[\s?.!]*$/i', $question) === 1;
        if (!$named && !$due) {
            return null;
        }

        $priorIntent = (string) ($previous['intent'] ?? '');
        $family = str_starts_with($priorIntent, 'deadline.') ? 'deadline'
            : (str_starts_with($priorIntent, 'weighting') ? 'weighting' : null);
        $subject = $named ? preg_replace('/\s+/', '', strtolower($match[1])) : null;
        if ($due && $previous !== null) {
            $family = 'deadline';
            preg_match_all('/\b(?:career\s*fair(?:\s+report)?|log\s*book|report)\b/i', (string) $previous['question'], $subjects);
            $subjects = array_unique(array_map(static fn (string $value): string => str_starts_with(strtolower($value), 'career')
                ? 'careerfair' : (string) preg_replace('/\s+/', '', strtolower($value)), $subjects[0]));
            $subject = count($subjects) > 1 ? null : match ($priorIntent) {
                'deadline.logbook', 'logbook.status', 'weighting.logbook' => 'logbook',
                'deadline.report', 'report.status' => 'report',
                'deadline.career_fair' => 'careerfair',
                default => count($subjects) === 1 ? reset($subjects) : null,
            };
        }
        if ($previous === null || $family === null || $subject === null) {
            return ['question' => $question, 'intent' => 'clarify'];
        }

        $label = $subject === 'careerfair' ? 'Career Fair' : $subject;
        return [
            'question' => $family === 'deadline' ? 'When is the ' . $label . ' due?' : 'What is the ' . $label . ' weighting?',
            'intent' => $family === 'deadline' ? 'deadline.' . ($subject === 'careerfair' ? 'career_fair' : $subject)
                : ($subject === 'logbook' ? 'weighting.logbook' : 'weighting'),
        ];
    }

    /** @return list<string> */
    private static function keywords(string $question): array
    {
        return array_values(array_slice(array_unique(array_filter(
            self::tokens(self::fold($question)),
            static fn (string $token): bool => strlen($token) > 2
        )), 0, 8));
    }

    private static function roleLabel(): string
    {
        $role = (string) Auth::role();
        return $role === 'admin' ? 'administrator' : ($role === '' ? 'signed-in' : $role);
    }

    private static function whyAnswer(array $previous): string
    {
        $steps = $previous['reasoning'] ?? [];
        if ($steps === []) {
            return 'The recorded basis for that answer is unavailable. I cannot reconstruct how it was produced from the saved conversation.';
        }
        $lines = [];
        foreach (array_values($steps) as $n => $step) {
            $lines[] = ($n + 1) . '. ' . $step;
        }
        return 'Here is the recorded basis for my answer to “' . $previous['question'] . "”:\n\n" . implode("\n", $lines);
    }

    private static function deeperAnswer(string $question, array $sources): string
    {
        $parts = ['For “' . $question . '”, here are related excerpts, limited to the guidebook pages returned by this search:'];
        foreach ($sources as $source) {
            $parts[] = 'Page ' . $source['page'] . ":\n" . self::trimSnippet((string) $source['snippet'], 420);
        }
        return implode("\n\n", $parts);
    }

    private static function confidenceReason(string $tier, string $confidence, array $sources): string
    {
        if ($tier !== 'retrieval') {
            return 'Match confidence is ' . $confidence . ' for the selected response rule; this is not independent verification of the answer.';
        }
        $coverage = $sources === [] ? 0 : (int) round(((float) ($sources[0]['coverage'] ?? 0)) * 100);
        return 'Match confidence is ' . $confidence . ': the best page overlaps with ' . $coverage
            . '% of the subject words. Word overlap does not verify the answer; check the cited excerpts.';
    }

    /** @return array{page:int,text:string}|null */
    public static function page(int $page): ?array
    {
        foreach (self::corpus() as $entry) {
            if ($entry['page'] === $page) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * The guidebook's pages in order, each with a one-line preview taken from
     * its own text, for the page-by-page list on the guidebook screen.
     *
     * @return list<array{page:int,preview:string}>
     */
    public static function contents(): array
    {
        $out = [];
        foreach (self::corpus() as $entry) {
            $preview = '';
            foreach (preg_split('/\n+/', $entry['text']) ?: [] as $line) {
                $line = trim((string) preg_replace('/\s+/', ' ', $line));
                // A line taken from a two-column table keeps its page number.
                $line = trim((string) preg_replace('/\s+\d{1,3}$/', '', $line));
                if (mb_strlen($line) < 30) {
                    continue;
                }
                $preview = self::trimSnippet($line, 90);
                break;
            }
            $out[] = ['page' => $entry['page'], 'preview' => $preview];
        }
        return $out;
    }

    /** @return list<string> */
    public static function stopwords(): array
    {
        return self::STOP;
    }

    public static function localStatus(): string
    {
        return self::$localOutcome ?? 'disabled';
    }

    private static function intent(string $question): ?string
    {
        if (preg_match('/^page\s+(\d{1,2})\b/i', $question)) {
            return null;
        }
        // "What does the guidebook say about roles?" is a quotation request,
        // even though "roles" also names the accounts rule. Quoting wins.
        if (preg_match('/\bguidebook\b/i', $question)
            && preg_match('/\b(say|says|state|states|require|requires|cover|covers|according to)\b/i', $question)) {
            return null;
        }
        foreach (self::INTENTS as $key => $pattern) {
            if (preg_match($pattern, $question)) {
                return $key;
            }
        }
        return null;
    }

    private static function guideAnswer(string $question, array $sources): string
    {
        if (preg_match('/page\s+(\d{1,2})\b/i', $question, $match)) {
            $page = self::page((int) $match[1]);
            if ($page !== null) {
                return 'Here is the text of guidebook page ' . $page['page'] . ":\n\n" . self::trimSnippet($page['text'], 900);
            }
            return 'The guidebook has 25 pages, so I cannot open page ' . (int) $match[1] . ' for you.';
        }

        $top = $sources[0];
        $answer = 'Quoted from the internship guidebook, page ' . $top['page'] . ":\n\n" . $top['snippet'];
        if (isset($sources[1]) && (float) $sources[1]['score'] >= (float) $top['score'] * 0.55) {
            $answer .= "\n\nAlso quoted, page " . $sources[1]['page'] . ":\n" . self::trimSnippet($sources[1]['snippet'], 420);
        }
        return $answer;
    }

    private static function capabilitiesAnswer(): string
    {
        $groups = self::capabilities();
        if ($groups === []) {
            return 'This account has no pages assigned yet. Ask an administrator to check its role.';
        }

        $role = (string) Auth::role();
        $label = $role === 'admin' ? 'administrator' : $role;
        $lines = [];
        $count = 0;
        foreach ($groups as $group) {
            $titles = array_map(static fn (array $item): string => $item['title'], $group['items']);
            $count += count($titles);
            $lines[] = $group['title'] . ': ' . implode(', ', $titles);
        }

        return "I am DIVA — Digital Internship Virtual Assistant — the assistant built into Digital LEAP.\n\n"
            . "I answer in this order: first your role-scoped live records through built-in rules (your placement, logbook, deadlines, results); then direct quotations from the 25-page internship guidebook with the page number; and nothing else. There is no web access and no cloud model; an optional local model may only rephrase what I retrieved.\n\n"
            . 'As ' . ($label === 'administrator' ? 'an administrator' : 'a ' . $label) . ' you can open ' . $count . " pages, and I can explain their purpose and prerequisites:\n\n"
            . implode("\n", $lines)
            . "\n\nWhat I cannot do: change internship records, approve or return work, send messages, give a mark, or answer about anything outside this internship system."
            . "\n\nYour questions and answers are saved locally to your account. You can reopen or archive conversations; archiving does not delete their stored messages. Saved answers are snapshots, so ask again for current records."
            . "\n\nAsk me what is due, quote a guidebook topic, or ask \u{201C}what should I do next?\u{201D}.";
    }

    /** @return array{answer:string,links:list<array>} */
    private static function greetingAnswer(): array
    {
        $name = trim((string) Auth::name());
        $first = $name !== '' ? explode(' ', $name)[0] : '';
        $hello = 'Hello' . ($first !== '' ? ', ' . $first : '') . '.';

        $intro = " I'm DIVA, the assistant built into Digital LEAP.\n\n"
            . "I can read your live records, tell you your next step, explain assessment weights and deadlines, point you to any page your role can open, and check a report draft against the Form E criteria.\n\n"
            . "I save this conversation to your account, but cannot change internship records, approve or return work, send messages, or answer about anything outside this internship system.\n\n"
            . 'Ask me anything about the internship, or pick one of the suggestions below.';

        $links = [self::link('Open my dashboard', 'dashboard')];
        $actions = self::nextActions();
        if ($actions !== []) {
            $intro = " I'm DIVA, the assistant built into Digital LEAP.\n\n"
                . 'Right now the top of your list is: ' . $actions[0]['title'] . ' — ' . rtrim((string) $actions[0]['detail'], '.') . ".\n\n"
                . 'Ask me anything about the internship, or pick one of the suggestions below.';
            array_unshift($links, self::link($actions[0]['cta'], $actions[0]['route'], $actions[0]['params']));
        }

        return ['answer' => $hello . $intro, 'links' => self::links($links)];
    }

    /** @return array{answer:string,links:list<array>} */
    private static function outOfScopeAnswer(): array
    {
        return [
            'answer' => "I could not find a sufficiently supported answer to that question using the implemented Digital LEAP rules and the available guidebook excerpts.\n\n"
                . "If a passage only shares a word or two with your question, I will not quote it — an unrelated quotation is worse than an honest refusal.\n\n"
                . "I can use role-scoped internship records, built-in rules and the internship guidebook, but I cannot search the web or give career advice.\n\n"
                . 'Try a deadline, your placement, the logbook, the internship report, the assessment weights, or where a page lives.',
            'links' => self::links([
                self::link('Open my dashboard', 'dashboard'),
                self::link('Check my report', 'diva/report-check'),
            ]),
        ];
    }

    /** @return array{route:string,group:string,title:string,body:string,ability:string,prereq:string,label:string}|null */
    private static function resolvePage(string $question): ?array
    {
        $lower = mb_strtolower($question);
        $best = null;
        $bestLength = 0;
        foreach (self::pages() as $route => $page) {
            if (!self::canRoute($route)) {
                continue;
            }
            $title = mb_strtolower($page['title']);
            if (strlen($title) > $bestLength && str_contains($lower, $title)) {
                $best = ['route' => $route] + $page;
                $bestLength = strlen($title);
            }
        }
        return $best;
    }

    /** Word coverage and score estimate a match, not factual correctness. */
    private static function confidence(string $tier, array $sources): string
    {
        if ($tier !== 'retrieval') {
            return 'high';
        }
        if ($sources === []) {
            return 'low';
        }
        $score = (float) $sources[0]['score'];
        $coverage = (float) ($sources[0]['coverage'] ?? 0.0);
        if ($coverage >= 0.75 && $score >= 6.0) {
            return 'high';
        }
        if ($coverage >= 0.5) {
            return 'medium';
        }
        return 'low';
    }

    /** Count content words present on at least two pages; zero rejects retrieval, not the topic itself. */
    private static function corpusReach(string $question): int
    {
        $pages = self::corpus();
        $tokens = array_values(array_unique(array_filter(
            self::tokens(self::fold($question)),
            static fn (string $token): bool => strlen($token) > 2
        )));
        if ($pages === [] || $tokens === []) {
            return 0;
        }

        $reach = 0;
        foreach ($tokens as $token) {
            $hits = 0;
            foreach ($pages as $page) {
                if (str_contains(self::fold($page['text']), $token)) {
                    $hits++;
                    if ($hits >= 2) {
                        $reach++;
                        break;
                    }
                }
            }
        }
        return $reach;
    }

    /**
     * A guidebook quote needs real overlap, not one or two common words that
     * happen to appear somewhere. Below this bar DIVA refuses instead of
     * quoting an unrelated passage.
     */
    private static function supportedMatch(string $question, array $sources): bool
    {
        if ($sources === [] || self::corpusReach($question) < 2) {
            return false;
        }
        return (float) ($sources[0]['score'] ?? 0.0) >= 6.0;
    }

    /**
     * @return list<array{page:int,snippet:string,score:float,coverage:float}>
     */
    private static function retrieve(string $question, int $limit): array
    {
        $pages = self::corpus();
        if ($pages === []) {
            return [];
        }

        if (preg_match('/page\s+(\d{1,2})\b/i', $question, $match)) {
            $page = self::page((int) $match[1]);
            if ($page !== null) {
                return [['page' => $page['page'], 'snippet' => self::trimSnippet($page['text'], 700), 'score' => 99.0, 'coverage' => 1.0]];
            }
        }

        $base = array_values(array_unique(array_filter(
            self::tokens(self::fold($question)),
            static fn (string $token): bool => strlen($token) > 2
        )));
        if ($base === []) {
            return [];
        }
        // Query expansion: a synonym contributes at half weight, so the words
        // the question actually used still decide the ranking.
        $tokens = self::expandTokens($base);

        $df = [];
        foreach ($pages as $page) {
            $lower = self::fold($page['text']);
            foreach ($tokens as $token) {
                if (str_contains($lower, $token)) {
                    $df[$token] = ($df[$token] ?? 0) + 1;
                }
            }
        }

        $total = count($pages);
        $idf = [];
        foreach ($tokens as $token) {
            $idf[$token] = log(1 + $total / (1 + ($df[$token] ?? 0)));
        }

        $ranked = [];
        foreach ($pages as $page) {
            $score = 0.0;
            $best = '';
            $bestScore = 0.0;
            $bestIndex = 0;
            $paragraphs = [];
            foreach (preg_split('/\n\s*\n/', $page['text']) ?: [] as $paragraph) {
                $paragraph = trim($paragraph);
                if (strlen($paragraph) < 40) {
                    continue;
                }
                $paragraphs[] = $paragraph;
            }
            foreach ($paragraphs as $index => $paragraph) {
                $lower = self::fold($paragraph);
                $pScore = 0.0;
                foreach ($tokens as $token) {
                    $count = substr_count($lower, $token);
                    if ($count > 0) {
                        $weight = in_array($token, $base, true) ? 1.0 : 0.5;
                        $pScore += (1 + log($count)) * $idf[$token] * $weight;
                    }
                }
                if ($pScore > $bestScore) {
                    $bestScore = $pScore;
                    $best = $paragraph;
                    $bestIndex = $index;
                }
                $score += $pScore;
            }
            if ($score > 0) {
                $pageLower = self::fold($page['text']);
                $matched = 0;
                foreach ($base as $token) {
                    if (str_contains($pageLower, $token)) {
                        $matched++;
                    }
                }
                // Quote from the winning paragraph onward in the page's own
                // text, so the excerpt stays a contiguous passage and the
                // verifier can find it word for word.
                $excerpt = $page['text'];
                if ($best !== '') {
                    $offset = mb_strpos($page['text'], $best);
                    if ($offset !== false) {
                        $excerpt = mb_substr($page['text'], $offset);
                    }
                }
                $ranked[] = [
                    'page' => $page['page'],
                    'snippet' => self::trimSnippet($excerpt, 700),
                    'score' => round($score, 3),
                    'coverage' => round($matched / count($base), 3),
                ];
            }
        }

        usort($ranked, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($ranked, 0, $limit);
    }

    /**
     * Pages an answer names in its own text, e.g. "pages 24-25".
     *
     * @return list<array{page:int,snippet:string,score:float}>
     */
    private static function citedSources(string $answer): array
    {
        if (!preg_match_all('/pages?\s+(\d{1,2})(?:\s*[–-]\s*(\d{1,2}))?/iu', $answer, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $out = [];
        foreach ($matches as $match) {
            $first = (int) $match[1];
            $last = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : $first;
            if ($last < $first || $last - $first > 3) {
                $last = $first;
            }
            for ($number = $first; $number <= $last; $number++) {
                $page = self::page($number);
                if ($page === null || isset($out[$number])) {
                    continue;
                }
                $out[$number] = ['page' => $number, 'snippet' => self::trimSnippet($page['text'], 300), 'score' => 90.0];
            }
        }

        return array_values($out);
    }

    /** @return list<string> */
    private static function tokens(string $text): array
    {
        preg_match_all('/[a-z0-9]{2,}/i', mb_strtolower($text), $matches);
        $out = [];
        foreach ($matches[0] as $token) {
            if (!in_array($token, self::STOP, true)) {
                $out[] = $token;
            }
        }
        return $out;
    }

    /** The guidebook writes "Log Book" as often as "logbook"; search treats them as one word. */
    private static function fold(string $text): string
    {
        return (string) preg_replace('/\blog[\s-]+book\b/i', 'logbook', mb_strtolower($text));
    }

    /**
     * The winning paragraph plus the one after it, so a heading keeps the
     * list that answers the question underneath it.
     */
    private static function contextSnippet(array $paragraphs, int $index, int $limit): string
    {
        $parts = [];
        $length = 0;
        $total = count($paragraphs);
        for ($i = $index; $i < $total; $i++) {
            $piece = trim((string) $paragraphs[$i]);
            if ($piece === '') {
                continue;
            }
            if ($parts !== [] && $length + strlen($piece) > $limit) {
                break;
            }
            $parts[] = $piece;
            $length += strlen($piece) + 2;
            if ($length >= (int) ($limit * 0.6)) {
                break;
            }
        }
        return $parts === [] ? '' : implode("\n\n", $parts);
    }

    private static function trimSnippet(string $text, int $limit): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        $cut = mb_substr($text, 0, $limit);
        $space = mb_strrpos($cut, ' ');
        return rtrim($space !== false ? mb_substr($cut, 0, $space) : $cut, " ,.;:") . '…';
    }

    /** @return list<array{page:int,text:string}> */
    private static function corpus(): array
    {
        if (self::$corpus !== null) {
            return self::$corpus;
        }

        $path = BASE_PATH . '/storage/knowledge/guidebook.txt';
        if (!is_file($path)) {
            return self::$corpus = [];
        }

        $raw = (string) file_get_contents($path);
        $parts = preg_split('/^\[\[page:(\d+)\]\]$/m', $raw, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $pages = [];
        for ($i = 1; $i + 1 < count($parts); $i += 2) {
            $number = (int) $parts[$i];
            $text = trim($parts[$i + 1]);
            $text = (string) preg_replace('/^.*Internship GuideBook -- Limkokwing.*$/m', '', $text);
            $text = trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
            if ($text !== '') {
                $pages[] = ['page' => $number, 'text' => $text];
            }
        }
        return self::$corpus = $pages;
    }

    private static function navLinks(string $question): array
    {
        $lower = mb_strtolower($question);
        $role = Auth::role();
        $map = [
            'logbook' => ['student' => ['student/logbook', 'Open my logbook'], 'mentor' => ['mentor/logbook', 'Open logbook review'], 'supervisor' => ['supervisor/queue', 'Open the review queue'], 'coordinator' => ['coordinator/placements', 'Open placements']],
            'report' => ['student' => ['student/report', 'Open my internship report'], 'mentor' => ['mentor/reports', 'Open report assessment'], 'coordinator' => ['coordinator/grades', 'Open the grade sheet']],
            'career fair' => ['student' => ['student/career-fair', 'Open Career Fair 2026']],
            'result' => ['student' => ['student/results', 'Open my results'], 'coordinator' => ['coordinator/grades', 'Open grades'], 'management' => ['management/kpis', 'Open quality indicators']],
            'message' => ['student' => ['messages', 'Open messages'], 'mentor' => ['messages', 'Open messages'], 'coordinator' => ['messages', 'Open messages']],
            'placement' => ['student' => ['student/placement', 'Open my placement'], 'coordinator' => ['coordinator/placements', 'Open placements'], 'mentor' => ['mentor/students', 'Open my students']],
            'visit' => ['mentor' => ['mentor/visits', 'Open site visits'], 'coordinator' => ['coordinator/visits', 'Open site visits'], 'student' => ['student/placement', 'Open my placement']],
            'feedback' => ['student' => ['dashboard', 'Open the dashboard'], 'mentor' => ['dashboard', 'Open the dashboard'], 'supervisor' => ['dashboard', 'Open the dashboard'], 'coordinator' => ['dashboard', 'Open the dashboard'], 'management' => ['dashboard', 'Open the dashboard'], 'admin' => ['dashboard', 'Open the dashboard']],
            'profile' => ['student' => ['profile', 'Open my profile'], 'mentor' => ['profile', 'Open my profile'], 'supervisor' => ['profile', 'Open my profile'], 'coordinator' => ['profile', 'Open my profile'], 'management' => ['profile', 'Open my profile'], 'admin' => ['profile', 'Open my profile']],
            'notification' => ['student' => ['notifications', 'Open notifications'], 'mentor' => ['notifications', 'Open notifications'], 'supervisor' => ['notifications', 'Open notifications'], 'coordinator' => ['notifications', 'Open notifications'], 'management' => ['notifications', 'Open notifications'], 'admin' => ['notifications', 'Open notifications']],
        ];

        $links = [];
        foreach ($map as $needle => $byRole) {
            if (!str_contains($lower, $needle)) {
                continue;
            }
            $target = $byRole[$role] ?? null;
            if ($target !== null) {
                $links[] = self::link($target[1], $target[0]);
            }
        }

        $links[] = self::link('Check my report', 'diva/report-check');
        return self::links($links);
    }

    private static function link(string $label, string $route, array $params = []): ?array
    {
        return self::canRoute($route) ? ['label' => $label, 'route' => $route, 'params' => $params] : null;
    }

    private static function links(array $links): array
    {
        $seen = [];
        $out = [];
        foreach ($links as $link) {
            if ($link === null || isset($seen[$link['route'] . '|' . json_encode($link['params'])])) {
                continue;
            }
            $seen[$link['route'] . '|' . json_encode($link['params'])] = true;
            $out[] = $link;
        }
        return array_slice($out, 0, 4);
    }

    private static function canRoute(string $route): bool
    {
        if (self::$routeTable === null) {
            self::$routeTable = require BASE_PATH . '/app/routes.php';
        }
        $entry = self::$routeTable['GET ' . $route] ?? null;
        if ($entry === null) {
            return false;
        }
        $permission = $entry[2] ?? null;
        if ($permission === null) {
            return true;
        }
        if ($permission === 'auth') {
            return Auth::check();
        }
        return Auth::can($permission);
    }

    private static function localCompletion(string $question, string $base, array $sources): ?string
    {
        $config = $GLOBALS['config']['diva']['local_model'] ?? null;
        if (!is_array($config) || empty($config['enabled'])) {
            self::$localOutcome = 'disabled';
            return null;
        }
        if (!function_exists('curl_init')) {
            self::$localOutcome = 'unavailable';
            return null;
        }

        $endpoint = rtrim((string) ($config['endpoint'] ?? ''), '/');
        $host = parse_url($endpoint, PHP_URL_HOST);
        if ($endpoint === '' || !in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            self::$localOutcome = 'refused';
            return null;
        }

        $excerpt = '';
        foreach ($sources as $source) {
            $excerpt .= "[page {$source['page']}] " . $source['snippet'] . "\n";
        }

        $prompt = "You are DIVA, an offline assistant for a university internship system. Answer briefly in plain text using only the context below. Do not invent records and do not give a mark.\n\nContext:\n"
            . ($excerpt !== '' ? $excerpt : '(no guidebook excerpt)') . "\nDraft answer:\n" . $base . "\n\nQuestion: " . $question . "\nAnswer:";

        $timeout = max(100, (int) ($config['timeout_ms'] ?? 400));
        $ch = curl_init($endpoint . '/api/generate');
        if ($ch === false) {
            self::$localOutcome = 'unavailable';
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => (string) ($config['model'] ?? 'llama3.2'),
                'prompt' => $prompt,
                'stream' => false,
                'options' => ['temperature' => 0.2, 'num_predict' => 220],
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => $timeout,
            CURLOPT_TIMEOUT_MS => $timeout,
            CURLOPT_NOSIGNAL => true,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if (!is_string($response) || $status !== 200) {
            self::$localOutcome = 'unreachable';
            return null;
        }
        $decoded = json_decode($response, true);
        $text = is_array($decoded) ? trim((string) ($decoded['response'] ?? '')) : '';
        if ($text === '') {
            self::$localOutcome = 'empty';
            return null;
        }
        self::$localOutcome = 'local';
        return $text;
    }
}
