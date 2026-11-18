<?php
declare(strict_types=1);

namespace Leap\Services;

use Leap\Core\Auth;
use Leap\Core\Database;

/**
 * Live-record answers for DIVA. Every query is fixed and scoped to the
 * signed-in user; no user text ever reaches SQL.
 */
trait DivaFacts
{
    /** @return array{answer:string,links:list<array>}|null */
    private static function factsFor(?string $intent): ?array
    {
        if ($intent === null) {
            return null;
        }
        $role = Auth::role();

        if ($intent === 'capabilities') {
            return [
                'answer' => "I am DIVA, the assistant built into Digital LEAP. I answer from two places: your live records (placement, logbook, report, results and queues, limited by your role) and the internship guidebook.\n\nI can show you a deadline, explain an assessment weight, point you to the right page, and read a report draft against the eight guidebook criteria without giving it a mark. I cannot change records, approve work or send messages for you.",
                'links' => self::links([self::link('Check my report', 'diva/report-check'), self::link('Open my dashboard', 'dashboard')]),
            ];
        }

        if ($intent === 'apply') {
            if ($role === 'student') {
                return self::studentApplyAnswer();
            }
            return [
                'answer' => 'A student applies from the Apply for Internship page: upload a CV, rank up to five host organisations with a reason for each, then submit. The internship office reads every choice and allocates one host. You can watch that queue from your own pages.',
                'links' => self::links([
                    self::link('Open applications', 'coordinator/applications'),
                    self::link('Open my students', 'mentor/students'),
                    self::link('Open my dashboard', 'dashboard'),
                ]),
            ];
        }

        if ($intent === 'allocation') {
            return [
                'answer' => 'The internship office allocates the host; the host organisation does not choose from the ranked list. A student ranks up to five hosts as advice. The office reads the CV, the reasons, the MoU status and the capacity, then creates one placement and closes the other open choices. After that the placement, the mentor and the host supervisor drive the rest of the internship.',
                'links' => self::links([
                    self::link('Open applications', 'coordinator/applications'),
                    self::link('Open my application', 'student/apply'),
                    self::link('Open my placement', 'student/placement'),
                ]),
            ];
        }

        if ($intent === 'internship.duration') {
            return [
                'answer' => "The internship training runs 16 weeks: 12 weeks of practical work plus 4 weeks for preparing the report (guidebook page 12, section 4.4). The 12 practical weeks are the ones the logbook covers, one entry per week (page 20), and a student who does not complete them has to repeat that period (page 13).\n\nTwo checkpoints fall inside those weeks: Form D1 at week 6 and Form D2 at week 12, which is when practical work ends (programme structure, page 25).",
                'links' => self::links([
                    self::link('Guidebook page 12', 'diva/source', ['page' => 12]),
                    self::link('Open my logbook', 'student/logbook'),
                    self::link('Open placements', 'coordinator/placements'),
                ]),
            ];
        }

        if ($intent === 'internship.type') {
            $answer = "LEAP runs two internship types (guidebook pages 3–4): an external placement within the industry, and an in-house placement on the university's acceleration platform. In an external placement the work comes from the host's projects; in-house students execute projects in line with, and sometimes with, industry. The guidebook states that the course structure and assessment criteria are the same for both (page 4).\n\nEligibility is the final semester, after the requirements leading up to semester five (pages 10–11). Digital LEAP records the type on every placement, so the placement letter, the coordinator register and your own placement page all show it.";
            $links = [
                self::link('Guidebook page 3', 'diva/source', ['page' => 3]),
                self::link('Guidebook page 4', 'diva/source', ['page' => 4]),
                self::link('Guidebook page 11', 'diva/source', ['page' => 11]),
            ];
            if ($role === 'student') {
                $placement = self::studentPlacement();
                if ($placement !== null) {
                    $answer = 'Your placement is ' . self::typePhrase($placement) . ".\n\n" . $answer;
                }
                $links[] = self::link('Open my placement', 'student/placement');
            } else {
                $links[] = self::link('Open placements', 'coordinator/placements');
            }
            return ['answer' => $answer, 'links' => self::links($links)];
        }

        if ($intent === 'weighting.logbook') {
            return [
                'answer' => 'The final mark is out of 200. Practical Internship carries 100 marks: logbook 50, Form D host supervisor evaluation 40 and student self evaluation 10 (Form E, guidebook page 24). The Internship Report carries 75 marks and the Career Fair 25 marks. So the logbook alone is half of Practical Internship, a quarter of the final mark.',
                'links' => self::links([self::link('Guidebook page 24', 'diva/source', ['page' => 24]), self::link('Open my logbook', 'student/logbook'), self::link('Check my report', 'diva/report-check')]),
            ];
        }

        if ($intent === 'weighting') {
            $lines = [];
            foreach (self::RUBRIC as $criterion) {
                $lines[] = '· ' . $criterion['label'] . ' — ' . $criterion['weight'] . '%';
            }
            return [
                'answer' => "The final mark is out of 200:\n\n· Practical Internship — 100 marks: logbook 50, Form D host supervisor evaluation 40, student self evaluation 10.\n· Internship Report — 75 marks, marked with these eight criteria from Form E (guidebook pages 24–25):\n" . implode("\n", $lines)
                    . "\n· Career Fair — 25 marks, recorded by the coordinator.\n\nThe academic mentor's evaluation is advisory and carries no marks.",
                'links' => self::links([self::link('Guidebook page 24', 'diva/source', ['page' => 24]), self::link('Check my report', 'diva/report-check'), self::link('Open my results', 'student/results')]),
            ];
        }

        if ($intent === 'evaluation') {
            return [
                'answer' => "Form D is the host organisation's assessment of the student's attitude and performance on placement, written by the appointed industry supervisor, not by the academic mentor or the student.\n\nIt has two parts: Form D1 at week 6 (the first evaluation, with a mid-term progress meeting) and Form D2 at week 12 (the final evaluation and final meeting). The week-by-week programme structure places them there on page 25. The student hands each form to the supervisor; when D2 is complete, the student and the supervisor sign it, the company stamps every page of D1 and D2, and both forms go back to the internship coordinator (guidebook pages 9–10, 13).\n\nIn the assessment it is 40% of Practical Internship, alongside the logbook at 50% and the student self evaluation at 10% (Form E, page 24). In Digital LEAP the host supervisor records it on the supervisor evaluation page and it feeds the grade sheet.",
                'links' => self::links([
                    self::link('Open supervisor evaluation', 'supervisor/evaluation'),
                    self::link('Open my placement', 'student/placement'),
                    self::link('Guidebook page 9', 'diva/source', ['page' => 9]),
                    self::link('Guidebook page 25', 'diva/source', ['page' => 25]),
                ]),
            ];
        }

        if ($intent === 'critique') {
            return [
                'answer' => "Open the report check and upload a .docx or paste the text. I compare the draft with the eight Form E criteria and show what I can and cannot see. I never give a mark and never rewrite your sentences; the judgement stays with your mentor.",
                'links' => self::links([self::link('Open report check', 'diva/report-check')]),
            ];
        }

        if ($intent === 'queue') {
            return self::queueAnswer($role);
        }

        if ($intent === 'overview') {
            return self::overviewAnswer($role);
        }

        if ($intent === 'accounts') {
            return self::accountsAnswer($role);
        }

        if ($intent === 'placement') {
            if ($role === 'student') {
                return self::studentPlacementAnswer();
            }
            return [
                'answer' => 'Placement records are scoped to your role. Use the placements page to see the records you are responsible for.',
                'links' => self::links([
                    self::link('Open placements', 'coordinator/placements'),
                    self::link('Open my students', 'mentor/students'),
                    self::link('Open the review queue', 'supervisor/queue'),
                ]),
            ];
        }

        if (in_array($intent, ['progress', 'results', 'report.status', 'logbook.status', 'deadline.report', 'deadline.logbook', 'deadline.career_fair'], true)) {
            if ($role !== 'student') {
                // Status questions are about a student's own record. For other
                // roles let guidebook retrieval answer "how it works" instead.
                if ($intent === 'report.status' || $intent === 'logbook.status') {
                    return null;
                }
                return self::roleFallback($intent);
            }
            $sid = self::studentId();
            if ($sid === null) {
                return null;
            }
            return match ($intent) {
                'progress' => self::studentProgressAnswer($sid),
                'results' => self::studentResultsAnswer($sid),
                'report.status', 'deadline.report' => self::studentReportAnswer($sid),
                'logbook.status', 'deadline.logbook' => self::studentLogbookAnswer($sid),
                'deadline.career_fair' => self::studentCareerFairAnswer($sid),
            };
        }

        return null;
    }

