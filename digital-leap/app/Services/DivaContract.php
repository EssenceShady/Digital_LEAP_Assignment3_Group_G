<?php
declare(strict_types=1);

namespace Leap\Services;

use Leap\Core\Auth;
use Leap\Core\Database;

/**
 * The DIVA answer contract and the stages that produce it.
 *
 * One shape is returned by both engines:
 *   {mode, intent, slots, evidence[{type,value,page?}], confidence, answer, next_action}
 *
 * The PHP engine runs the stages here; assets/js/diva-offline.js runs the same
 * stages over the shipped index when the device has no connection. Verification
 * is the last gate: a quotation must exist in the guidebook text and every
 * figure must appear in the records that were read, the guidebook or the
 * user's own question. A draft that fails is replaced by a safe refusal.
 */
trait DivaContract
{
    /**
     * Query expansion. The guidebook calls the same thing a "log book", a
     * "diary" and a "weekly record"; a question written in one word should
     * still reach the other. Multi-word variants are reduced to single
     * tokens, which is what the index stores.
     *
     * @return array<string,list<string>>
     */
    private static function synonymTable(): array
    {
        return [
            'logbook' => ['diary', 'journal', 'weekly', 'log'],
            'report' => ['writeup', 'document', 'reporting'],
            'supervisor' => ['mentor', 'industry', 'host'],
            'deadline' => ['due', 'date', 'submit', 'submission'],
            'weight' => ['weighting', 'marks', 'mark', 'grade', 'score', 'percentage'],
            'evaluate' => ['evaluation', 'assessment', 'assess', 'appraisal'],
            'placement' => ['attachment', 'organisation', 'organization', 'company'],
            'apply' => ['application', 'cv', 'resume', 'preference'],
            'visit' => ['site', 'organisation'],
            'policy' => ['rule', 'regulation', 'guideline', 'conduct'],
            'duration' => ['weeks', 'long', 'period', 'months'],
            'careerfair' => ['fair', 'exhibition', 'participation'],
        ];
    }

    /** @return list<array{type:string,value:string,page?:int}> */
    public static function contract(string $question, string $from = '', array $history = []): array
    {
        $result = self::ask($question, $from, $history);
        return [
            'mode' => 'online',
            'intent' => (string) $result['intent'],
            'slots' => $result['slots'],
            'evidence' => $result['evidence'],
            'confidence' => (string) $result['confidence'],
            'answer' => (string) $result['answer'],
            'next_action' => $result['next_action'],
        ];
    }

