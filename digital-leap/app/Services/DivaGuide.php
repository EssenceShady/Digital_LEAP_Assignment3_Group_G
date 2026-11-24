<?php
declare(strict_types=1);

namespace Leap\Services;

use Leap\Core\Auth;
use Leap\Core\Database;

/**
 * What DIVA knows about the system itself: every page the signed-in role can
 * reach, what that page does, what must exist before it is useful, and the
 * next live action waiting on the user. Read-only, like the rest of DIVA.
 */
trait DivaGuide
{
    /** @return array<string,array{group:string,title:string,body:string,ability:string,prereq:string,label:string}> */
    private static function pages(): array
    {
        static $pages = null;

        return $pages ??= [
            'dashboard' => [
            'group' => 'workspace', 'title' => 'Dashboard',
            'body' => 'Your live overview: placement state, logbook progress and the next entry that is due.',
            'ability' => 'Open a card to jump straight to the record behind it.',
            'prereq' => 'Nothing. Every role lands here after sign-in.',
            'label' => 'Open my dashboard',
        ],
        'profile' => [
            'group' => 'workspace', 'title' => 'Profile',
            'body' => 'Your name, contact details, photo and password.',
            'ability' => 'Edit your details, upload a profile picture, change your password and upload a CV.',
            'prereq' => 'Nothing.',
            'label' => 'Open my profile',
        ],
        'notifications' => [
            'group' => 'workspace', 'title' => 'Notifications',
            'body' => 'Approvals, comments, due-date reminders and released results, newest first.',
            'ability' => 'Read a notification and mark it as read.',
            'prereq' => 'Nothing.',
            'label' => 'Open notifications',
        ],
        'guidebook' => [
            'group' => 'workspace', 'title' => 'Guidebook',
            'body' => 'The published internship guidebook as a PDF, with every page also readable as text.',
            'ability' => 'Open the PDF in the browser, download it, and read any of its 25 pages on screen.',
            'prereq' => 'Nothing. It is published to every role.',
            'label' => 'Open the guidebook',
        ],
        'messages' => [
            'group' => 'workspace', 'title' => 'Messages',
            'body' => 'Conversations tied to a placement, so the student, mentor and host supervisor discuss the same record.',
            'ability' => 'Read a thread, reply, react and attach a file.',
            'prereq' => 'A placement with you on it.',
            'label' => 'Open messages',
        ],
        'student/apply' => [
            'group' => 'apply', 'title' => 'Apply for an Internship',
            'body' => 'One page for the whole application: upload your CV, rank up to five host organisations and add a reason for each.',
            'ability' => 'Upload or replace your CV, add a host, reorder the list, remove a host and submit the ranked list.',
            'prereq' => 'An active account. The ranked list closes once a placement is allocated.',
            'label' => 'Open Apply for Internship',
        ],
        'student/placement' => [
            'group' => 'apply', 'title' => 'My Placement',
            'body' => 'The placement record: host, internship type (external or in-house), dates, academic mentor, host supervisor and the placement letter.',
            'ability' => 'Read the record, download the letter and message the people on the placement.',
            'prereq' => 'The internship office must allocate a placement first.',
            'label' => 'Open my placement',
        ],
        'student/logbook' => [
            'group' => 'evidence', 'title' => 'Logbook (Form E — 50%)',
            'body' => 'One entry per week of the placement, recorded day by day as in the paper log book, each reviewed by your host supervisor.',
            'ability' => 'Write the due week, see its daily hours and total, read supervisor comments and rework a returned week.',
            'prereq' => 'An allocated placement.',
            'label' => 'Open my logbook',
        ],
        'student/report' => [
            'group' => 'evidence', 'title' => 'Internship Report (Form C)',
            'body' => 'Upload the internship report your academic mentor marks against the eight Form E criteria.',
            'ability' => 'Upload or replace the report and read the mentor assessment once it is done.',
            'prereq' => 'A placement. You can replace the file until your mentor starts the review.',
            'label' => 'Open my internship report',
        ],
        'student/career-fair' => [
            'group' => 'evidence', 'title' => 'Career Fair 2026',
            'body' => 'The career fair participation report, with its own deadline and late-submission flag.',
            'ability' => 'Upload the career fair report and see whether it arrived on time.',
            'prereq' => 'A placement.',
            'label' => 'Open Career Fair 2026',
        ],
        'student/results' => [
            'group' => 'results', 'title' => 'Results (Form E)',
            'body' => 'Your published mark and the grade behind it.',
            'ability' => 'Read the result only after the internship office publishes it.',
            'prereq' => 'The grade sheet must be computed, moderated and published.',
            'label' => 'Open my results',
        ],
        'supervisor/queue' => [
            'group' => 'supervise', 'title' => 'Logbook Queue',
            'body' => 'Every submitted week from the students placed with your organisation.',
            'ability' => 'Read an entry, approve it or return it with one clear comment.',
            'prereq' => 'Students allocated to your organisation with a submitted week.',
            'label' => 'Open the review queue',
        ],
        'supervisor/evaluation' => [
            'group' => 'supervise', 'title' => 'Form D evaluation',
            'body' => 'Form D, the weighted host supervisor evaluation of each student.',
            'ability' => 'Score each criterion, save a draft and submit once the record is final.',
            'prereq' => 'A placement where you are the host supervisor.',
            'label' => 'Open the evaluation',
        ],
        'mentor/students' => [
            'group' => 'review', 'title' => 'My Students',
            'body' => 'The students assigned to you, with their progress, logbook, visits and report.',
            'ability' => 'Open a student record to see everything attached to the placement.',
            'prereq' => 'The internship office assigns you as academic mentor.',
            'label' => 'Open my students',
        ],
        'mentor/logbook' => [
            'group' => 'review', 'title' => 'Logbook Review',
            'body' => 'The same weeks the host supervisor sees, including comments and returned entries.',
            'ability' => 'Read and comment; a site visit conversation can start from the evidence.',
            'prereq' => 'A placement where you are the academic mentor.',
            'label' => 'Open logbook review',
        ],
        'mentor/visits' => [
            'group' => 'review', 'title' => 'Site Visits',
            'body' => 'Scheduled, completed and missed mentor visits to a placement.',
            'ability' => 'Schedule a visit, record findings and recommendations, and complete it.',
            'prereq' => 'A placement where you are the academic mentor.',
            'label' => 'Open site visits',
        ],
        'mentor/evaluation' => [
            'group' => 'review', 'title' => 'Mentor evaluation',
            'body' => 'The academic mentor evaluation of the placement, separate from the host supervisor Form D.',
            'ability' => 'Score each criterion and submit the evaluation.',
            'prereq' => 'A placement where you are the academic mentor.',
            'label' => 'Open the evaluation',
        ],
        'mentor/reports' => [
            'group' => 'review', 'title' => 'Report Assessment (Form C)',
            'body' => 'Uploaded internship reports waiting for an academic mark and comment.',
            'ability' => 'Open the report, record the mark and a short comment.',
            'prereq' => 'The student has uploaded the report.',
            'label' => 'Open report assessment',
        ],
        'coordinator/applications' => [
            'group' => 'office', 'title' => 'Applications',
            'body' => 'Each student with their CV and every ranked host, including MoU status and capacity.',
            'ability' => 'Read the application and allocate a host; the ranking is advice, the office decides.',
            'prereq' => 'Students must have submitted a ranked list.',
            'label' => 'Open applications',
        ],
        'coordinator/placements' => [
            'group' => 'office', 'title' => 'Placements',
            'body' => 'Every placement for the year with status, mentor, supervisor and the letter.',
            'ability' => 'Open a placement, approve it, print the letter and change its status.',
            'prereq' => 'A placement created by allocation.',
            'label' => 'Open placements',
        ],
        'coordinator/organisations' => [
            'group' => 'office', 'title' => 'Organisations',
            'body' => 'Host organisations with MoU status, expiry and capacity by year.',
            'ability' => 'Add or edit a host, record the MoU and set capacity.',
            'prereq' => 'Nothing. Only active hosts with a signed or pending MoU can receive students.',
            'label' => 'Open host organisations',
        ],
        'coordinator/visits' => [
            'group' => 'office', 'title' => 'Site Visits',
            'body' => 'Every mentor visit across placements, with findings and status.',
            'ability' => 'See which placements have been visited and what the mentor recorded.',
            'prereq' => 'Mentors must schedule visits.',
            'label' => 'Open site visits',
        ],
        'coordinator/grades' => [
            'group' => 'results', 'title' => 'Grades (Form E)',
            'body' => 'The grade sheet: compute from evidence, moderate with a reason, then publish.',
            'ability' => 'Compute a mark, moderate it and publish it to the student.',
            'prereq' => 'Evaluations and the report assessment must exist first.',
            'label' => 'Open the grade sheet',
        ],
        'management/kpis' => [
            'group' => 'insight', 'title' => 'Quality Indicators',
            'body' => 'Cohort completion, placement spread, publication and audit figures, with an export.',
            'ability' => 'Read the indicators, print the view and export the data.',
            'prereq' => 'Nothing; this page is read-only.',
            'label' => 'Open quality indicators',
        ],
        'admin/users' => [
            'group' => 'admin', 'title' => 'Users',
            'body' => 'Every account, its role and status.',
            'ability' => 'Create or invite a user, suspend an account, unlock it and reset a password.',
            'prereq' => 'Administrator role.',
            'label' => 'Open users',
        ],
        'admin/roles' => [
            'group' => 'admin', 'title' => 'Roles and Permissions',
            'body' => 'The six roles and the permissions each one holds.',
            'ability' => 'Read the role matrix; the sidebar, pages and DIVA answers follow it.',
            'prereq' => 'Administrator role.',
            'label' => 'Open the role matrix',
        ],
        'admin/settings' => [
            'group' => 'admin', 'title' => 'Settings',
            'body' => 'Assessment weights, the academic year and system options read by every calculation.',
            'ability' => 'Change a setting deliberately; it affects every computation.',
            'prereq' => 'Administrator role.',
            'label' => 'Open settings',
        ],
        'admin/audit' => [
            'group' => 'admin', 'title' => 'Audit Log',
            'body' => 'An append-only record of every action, with the actor and time.',
            'ability' => 'Read entries and open one for its before and after values.',
            'prereq' => 'Administrator role.',
            'label' => 'Open the audit log',
        ],
        'admin/feedback' => [
            'group' => 'admin', 'title' => 'Feedback',
            'body' => 'Ratings and comments users send from the feedback button.',
            'ability' => 'Read what users said about the system.',
            'prereq' => 'Administrator role.',
            'label' => 'Open feedback',
        ],
        'admin/mail' => [
            'group' => 'admin', 'title' => 'Mail Queue',
            'body' => 'Queued, sent and failed notification emails, all held on this machine.',
            'ability' => 'Inspect a message, resend a failure and process the queue.',
            'prereq' => 'Administrator role.',
            'label' => 'Open the mail queue',
        ],
        ];
    }