    /** @return array{answer:string,links:list<array>} */
    private static function roleFallback(string $intent): array
    {
        $labels = [
            'progress' => 'Internship progress',
            'results' => 'Results',
            'report.status' => 'Internship reports',
            'deadline.report' => 'Internship report deadlines',
            'logbook.status' => 'Logbooks',
            'deadline.logbook' => 'Logbook deadlines',
            'deadline.career_fair' => 'Career Fair submissions',
        ];
        $label = $labels[$intent] ?? 'That record';
        return [
            'answer' => $label . ' belong to students, so I can only read your own. Your role sees the records on its own pages; I will not show another person\'s work.',
            'links' => self::links([
                self::link('Open report assessment', 'mentor/reports'),
                self::link('Open the review queue', 'supervisor/queue'),
                self::link('Open placements', 'coordinator/placements'),
            ]),
        ];
    }

    /** @param array<string,mixed> $placement */
    private static function typePhrase(array $placement): string
    {
        $org = (string) ($placement['org_name'] ?? 'the host organisation');
        return strtolower((string) ($placement['internship_type'] ?? 'external')) === 'in-house'
            ? 'an in-house placement on the LEAP acceleration platform with ' . $org
            : 'an external placement at ' . $org;
    }

    private static function studentId(): ?int
    {        $id = Database::val('SELECT student_id FROM student WHERE user_id = ?', [Auth::id()]);
        return $id === null ? null : (int) $id;
    }