    /**
     * Router + slot filler. Categories are the pipeline's own vocabulary:
     * records, guidebook, assessment, howto, self, memory, clarify, out_of_scope.
     *
     * @return array{category:string,role:string,subject:?string,component:?string,week:?int,form:?string}
     */
    private static function slots(string $question, string $intent, ?array $context, string $tier = 'rules'): array
    {
        $lower = mb_strtolower($question);
        $subject = null;
        foreach ([
            'career fair' => 'career_fair',
            'log book' => 'logbook',
            'logbook' => 'logbook',
            'report' => 'report',
            'placement' => 'placement',
            'result' => 'results',
            'mark' => 'results',
            'account' => 'accounts',
            'visit' => 'visit',
            'evaluation' => 'evaluation',
            'application' => 'apply',
        ] as $needle => $value) {
            if (str_contains($lower, $needle)) {
                $subject = $value;
                break;
            }
        }
        if ($subject === null) {
            foreach (['deadline.' => 'report', 'weighting.logbook' => 'logbook', 'logbook.status' => 'logbook', 'report.status' => 'report', 'results' => 'results', 'queue' => 'queue'] as $prefix => $value) {
                if (str_starts_with($intent, $prefix)) {
                    $subject = $value;
                    break;
                }
            }
        }

        $week = preg_match('/\bweek\s*(\d{1,2})\b/i', $lower, $match) === 1 ? (int) $match[1] : null;

        $form = null;
        if (preg_match('/\bform\s*(?:e|ix)\b/i', $lower)) {
            $form = 'E';
        } elseif (preg_match('/\bform\s*d\s*1\b|\bform\s*d-1\b|\bd1\b/i', $lower)) {
            $form = 'D1';
        } elseif (preg_match('/\bform\s*d\s*2\b|\bform\s*d-2\b|\bd2\b/i', $lower)) {
            $form = 'D2';
        } elseif (preg_match('/\bform\s*d\b/i', $lower)) {
            $form = 'D';
        } elseif (preg_match('/\bform\s*(a2|a3|b|c)\b/i', $lower, $match)) {
            $form = strtoupper($match[1]);
        }

        $component = null;
        if (preg_match('/\b(log\s*book|diary|weekly record)\b/i', $lower)) {
            $component = 'logbook';
        } elseif (preg_match('/\b(form\s*d|supervisor evaluation)\b/i', $lower)) {
            $component = 'form_d';
        } elseif (preg_match('/\b(self[- ]evaluation)\b/i', $lower)) {
            $component = 'self_evaluation';
        } elseif (preg_match('/\bcareer\s*fair\b/i', $lower)) {
            $component = 'career_fair';
        } elseif (preg_match('/\breport\b/i', $lower)) {
            $component = 'report';
        }

        return [
            'category' => self::category($intent, $context, $tier),
            'role' => (string) Auth::role(),
            'subject' => $subject,
            'component' => $component,
            'week' => $week,
            'form' => $form,
        ];
    }

    private static function category(string $intent, ?array $context, string $tier): string
    {
        if (in_array($intent, ['greeting', 'gratitude', 'capabilities', 'critique', 'page.help', 'next', 'empty'], true)) {
            return 'self';
        }
        if ($intent === 'clarify') {
            return 'clarify';
        }
        if (str_starts_with($intent, 'followup.')) {
            return 'memory';
        }
        if (in_array($intent, ['weighting', 'weighting.logbook', 'evaluation', 'internship.duration', 'internship.type'], true)) {
            return 'assessment';
        }
        if (in_array($intent, ['apply', 'allocation', 'deadline.career_fair', 'deadline.report', 'deadline.logbook', 'queue', 'overview', 'accounts', 'results', 'report.status', 'logbook.status', 'progress', 'placement'], true)) {
            return 'records';
        }
        if ($intent === 'none') {
            return $tier === 'retrieval' || $context !== null ? 'guidebook' : 'out_of_scope';
        }
        return 'guidebook';
    }

    /**
     * Evidence is what the answer would be checked against: the quoted pages,
     * the cited pages, the marking model and the scoped record values that
     * appear in the answer.
     *
     * @return list<array{type:string,value:string,page?:int}>
     */
    private static function evidenceFor(string $intent, string $tier, string $answer, array $sources): array
    {
        $evidence = [];
        $quoting = $tier === 'retrieval' || $intent === 'followup.more';

        foreach ($sources as $source) {
            $page = (int) ($source['page'] ?? 0);
            if ($page <= 0) {
                continue;
            }
            if ($quoting) {
                $evidence[] = ['type' => 'guidebook_quote', 'value' => (string) ($source['snippet'] ?? ''), 'page' => $page];
            } else {
                $evidence[] = ['type' => 'guidebook_citation', 'value' => 'Guidebook page ' . $page, 'page' => $page];
            }
        }

        if (in_array($intent, ['weighting', 'weighting.logbook', 'evaluation', 'internship.duration', 'internship.type'], true)) {
            $a = self::ASSESSMENT;
            $evidence[] = [
                'type' => 'figure',
                'value' => 'Assessment model: practical ' . $a['practical'] . ' (logbook ' . $a['logbook']
                    . ', Form D ' . $a['form_d'] . ', self-evaluation ' . $a['self_evaluation'] . ') + report '
                    . $a['report'] . ' + career fair ' . $a['career_fair'] . ' = ' . $a['total'] . ' marks',
                'page' => 24,
            ];
        }

        if ($tier === 'rules' || $tier === 'local') {
            $mapped = self::recordKeys($intent);
            foreach (self::recordCapture($intent) as $label => $value) {
                if (self::sharesNumber($answer, $value) || in_array($label, $mapped, true)) {
                    $evidence[] = ['type' => 'record', 'value' => $label . ': ' . $value];
                }
            }
        }

        if ($evidence === []) {
            $evidence[] = ['type' => 'rule', 'value' => $intent === 'none' ? 'no supported rule or passage' : 'rule ' . $intent];
        }

        return $evidence;
    }