    /** @return array<string,string> */
    private static function groupTitles(): array
    {
        return [
            'workspace' => 'Workspace',
            'apply' => 'Apply and placement',
            'evidence' => 'Logbook, report and career fair',
            'results' => 'Results',
            'supervise' => 'Host supervision',
            'review' => 'Mentor review',
            'office' => 'Internship office',
            'insight' => 'Quality indicators',
            'admin' => 'Administration',
        ];
    }

    /** @return list<string> */
    private static function groupOrder(): array
    {
        return ['workspace', 'apply', 'evidence', 'supervise', 'review', 'office', 'results', 'insight', 'admin'];
    }

    /** @return array{route:string,title:string,body:string,ability:string,prereq:string,label:string}|null */
    public static function context(string $route): ?array
    {
        $pages = self::pages();
        if (!isset($pages[$route]) || !self::canRoute($route)) {
            return null;
        }
        return ['route' => $route] + $pages[$route];
    }

    /** @return list<array{key:string,title:string,items:list<array>}> */
    public static function capabilities(): array
    {
        $groups = [];
        $routes = [];
        $pages = self::pages();
        $titles = self::groupTitles();
        foreach (\nav_items() as $item) {
            $routes[] = (string) $item[0];
        }
        $routes[] = 'profile';
        $routes[] = 'notifications';
        $routes = array_values(array_unique($routes));

        foreach ($routes as $route) {
            if ($route === 'diva' || !isset($pages[$route]) || !self::canRoute($route)) {
                continue;
            }
            $page = $pages[$route];
            $groups[$page['group']]['key'] = $page['group'];
            $groups[$page['group']]['title'] = $titles[$page['group']] ?? 'Pages';
            $groups[$page['group']]['items'][] = [
                'title' => $page['title'],
                'body' => $page['body'],
                'label' => $page['label'],
                'route' => $route,
                'params' => [],
            ];
        }

        $ordered = [];
        foreach (self::groupOrder() as $key) {
            if (isset($groups[$key])) {
                $ordered[] = $groups[$key];
            }
        }
        return $ordered;
    }