    private static function studentPlacement(): ?array
    {
        $sid = self::studentId();
        if ($sid === null) {
            return null;
        }
        return Database::one(
            'SELECT p.*, o.name AS org_name, o.district,
                    mu.first_name AS mentor_first, mu.last_name AS mentor_last,
                    su.first_name AS supervisor_first, su.last_name AS supervisor_last
             FROM placement p
             JOIN organisation o ON o.org_id = p.org_id
             JOIN users mu ON mu.user_id = p.mentor_id
             LEFT JOIN users su ON su.user_id = p.supervisor_id
             WHERE p.student_id = ?
             ORDER BY p.start_date DESC LIMIT 1',
            [$sid]
        );
    }

    /** @return array{answer:string,links:list<array>} */
    private static function studentPlacementAnswer(): array
    {
        $placement = self::studentPlacement();
        if ($placement === null) {
            return [
                'answer' => 'You do not have a placement record yet. The internship office creates it after an organisation accepts you.',
                'links' => self::links([self::link('Open my placement', 'student/placement')]),
            ];
        }
        $mentor = trim($placement['mentor_first'] . ' ' . $placement['mentor_last']);
        $supervisor = trim((string) ($placement['supervisor_first'] ?? '') . ' ' . (string) ($placement['supervisor_last'] ?? ''));
        $supervisor = $supervisor !== '' ? $supervisor : 'not recorded yet';
        return [
            'answer' => 'You are placed at ' . $placement['org_name'] . ' (' . $placement['district'] . ') for ' . $placement['academic_year']
                . ', from ' . \fmt_date($placement['start_date']) . ' to ' . \fmt_date($placement['end_date'])
                . ' over ' . (int) $placement['total_weeks'] . " weeks.\n\nAcademic mentor: " . $mentor
                . "\nHost supervisor: " . $supervisor
                . "\nStatus: " . str_replace('_', ' ', (string) $placement['status']),
            'links' => self::links([self::link('Open my placement', 'student/placement'), self::link('Message my mentor', 'messages')]),
        ];
    }