    private static function sharesNumber(string $answer, string $value): bool
    {
        foreach (self::numbers($value) as $number) {
            if (preg_match('/\b' . preg_quote($number, '/') . '\b/', $answer) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Records that are the basis of an intent even when the answer quotes no
     * figure — "no result published" is still a statement about that record.
     *
     * @return list<string>
     */
    private static function recordKeys(string $intent): array
    {
        return match ($intent) {
            'results' => ['result'],
            'report.status' => ['report'],
            'progress' => ['progress'],
            'logbook.status' => ['logbook', 'logbook weeks', 'logbook rework week', 'next week'],
            'deadline.logbook' => ['logbook', 'next week'],
            'deadline.career_fair' => ['career fair deadline', 'career fair file'],
            'apply' => ['cv', 'applications'],
            'placement' => ['placement'],
            'queue' => ['logbook queue', 'reports queue', 'site visits scheduled', 'evaluations draft', 'placement proposals', 'grade sheets pending', 'students'],
            'overview' => ['placements', 'placements active', 'placements completed', 'placements proposed', 'marks published'],
            'accounts' => ['users', 'audit today', 'failed sign-ins today'],
            default => [],
        };
    }

    /**
     * The captured records a rules answer may quote. One scoped read per
     * session; the verifier uses the same numbers, so a figure that is not in
     * here and not in the guidebook stops the answer.
     *
     * @return array<string,string>
     */
    private static function recordCapture(string $intent): array
    {
        $uid = (int) Auth::id();
        if (self::$capture !== null && self::$captureFor === $uid) {
            return self::$capture;
        }
        self::$captureFor = $uid;
        $out = [];
        $role = (string) Auth::role();
        try {
            $out['workspace pages'] = (string) array_sum(array_map(
                static fn (array $group): int => count($group['items']),
                self::capabilities()
            ));
            if ($role === 'student' && ($sid = self::studentId()) !== null) {
                $placement = self::studentPlacement();
                if ($placement !== null) {
                    $out['placement'] = $placement['org_name'] . ', ' . (int) $placement['total_weeks']
                        . ' weeks, ' . \fmt_date((string) $placement['start_date']) . ' to ' . \fmt_date((string) $placement['end_date'])
                        . ', ' . $placement['academic_year'];
                    $row = Database::one(
                        "SELECT COUNT(*) AS total,
                                SUM(status IN ('approved','mentor_reviewed')) AS approved,
                                SUM(status = 'returned') AS returned,
                                SUM(status = 'submitted') AS with_mentor,
                                MAX(CASE WHEN status = 'returned' THEN week_no END) AS rework_week
                         FROM logbook_entry WHERE placement_id = ?",
                        [(int) $placement['placement_id']]
                    );
                    $out['logbook'] = (int) ($row['total'] ?? 0) . ' entries, ' . (int) ($row['approved'] ?? 0)
                        . ' approved, ' . (int) ($row['with_mentor'] ?? 0) . ' waiting, ' . (int) ($row['returned'] ?? 0) . ' returned';
                    if ($row['rework_week'] !== null) {
                        $out['logbook rework week'] = (string) (int) $row['rework_week'];
                    }
                    $weeks = Database::all('SELECT week_no FROM logbook_entry WHERE placement_id = ? ORDER BY week_no', [(int) $placement['placement_id']]);
                    $out['logbook weeks'] = implode(' ', array_map(static fn (array $entry): int => (int) $entry['week_no'], $weeks));
                    $taken = array_map(static fn (array $entry): int => (int) $entry['week_no'], $weeks);
                    $total = (int) $placement['total_weeks'];
                    for ($week = 1; $week <= $total; $week++) {
                        if (!in_array($week, $taken, true)) {
                            $from = date('Y-m-d', strtotime((string) $placement['start_date'] . ' +' . ($week - 1) * 7 . ' days'));
                            $out['next week'] = $week . ' from ' . \fmt_date($from) . ' to ' . \fmt_date(date('Y-m-d', strtotime($from . ' +6 days')));
                            break;
                        }
                    }
                } else {
                    $out['placement'] = 'no placement record yet';
                }
                $report = Database::one(
                    'SELECT r.status, r.submitted_at, r.assessed_at FROM internship_report r
                     JOIN placement p ON p.placement_id = r.placement_id
                     WHERE p.student_id = ? ORDER BY r.submitted_at DESC LIMIT 1',
                    [$sid]
                );
                if ($report !== null) {
                    $out['report'] = (string) $report['status'] . ' ' . \fmt_date((string) ($report['assessed_at'] ?? $report['submitted_at']));
                } else {
                    $out['report'] = 'no internship report on file';
                }
                $mark = Database::one(
                    "SELECT m.weighted_total, m.published_at, g.grade_letter, p.academic_year FROM mark m
                     JOIN placement p ON p.placement_id = m.placement_id
                     LEFT JOIN grade_scale g ON g.grade_id = m.grade_id
                     WHERE p.student_id = ? AND m.status = 'published' ORDER BY p.academic_year DESC LIMIT 1",
                    [$sid]
                );
                if ($mark !== null) {
                    $out['result'] = 'grade ' . $mark['grade_letter'] . ', ' . (float) $mark['weighted_total'] . ' marks, released ' . \fmt_date((string) $mark['published_at']) . ', ' . $mark['academic_year'];
                } else {
                    $out['result'] = 'no published result';
                }
                $progress = \internship_progress($sid);
                $out['progress'] = (int) $progress['percent'] . ' percent complete';
                $out['career fair deadline'] = date('l j F Y, g:i a', strtotime(\career_fair_deadline()));
                $placement = $placement ?? self::studentPlacement();
                if ($placement !== null) {
                    $doc = Database::one(
                        "SELECT original_name, uploaded_at FROM document WHERE placement_id = ? AND doc_type = 'career_fair_report' ORDER BY uploaded_at DESC LIMIT 1",
                        [(int) $placement['placement_id']]
                    );
                    if ($doc !== null) {
                        $out['career fair file'] = (string) $doc['original_name'] . ' uploaded ' . \fmt_date((string) $doc['uploaded_at']);
                    }
                }
                $cv = Database::one("SELECT original_name, uploaded_at FROM document WHERE student_id = ? AND doc_type = 'cv' ORDER BY uploaded_at DESC LIMIT 1", [$sid]);
                if ($cv !== null) {
                    $out['cv'] = (string) $cv['original_name'] . ' uploaded ' . \fmt_date((string) $cv['uploaded_at']);
                }
                $prefs = Database::all('SELECT preference_rank, status FROM application WHERE student_id = ? AND academic_year = ?', [$sid, self::currentYear()]);
                $out['applications'] = count($prefs) . ' ranked'
                    . ($prefs === [] ? '' : ', ranks ' . implode(' ', array_map(static fn (array $pref): int => (int) $pref['preference_rank'], $prefs)));
            } elseif (in_array($role, ['mentor', 'supervisor', 'coordinator', 'management', 'admin', 'aqa'], true)) {                $out['placements'] = (string) Database::val("SELECT COUNT(*) FROM placement", []);
                $out['logbook queue'] = (string) Database::val(
                    $role === 'mentor'
                        ? "SELECT COUNT(*) FROM logbook_entry e JOIN placement p ON p.placement_id = e.placement_id WHERE p.mentor_id = ? AND e.status = 'submitted'"
                        : ($role === 'supervisor'
                            ? "SELECT COUNT(*) FROM logbook_entry e JOIN placement p ON p.placement_id = e.placement_id WHERE p.supervisor_id = ? AND e.status = 'submitted'"
                            : "SELECT COUNT(*) FROM logbook_entry WHERE status = 'submitted'"),
                    in_array($role, ['mentor', 'supervisor'], true) ? [(int) Auth::id()] : []
                );
                $out['reports queue'] = (string) Database::val(
                    $role === 'mentor'
                        ? "SELECT COUNT(*) FROM internship_report r JOIN placement p ON p.placement_id = r.placement_id WHERE p.mentor_id = ? AND r.status = 'submitted'"
                        : "SELECT COUNT(*) FROM internship_report WHERE status = 'submitted'",
                    $role === 'mentor' ? [(int) Auth::id()] : []
                );
                foreach (Database::all('SELECT status, COUNT(*) AS total FROM placement GROUP BY status') as $row) {
                    $out['placements ' . $row['status']] = (string) (int) $row['total'];
                }
                if ($role === 'mentor') {
                    $out['site visits scheduled'] = (string) Database::val(
                        "SELECT COUNT(*) FROM site_visit v JOIN placement p ON p.placement_id = v.placement_id WHERE p.mentor_id = ? AND v.status = 'scheduled'",
                        [(int) Auth::id()]
                    );
                    $out['students'] = (string) Database::val(
                        "SELECT COUNT(*) FROM placement p WHERE p.mentor_id = ? AND p.status IN ('approved','active')",
                        [(int) Auth::id()]
                    );
                }
                if ($role === 'supervisor') {
                    $out['evaluations draft'] = (string) Database::val(
                        "SELECT COUNT(*) FROM evaluation ev JOIN placement p ON p.placement_id = ev.placement_id WHERE p.supervisor_id = ? AND ev.evaluation_type = 'supervisor' AND ev.status = 'draft'",
                        [(int) Auth::id()]
                    );
                }
                if ($role === 'coordinator') {
                    $out['placement proposals'] = (string) Database::val("SELECT COUNT(*) FROM placement WHERE status = 'proposed'", []);
                    $out['grade sheets pending'] = (string) Database::val("SELECT COUNT(*) FROM mark WHERE status IN ('draft','moderated')", []);
                }
                $out['marks published'] = (string) Database::val("SELECT COUNT(*) FROM mark WHERE status = 'published'", []);
                if ($role === 'admin') {
                    $out['users'] = (string) Database::val('SELECT COUNT(*) FROM users', []);
                    $out['audit today'] = (string) Database::val('SELECT COUNT(*) FROM audit_log WHERE created_at >= ?', [date('Y-m-d 00:00:00')]);
                    $out['failed sign-ins today'] = (string) Database::val("SELECT COUNT(*) FROM audit_log WHERE action = 'login.failed' AND created_at >= ?", [date('Y-m-d 00:00:00')]);
                }
            }
        } catch (\Throwable $error) {
            // Verification will refuse anything it cannot trace.
        }
        return self::$capture = $out;
    }

    /**
     * @param list<array{type:string,value:string,page?:int}> $evidence
     * @return array{ok:bool,reason:string,unverified:list<string>}
     */
    private static function verify(string $answer, array $evidence, array $sources, string $question, array $slots): array
    {
        foreach ($evidence as $item) {
            if (($item['type'] ?? '') !== 'guidebook_quote') {
                continue;
            }
            $page = self::page((int) ($item['page'] ?? 0));
            if ($page === null) {
                return ['ok' => false, 'reason' => 'a quotation cites a page the guidebook does not have', 'unverified' => []];
            }
            // Display snippets may be shortened with an ellipsis; the words
            // before it must still appear on the page in that order.
            $quote = (string) preg_replace('/\s*…\s*$/u', '', self::normalise((string) $item['value']));
            if ($quote !== '' && !str_contains(self::normalise($page['text']), $quote)) {
                return ['ok' => false, 'reason' => 'a quotation is not found word for word on its page', 'unverified' => []];
            }
        }

        $allowed = [];
        foreach ($evidence as $item) {
            self::collectNumbers($allowed, (string) $item['value']);
            if (isset($item['page'])) {
                self::collectNumbers($allowed, (string) $item['page']);
            }
        }
        foreach ($sources as $source) {
            $page = self::page((int) ($source['page'] ?? 0));
            if ($page !== null) {
                self::collectNumbers($allowed, $page['text']);
            }
        }
        foreach (self::recordCapture('') as $value) {
            self::collectNumbers($allowed, $value);
        }
        self::collectNumbers($allowed, $question);
        self::collectNumbers($allowed, (string) json_encode($slots));
        foreach (self::ASSESSMENT as $value) {
            self::collectNumbers($allowed, (string) $value);
        }
        self::collectNumbers($allowed, (string) count(self::corpus()));

        $unverified = [];
        foreach (self::numbers(self::stripDates($answer)) as $number) {
            if (!isset($allowed[$number])) {
                $unverified[$number] = true;
            }
        }
        if ($unverified !== []) {
            return ['ok' => false, 'reason' => 'a figure could not be traced to the records or the guidebook', 'unverified' => array_keys($unverified)];
        }

        return ['ok' => true, 'reason' => '', 'unverified' => []];
    }

    /** @param array{ok:bool,reason:string,unverified:list<string>} $check */
    private static function refusalAnswer(array $check): string
    {
        $detail = $check['unverified'] === []
            ? ''
            : ' The figures ' . implode(', ', $check['unverified']) . ' could not be traced.';
        return "I stopped short of answering: " . $check['reason'] . '.' . $detail . "\n\n"
            . "Every answer I give must be checkable against the guidebook or your own records, so I will not present a draft that fails that check.\n\n"
            . 'Try rephrasing the question, or ask about a deadline, the logbook, the internship report or the assessment weights.';
    }

    private static function normalise(string $text): string
    {
        $text = mb_strtolower($text);
        $text = (string) preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /** Dates carry numbers that come from a formatted record, not a claim. */
    private static function stripDates(string $text): string
    {
        $text = (string) preg_replace('/\b\d{4}-\d{2}-\d{2}\b/', ' ', $text);
        $text = (string) preg_replace('/\b\d{1,2}:\d{2}\b/', ' ', $text);
        return $text;
    }

    /** @return list<string> */
    private static function numbers(string $text): array
    {
        preg_match_all('/\b\d{1,4}\b/', $text, $matches);
        return array_values(array_unique($matches[0]));
    }

    /** @param array<string,true> $set */
    private static function collectNumbers(array &$set, string $text): void
    {
        foreach (self::numbers($text) as $number) {
            $set[$number] = true;
        }
    }

    /** @return list<string> */
    public static function synonyms(): array
    {
        return self::synonymTable();
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private static function expandTokens(array $tokens): array
    {
        $out = $tokens;
        foreach (self::synonymTable() as $canonical => $variants) {
            $group = array_merge([$canonical], $variants);
            $hit = false;
            foreach ($tokens as $token) {
                if (in_array($token, $group, true)) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                continue;
            }
            foreach ($group as $variant) {
                $variant = (string) preg_replace('/\s+/', '', $variant);
                if (strlen($variant) > 2) {
                    $out[] = $variant;
                }
            }
        }
        return array_values(array_unique($out));
    }
}