    /** @return list<array{key:string,title:string,detail:string,route:string,params:array,cta:string,priority:int}> */
    public static function nextActions(): array
    {
        $role = (string) Auth::role();
        $rows = match ($role) {
            'student' => self::studentActions(),
            'supervisor' => self::supervisorActions(),
            'mentor' => self::mentorActions(),
            'coordinator' => self::coordinatorActions(),
            'management' => self::managementActions(),
            'admin', 'administrator' => self::adminActions(),
            default => [],
        };

        usort($rows, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);
        $out = [];
        foreach ($rows as $row) {
            $row['priority'] = 0;
            $out[] = $row;
        }
        return array_slice($out, 0, 4);
    }

    /** @return list<array{key:string,title:string,detail:string,route:string,params:array,cta:string,priority:int}> */
    private static function studentActions(): array
    {
        $sid = self::studentId();
        if ($sid === null) {
            return [];
        }

        $rows = [];
        $placement = self::studentPlacement();
        $year = self::currentYear();
        $cv = (int) Database::val("SELECT COUNT(*) FROM document WHERE student_id = ? AND doc_type = 'cv'", [$sid]);
        $prefs = Database::all(
            'SELECT a.status, o.name FROM application a JOIN organisation o ON o.org_id = a.org_id
             WHERE a.student_id = ? AND a.academic_year = ? ORDER BY a.preference_rank',
            [$sid, $year]
        );

        if ($placement !== null) {
            $pid = (int) $placement['placement_id'];
            $returned = Database::one(
                "SELECT entry_id, week_no FROM logbook_entry
                 WHERE placement_id = ? AND status = 'returned' ORDER BY week_no LIMIT 1",
                [$pid]
            );
            if ($returned !== null) {
                $rows[] = [
                    'key' => 'logbook.rework',
                    'title' => 'Rework week ' . (int) $returned['week_no'],
                    'detail' => 'Your host supervisor returned this week with a comment. Fix it, then resubmit.',
                    'route' => 'student/logbook/view', 'params' => ['id' => (int) $returned['entry_id']],
                    'cta' => 'Open week ' . (int) $returned['week_no'], 'priority' => 1,
                ];
            } else {
                $taken = array_map(
                    static fn (array $entry): int => (int) $entry['week_no'],
                    Database::all('SELECT week_no FROM logbook_entry WHERE placement_id = ?', [$pid])
                );
                $weeks = max(1, (int) $placement['total_weeks']);
                $next = null;
                for ($week = 1; $week <= $weeks; $week++) {
                    if (!in_array($week, $taken, true)) {
                        $next = $week;
                        break;
                    }
                }
                if ($next !== null) {
                    $from = date('Y-m-d', strtotime((string) $placement['start_date'] . ' +' . ($next - 1) * 7 . ' days'));
                    $to = date('Y-m-d', strtotime($from . ' +6 days'));
                    $overdue = strtotime($to) < time();
                    $rows[] = [
                        'key' => 'logbook.next',
                        'title' => 'Write week ' . $next,
                        'detail' => \fmt_date($from) . ' to ' . \fmt_date($to) . ($overdue ? ' — this week is overdue.' : ' — due by ' . \fmt_date($to) . '.'),
                        'route' => 'student/logbook/new', 'params' => ['week' => $next],
                        'cta' => 'Write week ' . $next, 'priority' => $overdue ? 2 : 3,
                    ];
                }
            }

            $report = Database::one(
                'SELECT status FROM internship_report WHERE placement_id = ? LIMIT 1',
                [$pid]
            );
            if ($report === null) {
                $rows[] = [
                    'key' => 'report.start',
                    'title' => 'Upload your internship report',
                    'detail' => 'The report is marked separately from the practical module. Your mentor can only assess it after you upload it.',
                    'route' => 'student/report', 'params' => [],
                    'cta' => 'Open my report', 'priority' => 5,
                ];
            }

            $published = Database::one(
                "SELECT m.weighted_total, g.grade_letter FROM mark m
                 LEFT JOIN grade_scale g ON g.grade_id = m.grade_id
                 WHERE m.placement_id = ? AND m.status = 'published' LIMIT 1",
                [$pid]
            );
            if ($published !== null) {
                $rows[] = [
                    'key' => 'result.read',
                    'title' => 'Your result is published',
                    'detail' => 'Grade ' . (string) ($published['grade_letter'] ?? '—') . '. Open the result sheet to read the detail.',
                    'route' => 'student/results', 'params' => [],
                    'cta' => 'Read my result', 'priority' => 9,
                ];
            }
        } else {
            if ($cv === 0) {
                $rows[] = [
                    'key' => 'cv.upload',
                    'title' => 'Upload your CV',
                    'detail' => 'The internship office and hosts read the latest CV on your application. You can replace it at any time before allocation.',
                    'route' => 'student/apply', 'params' => [],
                    'cta' => 'Upload my CV', 'priority' => 1,
                ];
            }
            if ($prefs === []) {
                $rows[] = [
                    'key' => 'apply.rank',
                    'title' => 'Rank your host organisations',
                    'detail' => 'Choose up to five hosts and write why each fits you. The office sees the whole list, not only your first choice.',
                    'route' => 'student/apply', 'params' => [],
                    'cta' => 'Rank my hosts', 'priority' => $cv === 0 ? 2 : 1,
                ];
            } elseif (in_array('draft', array_column($prefs, 'status'), true)) {
                $rows[] = [
                    'key' => 'apply.submit',
                    'title' => 'Submit your ranked list',
                    'detail' => count($prefs) . ' host' . (count($prefs) === 1 ? '' : 's') . ' on the list. Submit it so the internship office can start allocating.',
                    'route' => 'student/apply', 'params' => [],
                    'cta' => 'Open my application', 'priority' => 1,
                ];
            } else {
                $rows[] = [
                    'key' => 'apply.waiting',
                    'title' => 'Your application is with the office',
                    'detail' => 'You ranked ' . count($prefs) . ' host' . (count($prefs) === 1 ? '' : 's') . '. The internship office makes the final allocation; you can still update your CV.',
                    'route' => 'student/apply', 'params' => [],
                    'cta' => 'Open my application', 'priority' => 8,
                ];
                if ($cv === 0) {
                    $rows[] = [
                        'key' => 'cv.upload',
                        'title' => 'Upload your CV',
                        'detail' => 'Your ranked list is in, but no CV is on file. Add one so reviewers have something to read.',
                        'route' => 'student/apply', 'params' => [],
                        'cta' => 'Upload my CV', 'priority' => 2,
                    ];
                }
            }
        }

        return $rows;
    }