    /** @return array{answer:string,links:list<array>} */
    private static function studentApplyAnswer(): array
    {
        $sid = self::studentId();
        if ($sid === null) {
            return [
                'answer' => 'Open the Apply for Internship page to start. Upload your CV, then rank up to five hosts with a reason for each.',
                'links' => self::links([self::link('Open Apply for Internship', 'student/apply')]),
            ];
        }

        $year = self::currentYear();
        $cv = Database::one(
            "SELECT original_name, uploaded_at FROM document WHERE student_id = ? AND doc_type = 'cv'
             ORDER BY uploaded_at DESC LIMIT 1",
            [$sid]
        );
        $prefs = Database::all(
            'SELECT a.status, o.name FROM application a JOIN organisation o ON o.org_id = a.org_id
             WHERE a.student_id = ? AND a.academic_year = ? ORDER BY a.preference_rank',
            [$sid, $year]
        );
        $placement = self::studentPlacement();

        if ($placement !== null) {
            return [
                'answer' => 'Your application is closed because you already have a placement at ' . $placement['org_name']
                    . ' for ' . $placement['academic_year'] . '. The ranked list is now read-only. Your next work is the weekly logbook.'
                    . "\n\nFor a future run the flow is: upload a CV, rank up to five hosts with a reason for each, submit, then the internship office allocates one host.",
                'links' => self::links([
                    self::link('Open my placement', 'student/placement'),
                    self::link('Open my logbook', 'student/logbook'),
                ]),
            ];
        }

        $cvLine = $cv === null
            ? '1. CV — not uploaded yet. Reviewers have nothing to read until you add it.'
            : '1. CV — on file: ' . $cv['original_name'] . ', uploaded ' . \fmt_date((string) $cv['uploaded_at']) . '.';

        $count = count($prefs);
        $drafts = in_array('draft', array_column($prefs, 'status'), true);
        if ($count === 0) {
            $prefsLine = '2. Ranked hosts — none yet. Add up to five, each with a short reason.';
            $submitLine = '3. Submit — nothing to submit until at least one host is ranked.';
        } else {
            $names = array_map(static fn (array $pref): string => (string) $pref['name'], $prefs);
            $prefsLine = '2. Ranked hosts — ' . $count . ' of 5: ' . implode(' → ', $names) . '.';
            $submitLine = $drafts
                ? '3. Submit — the list is still a draft. Submit it so the internship office can allocate.'
                : '3. Submit — done. The office now sees your whole list, not only the first choice.';
        }

        $answer = "Your application, step by step:\n\n" . $cvLine . "\n" . $prefsLine . "\n" . $submitLine
            . "\n\nAllocation is the last step and the internship office makes it; a host does not pick from your list.";

        return [
            'answer' => $answer,
            'links' => self::links([
                self::link('Open Apply for Internship', 'student/apply'),
                self::link('Open my placement', 'student/placement'),
            ]),
        ];
    }

    /** @return array{answer:string,links:list<array>} */
    private static function studentProgressAnswer(int $sid): array
    {
        $progress = \internship_progress($sid);
        $done = [];
        $open = [];
        foreach ($progress['steps'] as $step) {
            $state = (string) ($step['state'] ?? 'todo');
            if ($state === 'done') {
                $done[] = $step['label'];
            } else {
                $open[] = $step['label'] . ($state === 'active' ? ' (in progress)' : '');
            }
        }

        $answer = 'Your internship is ' . (int) $progress['percent'] . '% complete.';
        if ($progress['placement'] !== null) {
            $answer .= ' The record is for ' . $progress['placement']['org_name'] . '.';
        }
        if ($done !== []) {
            $answer .= "\n\nComplete: " . implode(', ', $done) . '.';
        }
        if ($open !== []) {
            $answer .= "\n\nStill open: " . implode(', ', $open) . '.';
        }

        return [
            'answer' => $answer,
            'links' => self::links([
                self::link('Open my logbook', 'student/logbook'),
                self::link('Open my report', 'student/report'),
                self::link('Open my results', 'student/results'),
            ]),
        ];
    }