    /** @return list<array{key:string,title:string,detail:string,route:string,params:array,cta:string,priority:int}> */
    private static function supervisorActions(): array
    {
        $row = Database::one(
            "SELECT
                (SELECT COUNT(*) FROM logbook_entry e JOIN placement p ON p.placement_id = e.placement_id
                  WHERE p.supervisor_id = ? AND e.status = 'submitted') AS entries,
                (SELECT COUNT(*) FROM placement p
                  LEFT JOIN evaluation ev ON ev.placement_id = p.placement_id AND ev.evaluation_type = 'supervisor'
                  WHERE p.supervisor_id = ? AND p.status IN ('approved','active') AND (ev.evaluation_id IS NULL OR ev.status = 'draft')) AS evaluations",
            [Auth::id(), Auth::id()]
        );

        $rows = [];
        $entries = (int) ($row['entries'] ?? 0);
        $evaluations = (int) ($row['evaluations'] ?? 0);
        if ($entries > 0) {
            $rows[] = [
                'key' => 'queue.review',
                'title' => 'Review ' . self::plural($entries, 'logbook entry', 'logbook entries'),
                'detail' => 'A submitted week waits for approve or return. One clear comment is enough.',
                'route' => 'supervisor/queue', 'params' => [],
                'cta' => 'Open the review queue', 'priority' => 1,
            ];
        }
        if ($evaluations > 0) {
            $rows[] = [
                'key' => 'evaluation.complete',
                'title' => 'Complete Form D',
                'detail' => self::plural($evaluations, 'student is', 'students are') . ' waiting for your weighted evaluation.',
                'route' => 'supervisor/evaluation', 'params' => [],
                'cta' => 'Open the evaluation', 'priority' => 2,
            ];
        }
        return $rows;
    }

    /** @return list<array{key:string,title:string,detail:string,route:string,params:array,cta:string,priority:int}> */
    private static function mentorActions(): array
    {
        $row = Database::one(
            "SELECT
                (SELECT COUNT(*) FROM internship_report r JOIN placement p ON p.placement_id = r.placement_id
                  WHERE p.mentor_id = ? AND r.status = 'submitted') AS reports,
                (SELECT COUNT(*) FROM logbook_entry e JOIN placement p ON p.placement_id = e.placement_id
                  WHERE p.mentor_id = ? AND e.status = 'submitted') AS entries,
                (SELECT COUNT(*) FROM site_visit v
                  WHERE v.mentor_id = ? AND v.status = 'scheduled') AS visits,
                (SELECT COUNT(*) FROM placement p
                  LEFT JOIN evaluation ev ON ev.placement_id = p.placement_id AND ev.evaluation_type = 'mentor'
                  WHERE p.mentor_id = ? AND p.status IN ('approved','active') AND (ev.evaluation_id IS NULL OR ev.status = 'draft')) AS evaluations",
            [Auth::id(), Auth::id(), Auth::id(), Auth::id()]
        );

        $rows = [];
        $reports = (int) ($row['reports'] ?? 0);
        $entries = (int) ($row['entries'] ?? 0);
        $visits = (int) ($row['visits'] ?? 0);
        $evaluations = (int) ($row['evaluations'] ?? 0);
        if ($reports > 0) {
            $rows[] = [
                'key' => 'report.assess',
                'title' => 'Assess ' . self::plural($reports, 'internship report'),
                'detail' => 'Open the report, record the mark and a short comment; the office sees it immediately.',
                'route' => 'mentor/reports', 'params' => [],
                'cta' => 'Open report assessment', 'priority' => 1,
            ];
        }
        if ($entries > 0) {
            $rows[] = [
                'key' => 'logbook.read',
                'title' => 'Read ' . self::plural($entries, 'submitted week'),
                'detail' => 'These weeks are also with the host supervisor. Your comment stays on the record.',
                'route' => 'mentor/logbook', 'params' => [],
                'cta' => 'Open logbook review', 'priority' => 2,
            ];
        }
        if ($visits > 0) {
            $rows[] = [
                'key' => 'visit.complete',
                'title' => 'Complete ' . self::plural($visits, 'scheduled visit'),
                'detail' => 'Record who you met, what you observed and your recommendations.',
                'route' => 'mentor/visits', 'params' => [],
                'cta' => 'Open site visits', 'priority' => 3,
            ];
        }
        if ($evaluations > 0) {
            $rows[] = [
                'key' => 'evaluation.complete',
                'title' => 'Submit ' . self::plural($evaluations, 'mentor evaluation'),
                'detail' => 'The academic evaluation is separate from Form D and feeds the grade sheet.',
                'route' => 'mentor/evaluation', 'params' => [],
                'cta' => 'Open the evaluation', 'priority' => 4,
            ];
        }
        return $rows;
    }