    /** @return array{answer:string,links:list<array>} */
    private static function studentLogbookAnswer(int $sid): array
    {
        $placement = self::studentPlacement();
        if ($placement === null) {
            return [
                'answer' => 'A logbook belongs to a placement, and you do not have one yet.',
                'links' => self::links([self::link('Open my placement', 'student/placement')]),
            ];
        }

        $row = Database::one(
            "SELECT COUNT(*) AS total,
                    SUM(status IN ('approved','mentor_reviewed')) AS approved,
                    SUM(status = 'returned') AS returned,
                    SUM(status = 'submitted') AS with_mentor,
                    MAX(CASE WHEN status = 'returned' THEN week_no END) AS rework_week
             FROM logbook_entry WHERE placement_id = ?",
            [(int) $placement['placement_id']]
        );

        $total = (int) ($row['total'] ?? 0);
        $approved = (int) ($row['approved'] ?? 0);
        $returned = (int) ($row['returned'] ?? 0);
        $withMentor = (int) ($row['with_mentor'] ?? 0);
        $weeks = (int) $placement['total_weeks'];

        if ($total === 0) {
            return [
                'answer' => 'You have not started the logbook yet. The placement runs for ' . $weeks . ' weeks; each week gets an entry.',
                'links' => self::links([self::link('Open my logbook', 'student/logbook')]),
            ];
        }

        $answer = 'Your logbook has ' . $total . ' of ' . $weeks . ' weeks started: ' . $approved . ' approved, '
            . $withMentor . ' waiting with your mentor and ' . $returned . ' returned for rework.';
        if ($returned > 0 && $row['rework_week'] !== null) {
            $answer .= ' Week ' . (int) $row['rework_week'] . ' needs your attention first.';
        }

        $started = Database::all(
            'SELECT week_no FROM logbook_entry WHERE placement_id = ? ORDER BY week_no',
            [(int) $placement['placement_id']]
        );
        $taken = array_map(static fn (array $entry): int => (int) $entry['week_no'], $started);
        $next = null;
        for ($week = 1; $week <= $weeks; $week++) {
            if (!in_array($week, $taken, true)) {
                $next = $week;
                break;
            }
        }

        if ($next === null) {
            $answer .= ' Every week already has an entry; the placement ends on ' . \fmt_date((string) $placement['end_date']) . '.';
        } else {
            $from = date('Y-m-d', strtotime((string) $placement['start_date'] . ' +' . ($next - 1) * 7 . ' days'));
            $to = date('Y-m-d', strtotime($from . ' +6 days'));
            $answer .= ' Week ' . $next . ' (' . \fmt_date($from) . ' to ' . \fmt_date($to) . ') is the next entry';
            $answer .= strtotime($to) < time() ? ' and it is overdue.' : ', due by ' . \fmt_date($to) . '.';
        }

        return [
            'answer' => $answer,
            'links' => self::links([self::link('Open my logbook', 'student/logbook')]),
        ];
    }

    /** @return array{answer:string,links:list<array>} */
    private static function studentReportAnswer(int $sid): array
    {
        $report = Database::one(
            'SELECT r.status, r.submitted_at, r.assessed_at
             FROM internship_report r
             JOIN placement p ON p.placement_id = r.placement_id
             WHERE p.student_id = ?
             ORDER BY r.submitted_at DESC LIMIT 1',
            [$sid]
        );

        if ($report === null) {
            return [
                'answer' => 'No internship report is on file yet. Upload the finished report from the internship report page; your mentor is notified automatically.',
                'links' => self::links([self::link('Open my report', 'student/report')]),
            ];
        }

        $status = (string) $report['status'];
        if ($status === 'assessed') {
            $answer = 'Your report was assessed on ' . \fmt_date((string) $report['assessed_at']) . '. The feedback is on the report page; your mentor wrote the assessment.';
        } elseif ($status === 'submitted') {
            $answer = 'Your report was submitted on ' . \fmt_date((string) $report['submitted_at']) . ' and is waiting for your mentor to assess it.';
        } else {
            $answer = 'A report record exists in ' . $status . ' state. Open the report page to see what is missing.';
        }

        return [
            'answer' => $answer,
            'links' => self::links([
                self::link('Open my report', 'student/report'),
                self::link('Check my report draft', 'diva/report-check'),
            ]),
        ];
    }