    /** @return list<array{key:string,title:string,detail:string,route:string,params:array,cta:string,priority:int}> */
    private static function coordinatorActions(): array
    {
        $row = Database::one(
            "SELECT
                (SELECT COUNT(DISTINCT a.student_id) FROM application a
                  LEFT JOIN placement p ON p.student_id = a.student_id AND p.academic_year = a.academic_year
                  WHERE a.status IN ('submitted','shortlisted') AND p.placement_id IS NULL) AS allocate,
                (SELECT COUNT(*) FROM placement WHERE status = 'proposed') AS proposals,
                (SELECT COUNT(*) FROM mark WHERE status IN ('draft','moderated')) AS marks,
                (SELECT COUNT(*) FROM organisation
                  WHERE status = 'active' AND (mou_status <> 'signed' OR mou_expiry < CURDATE())) AS mou"
        );

        $rows = [];
        $allocate = (int) ($row['allocate'] ?? 0);
        $proposals = (int) ($row['proposals'] ?? 0);
        $marks = (int) ($row['marks'] ?? 0);
        $mou = (int) ($row['mou'] ?? 0);
        if ($allocate > 0) {
            $rows[] = [
                'key' => 'allocate',
                'title' => 'Allocate ' . self::plural($allocate, 'student'),
                'detail' => 'Submitted applications are waiting. The office chooses the host; the ranked list is advice.',
                'route' => 'coordinator/applications', 'params' => [],
                'cta' => 'Open applications', 'priority' => 1,
            ];
        }
        if ($proposals > 0) {
            $rows[] = [
                'key' => 'approve',
                'title' => 'Approve ' . self::plural($proposals, 'placement'),
                'detail' => 'A proposed placement stays invisible as confirmed until it is approved.',
                'route' => 'coordinator/placements', 'params' => [],
                'cta' => 'Open placements', 'priority' => 2,
            ];
        }
        if ($marks > 0) {
            $rows[] = [
                'key' => 'publish',
                'title' => 'Publish ' . self::plural($marks, 'grade sheet'),
                'detail' => 'Compute from evidence, moderate with a recorded reason, then release the results.',
                'route' => 'coordinator/grades', 'params' => [],
                'cta' => 'Open the grade sheet', 'priority' => 3,
            ];
        }
        if ($mou > 0) {
            $rows[] = [
                'key' => 'mou',
                'title' => self::plural($mou, 'host needs', 'hosts need') . ' an MoU check',
                'detail' => 'An active host without a signed, current MoU cannot receive another student.',
                'route' => 'coordinator/organisations', 'params' => [],
                'cta' => 'Open host organisations', 'priority' => 5,
            ];
        }
        return $rows;
    }

    /** @return list<array{key:string,title:string,detail:string,route:string,params:array,cta:string,priority:int}> */
    private static function managementActions(): array
    {
        $row = Database::one(
            "SELECT
                (SELECT COUNT(*) FROM mark) AS marks,
                (SELECT COUNT(*) FROM mark WHERE status = 'published') AS published,
                (SELECT COUNT(*) FROM placement WHERE status = 'active') AS active"
        );
        $marks = (int) ($row['marks'] ?? 0);
        $published = (int) ($row['published'] ?? 0);
        $rows = [];
        if ($marks > $published) {
            $rows[] = [
                'key' => 'publication',
                'title' => ($marks - $published) . ' grade sheet' . ($marks - $published === 1 ? '' : 's') . ' not yet published',
                'detail' => 'Indicators count published results only. The internship office releases them from the grade sheet.',
                'route' => 'management/kpis', 'params' => [],
                'cta' => 'Open quality indicators', 'priority' => 1,
            ];
        }
        $rows[] = [
            'key' => 'active',
            'title' => self::plural((int) ($row['active'] ?? 0), 'placement is', 'placements are') . ' active right now',
            'detail' => 'Completion, organisation spread and publication figures are on the indicator page.',
            'route' => 'management/kpis', 'params' => [],
            'cta' => 'Open quality indicators', 'priority' => 4,
        ];
        return $rows;
    }