    /** @return array{answer:string,links:list<array>} */
    private static function studentResultsAnswer(int $sid): array
    {
        $mark = Database::one(
            "SELECT m.status, m.weighted_total, m.published_at, g.grade_letter, g.classification, p.academic_year
             FROM mark m
             JOIN placement p ON p.placement_id = m.placement_id
             LEFT JOIN grade_scale g ON g.grade_id = m.grade_id
             WHERE p.student_id = ? AND m.status = 'published'
             ORDER BY p.academic_year DESC LIMIT 1",
            [$sid]
        );

        if ($mark === null) {
            return [
                'answer' => 'No result has been published for you yet. A result appears only after the grade sheet is computed, moderated and released.',
                'links' => self::links([self::link('Open my results', 'student/results')]),
            ];
        }

        return [
            'answer' => 'Your ' . $mark['academic_year'] . ' result is published: grade ' . $mark['grade_letter']
                . ' (' . $mark['classification'] . '), released ' . \fmt_date((string) $mark['published_at']) . '. The full result sheet is on your results page.',
            'links' => self::links([self::link('Open my results', 'student/results')]),
        ];
    }

    /** @return array{answer:string,links:list<array>} */
    private static function studentCareerFairAnswer(int $sid): array
    {
        $placement = self::studentPlacement();
        $deadline = \career_fair_deadline();
        $late = strtotime($deadline) < time();
        $doc = null;
        if ($placement !== null) {
            $doc = Database::one(
                "SELECT original_name, uploaded_at FROM document WHERE placement_id = ? AND doc_type = 'career_fair_report' ORDER BY uploaded_at DESC LIMIT 1",
                [(int) $placement['placement_id']]
            );
        }

        $answer = 'The Career Fair 2026 report is due ' . date('l j F Y, g:i a', strtotime($deadline)) . '.';
        if ($doc === null) {
            $answer .= $late ? ' The deadline has passed; a submission is still accepted and marked late.' : ' No report is uploaded yet.';
        } else {
            $answer .= ' Your latest file, ' . $doc['original_name'] . ', was uploaded ' . \fmt_date((string) $doc['uploaded_at'])
                . (strtotime((string) $doc['uploaded_at']) > strtotime($deadline) ? ' (late).' : ' (on time).');
        }

        return [
            'answer' => $answer,
            'links' => self::links([self::link('Open Career Fair 2026', 'student/career-fair')]),
        ];
    }

    /** @return array{answer:string,links:list<array>} */
    private static function queueAnswer(string $role): array
    {
        if ($role === 'mentor') {
            $row = Database::one(
                "SELECT
                    (SELECT COUNT(*) FROM logbook_entry e JOIN placement p ON p.placement_id = e.placement_id WHERE p.mentor_id = ? AND e.status = 'submitted') AS entries,
                    (SELECT COUNT(*) FROM internship_report r JOIN placement p ON p.placement_id = r.placement_id WHERE p.mentor_id = ? AND r.status = 'submitted') AS reports,
                    (SELECT COUNT(*) FROM site_visit v JOIN placement p ON p.placement_id = v.placement_id WHERE p.mentor_id = ? AND v.status = 'scheduled') AS visits,
                    (SELECT COUNT(*) FROM placement p WHERE p.mentor_id = ? AND p.status IN ('approved','active')) AS students",
                [Auth::id(), Auth::id(), Auth::id(), Auth::id()]
            );
            return [
                'answer' => 'You have ' . self::plural((int) $row['entries'], 'logbook entry', 'logbook entries') . ' to review, '
                    . self::plural((int) $row['reports'], 'report') . ' to assess and '
                    . self::plural((int) $row['visits'], 'site visit') . ' scheduled across '
                    . self::plural((int) $row['students'], 'student') . '.',
                'links' => self::links([
                    self::link('Open logbook review', 'mentor/logbook'),
                    self::link('Open report assessment', 'mentor/reports'),
                    self::link('Open site visits', 'mentor/visits'),
                ]),
            ];
        }

        if ($role === 'supervisor') {
            $row = Database::one(
                "SELECT
                    (SELECT COUNT(*) FROM logbook_entry e JOIN placement p ON p.placement_id = e.placement_id WHERE p.supervisor_id = ? AND e.status = 'submitted') AS entries,
                    (SELECT COUNT(*) FROM evaluation ev JOIN placement p ON p.placement_id = ev.placement_id WHERE p.supervisor_id = ? AND ev.evaluation_type = 'supervisor' AND ev.status = 'draft') AS evaluations",
                [Auth::id(), Auth::id()]
            );
            return [
                'answer' => 'Your queue has ' . self::plural((int) $row['entries'], 'logbook entry', 'logbook entries')
                    . ' waiting for review and ' . self::plural((int) $row['evaluations'], 'evaluation form') . ' still in draft.',
                'links' => self::links([
                    self::link('Open the review queue', 'supervisor/queue'),
                    self::link('Open evaluation', 'supervisor/evaluation'),
                ]),
            ];
        }

        if ($role === 'coordinator') {
            $row = Database::one(
                "SELECT
                    (SELECT COUNT(*) FROM placement WHERE status = 'proposed') AS proposals,
                    (SELECT COUNT(*) FROM internship_report WHERE status = 'submitted') AS reports,
                    (SELECT COUNT(*) FROM mark WHERE status IN ('draft','moderated')) AS marks",
                []
            );
            return [
                'answer' => 'There are ' . self::plural((int) $row['proposals'], 'placement proposal')
                    . ' to approve, ' . self::plural((int) $row['reports'], 'report') . ' with mentors and '
                    . self::plural((int) $row['marks'], 'grade sheet') . ' not yet published.',
                'links' => self::links([
                    self::link('Open applications', 'coordinator/applications'),
                    self::link('Open grades', 'coordinator/grades'),
                ]),
            ];
        }

        return [
            'answer' => 'Queues are part of the role pages. Open the dashboard to see what needs attention.',
            'links' => self::links([self::link('Open my dashboard', 'dashboard')]),
        ];
    }

    /** @return array{answer:string,links:list<array>} */
    private static function overviewAnswer(string $role): array
    {
        $counts = Database::all('SELECT status, COUNT(*) AS total FROM placement GROUP BY status');
        $byStatus = [];
        foreach ($counts as $row) {
            $byStatus[(string) $row['status']] = (int) $row['total'];
        }
        $total = array_sum($byStatus);
        $active = (int) ($byStatus['active'] ?? 0);
        $completed = (int) ($byStatus['completed'] ?? 0);

        $summary = 'There are ' . $total . ' placements in the system: ' . $active . ' active, ' . $completed . ' completed and '
            . (int) ($byStatus['proposed'] ?? 0) . ' still proposed.';

        if ($role === 'management' || $role === 'admin') {
            $marks = Database::one(
                "SELECT COUNT(*) AS published, SUM(weighted_total >= 50) AS passed FROM mark WHERE status = 'published'",
                []
            );
            $published = (int) ($marks['published'] ?? 0);
            $passed = (int) ($marks['passed'] ?? 0);
            if ($published === 1) {
                $summary .= ' 1 result is published' . ($passed === 1 ? ' and it is a pass.' : '.');
            } elseif ($published > 1) {
                $summary .= ' ' . $published . ' results are published and ' . $passed . ' of them are passes.';
            }
        }

        return [
            'answer' => $summary,
            'links' => self::links([
                self::link('Open quality indicators', 'management/kpis'),
                self::link('Open placements', 'coordinator/placements'),
            ]),
        ];
    }

    /** @return array{answer:string,links:list<array>} */
    private static function accountsAnswer(string $role): array
    {
        if ($role !== 'admin') {
            return [
                'answer' => 'Account and audit records are administrator-only. I will not show them for your role.',
                'links' => self::links([self::link('Open my profile', 'profile')]),
            ];
        }

        $users = (int) Database::val('SELECT COUNT(*) FROM users', []);
        $today = (int) Database::val('SELECT COUNT(*) FROM audit_log WHERE created_at >= ?', [date('Y-m-d 00:00:00')]);
        $failed = (int) Database::val("SELECT COUNT(*) FROM audit_log WHERE action = 'login.failed' AND created_at >= ?", [date('Y-m-d 00:00:00')]);

        return [
            'answer' => 'The system has ' . $users . ' accounts. Today the audit log has recorded ' . $today . ' events, including ' . $failed . ' failed sign-ins.',
            'links' => self::links([
                self::link('Open users', 'admin/users'),
                self::link('Open the audit log', 'admin/audit'),
            ]),
        ];
    }

    private static function plural(int $count, string $singular, string $plural = ''): string
    {
        return $count . ' ' . ($count === 1 ? $singular : ($plural !== '' ? $plural : $singular . 's'));
    }
}