    /** @return list<array{key:string,title:string,detail:string,route:string,params:array,cta:string,priority:int}> */
    private static function adminActions(): array
    {
        $row = Database::one(
            "SELECT
                (SELECT COUNT(*) FROM users WHERE status = 'invited') AS invited,
                (SELECT COUNT(*) FROM email_queue WHERE status IN ('queued','failed')) AS mail,
                (SELECT COUNT(*) FROM audit_log WHERE action = 'login.failed' AND created_at >= ?) AS failed",
            [date('Y-m-d 00:00:00')]
        );

        $rows = [];
        $invited = (int) ($row['invited'] ?? 0);
        $mail = (int) ($row['mail'] ?? 0);
        $failed = (int) ($row['failed'] ?? 0);
        if ($mail > 0) {
            $rows[] = [
                'key' => 'mail',
                'title' => self::plural($mail, 'email') . ' in the queue',
                'detail' => 'Queued or failed notification emails are held on this machine; nothing leaves the campus network.',
                'route' => 'admin/mail', 'params' => [],
                'cta' => 'Open the mail queue', 'priority' => 1,
            ];
        }
        if ($failed > 0) {
            $rows[] = [
                'key' => 'security',
                'title' => self::plural($failed, 'failed sign-in') . ' today',
                'detail' => 'Open the audit log to see which accounts were attempted.',
                'route' => 'admin/audit', 'params' => [],
                'cta' => 'Open the audit log', 'priority' => 2,
            ];
        }
        if ($invited > 0) {
            $rows[] = [
                'key' => 'invited',
                'title' => self::plural($invited, 'invited account'),
                'detail' => 'An invited account has not signed in yet. You can resend the invitation from Users.',
                'route' => 'admin/users', 'params' => [],
                'cta' => 'Open users', 'priority' => 3,
            ];
        }
        return $rows;
    }

    /** @return list<string> */
    public static function followUps(string $intent, ?string $route = null): array
    {
        $role = (string) Auth::role();
        $map = [
            'weighting' => ['How is the logbook weighted?', 'What does Form D cover?', 'What are the report criteria?'],
            'weighting.logbook' => ['What are the report criteria?', 'Who completes Form D?', 'How is the final grade computed?'],
            'deadline.logbook' => ['What happens if my supervisor returns a week?', 'How do I write a logbook entry?', 'Show me my logbook progress'],
            'logbook.status' => ['What is my next logbook entry?', 'What happens if a week is returned?', 'How is the logbook weighted?'],
            'deadline.report' => ['Where do I upload my report?', 'Check my report draft', 'What are the report criteria?'],
            'report.status' => ['Check my report draft', 'What are the report criteria?', 'Who marks the report?'],
            'results' => ['How is my final grade computed?', 'When are results published?', 'What does a pass mark look like?'],
            'progress' => ['What is my next logbook entry?', 'Can I see my results?', 'What is still open?'],
            'apply' => ['How do I upload my CV?', 'How many hosts can I rank?', 'Who chooses the host organisation?'],
            'allocation' => ['Who chooses the host organisation?', 'Can I change my ranked list after submitting?', 'What happens after allocation?'],
            'internship.type' => ['Is the assessment the same for in-house and external?', 'Who is eligible for internship?', 'How many weeks does the internship run?'],
            'internship.duration' => ['What is Form D?', 'What is my next logbook entry?', 'What are the assessment weights?'],
            'evaluation' => ['Who completes Form D?', 'How is the logbook weighted?', 'How is the final grade computed?'],
            'queue' => $role === 'student'
                ? ['What is my next logbook entry?', 'Can I see my results?']
                : ['What is waiting for me?', 'How is the grade computed?', 'What are the report criteria?'],
            'page.help' => ['What should I do next?', 'What can you do?', 'How is the logbook weighted?'],
            'capabilities' => ['What should I do next?', 'What can I do on this page?', 'How is the logbook weighted?'],
            'next' => ['What can you do?', 'How is the final grade computed?', 'What happens after allocation?'],
        ];

        $questions = $map[$intent] ?? ['What should I do next?', 'What can you do?', 'What are the assessment weights?'];
        $questions = array_values(array_filter($questions, static function (string $question): bool {
            return !preg_match('/^check my report draft$/i', $question) || self::canRoute('diva/report-check');
        }));

        return array_slice($questions, 0, 3);
    }

    private static function currentYear(): string
    {
        $current = (string) Database::val(
            'SELECT academic_year FROM placement WHERE status IN (?,?) GROUP BY academic_year ORDER BY academic_year DESC LIMIT 1',
            ['active', 'approved']
        );
        if ($current !== '') {
            return $current;
        }
        $month = (int) date('n');
        $start = $month >= 8 ? (int) date('Y') : (int) date('Y') - 1;
        return $start . '/' . ($start + 1);
    }
}
