<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\Guard;
use Leap\Core\Messenger;
use Leap\Core\Notify;
use Leap\Core\Settings;
use Leap\Core\Validator;
use Leap\Core\View;

final class CoordinatorController
{
    public function __construct()
    {
        Guard::requireRole('coordinator');
    }

    public function organisations(): void
    {
        $orgs = Database::all(
            'SELECT o.*,
                    (SELECT COUNT(*) FROM placement p WHERE p.org_id = o.org_id AND p.status IN (?,?,?)) AS active_placements,
                    (SELECT COUNT(*) FROM placement p WHERE p.org_id = o.org_id) AS total_placements
             FROM organisation o ORDER BY o.name',
            ['proposed', 'approved', 'active']
        );

        View::render('coordinator/organisations', [
            'title' => 'Host organisations',
            'orgs' => $orgs,
        ]);
    }

    public function orgForm(): void
    {
        $id = (int) \qget('id', 0);
        $org = null;
        if ($id > 0) {
            $org = Database::one('SELECT * FROM organisation WHERE org_id = ?', [$id]);
            if ($org === null) {
                \flash('error', 'Organisation not found.');
                \redirect('coordinator/organisations');
            }
        }

        View::render('coordinator/org_form', [
            'title' => $org ? 'Edit organisation' : 'Add organisation',
            'org' => $org,
        ]);
    }

    public function orgSave(): void
    {
        $id = (int) \post('org_id', 0);
        $name = trim((string) \post('name', ''));
        $sector = trim((string) \post('sector', ''));
        $address = trim((string) \post('address', ''));
        $district = trim((string) \post('district', ''));
        $contact = trim((string) \post('contact_person', ''));
        $email = strtolower(trim((string) \post('contact_email', '')));
        $phone = trim((string) \post('contact_phone', ''));
        $mou = (string) \post('mou_status', 'pending');
        $expiry = trim((string) \post('mou_expiry', ''));
        $capacity = (int) \post('capacity', 1);
        $status = (string) \post('status', 'active');

        $errors = [];
        if (!Validator::length($name, 3, 120)) {
            $errors[] = 'Enter the organisation name.';
        }
        if (!Validator::email($email)) {
            $errors[] = 'Enter a valid contact email address.';
        }
        if (!Validator::int($capacity, 1, 200)) {
            $errors[] = 'Capacity must be between 1 and 200 students.';
        }
        if ($expiry !== '' && !Validator::date($expiry)) {
            $errors[] = 'The MoU expiry date is not valid.';
        }
        if (!in_array($mou, ['pending', 'signed', 'expired'], true)) {
            $mou = 'pending';
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }
        $duplicate = Database::val('SELECT COUNT(*) FROM organisation WHERE name = ? AND org_id <> ?', [$name, $id]);
        if ((int) $duplicate > 0) {
            $errors[] = 'Another organisation already uses that name.';
        }

        if ($errors !== []) {
            foreach ($errors as $message) {
                \flash('error', $message);
            }
            \redirect('coordinator/organisations/form', $id > 0 ? ['id' => $id] : []);
        }

        $data = [
            'name' => $name,
            'sector' => $sector,
            'address' => $address,
            'district' => $district,
            'contact_person' => $contact,
            'contact_email' => $email,
            'contact_phone' => $phone,
            'mou_status' => $mou,
            'mou_expiry' => $expiry === '' ? null : $expiry,
            'capacity' => $capacity,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        Database::tx(static function () use ($id, $data, $name): void {
            if ($id > 0) {
                $before = Database::one('SELECT * FROM organisation WHERE org_id = ?', [$id]);
                Database::update('organisation', $data, 'org_id = ?', [$id]);
                Audit::log('organisation.updated', 'organisation', $id, $before, $data);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $newId = Database::insert('organisation', $data);
                Audit::log('organisation.created', 'organisation', $newId, null, ['name' => $name]);
            }
        });

        \flash('success', 'Organisation saved.');
        \redirect('coordinator/organisations');
    }

    public function applications(): void
    {
        $year = $this->year();
        $search = trim((string) \qget('q', ''));
        $cvFilter = (string) \qget('cv', '');
        $where = '';
        $params = [$year, $year, $year, 'cv', 'active'];
        if ($search !== '') {
            $where .= " AND CONCAT_WS(' ', u.first_name, u.last_name, u.email, s.reg_no) LIKE ?";
            $params[] = '%' . $search . '%';
        }
        if (in_array($cvFilter, ['uploaded', 'missing'], true)) {
            $where .= ' AND ' . ($cvFilter === 'missing' ? 'NOT ' : '') . "EXISTS (SELECT 1 FROM document d WHERE d.student_id = s.student_id AND d.doc_type = 'cv')";
        }
        $students = Database::all(
            "SELECT s.*, u.first_name, u.last_name, u.email,
                    (SELECT COUNT(*) FROM application a WHERE a.student_id = s.student_id AND a.academic_year = ?) AS preference_count,
                    (SELECT a.status FROM application a WHERE a.student_id = s.student_id AND a.academic_year = ?
                      ORDER BY FIELD(a.status, 'allocated', 'shortlisted', 'submitted', 'draft', 'rejected', 'withdrawn'), a.preference_rank LIMIT 1) AS first_status,
                    (SELECT p.status FROM placement p WHERE p.student_id = s.student_id AND p.academic_year = ? LIMIT 1) AS placement_status,
                    (SELECT d.doc_id FROM document d WHERE d.student_id = s.student_id AND d.doc_type = ? ORDER BY d.doc_id DESC LIMIT 1) AS cv_doc
             FROM student s JOIN users u ON u.user_id = s.user_id
             WHERE u.status = ?" . $where . "
             ORDER BY u.last_name, u.first_name",
            $params
        );

        View::render('coordinator/applications', [
            'title' => 'Applications',
            'students' => $students,
            'search' => $search,
            'cvFilter' => $cvFilter,
            'year' => $year,
        ]);
    }

    public function applicationView(): void
    {
        $studentId = (int) \qget('student_id', 0);
        $student = Database::one(
            'SELECT s.*, u.first_name, u.last_name, u.email, u.phone
             FROM student s JOIN users u ON u.user_id = s.user_id WHERE s.student_id = ?',
            [$studentId]
        );
        if ($student === null) {
            \flash('error', 'Student not found.');
            \redirect('coordinator/applications');
        }
        $year = $this->year();
        $prefs = Database::all(
            'SELECT a.*, o.name AS org_name, o.district, o.capacity, o.mou_status
             FROM application a JOIN organisation o ON o.org_id = a.org_id
             WHERE a.student_id = ? AND a.academic_year = ? ORDER BY a.preference_rank',
            [$studentId, $year]
        );
        $cv = Database::one(
            'SELECT d.*, rv.first_name AS reviewer_first, rv.last_name AS reviewer_last
             FROM document d LEFT JOIN users rv ON rv.user_id = d.reviewed_by
             WHERE d.student_id = ? AND d.doc_type = ? ORDER BY d.doc_id DESC LIMIT 1',
            [$studentId, 'cv']
        );
        $placement = Database::one(
            'SELECT * FROM placement WHERE student_id = ? AND academic_year = ?',
            [$studentId, $year]
        );

        View::render('coordinator/application_view', [
            'title' => 'Application',
            'student' => $student,
            'prefs' => $prefs,
            'cv' => $cv,
            'placement' => $placement,
            'year' => $year,
        ]);
    }

    public function placements(): void
    {
        $year = (string) \qget('year', $this->year());
        $status = (string) \qget('status', '');
        $orgId = (int) \qget('org_id', 0);
        $sql = 'SELECT p.*, u.first_name, u.last_name, u.user_id AS student_user, s.reg_no, o.name AS org_name,
                       m.first_name AS mentor_first, m.last_name AS mentor_last, m.user_id AS mentor_user,
                       sup.first_name AS sup_first, sup.last_name AS sup_last, sup.user_id AS sup_user
                FROM placement p
                JOIN student s ON s.student_id = p.student_id
                JOIN users u ON u.user_id = s.user_id
                JOIN organisation o ON o.org_id = p.org_id
                JOIN users m ON m.user_id = p.mentor_id
                LEFT JOIN users sup ON sup.user_id = p.supervisor_id
                WHERE p.academic_year = ?';
        $params = [$year];
        if ($status !== '') {
            $sql .= ' AND p.status = ?';
            $params[] = $status;
        }
        if ($orgId > 0) {
            $sql .= ' AND p.org_id = ?';
            $params[] = $orgId;
        }
        $sql .= ' ORDER BY o.name, u.last_name';

        View::render('coordinator/placements', [
            'title' => 'Placements',
            'placements' => Database::all($sql, $params),
            'year' => $year,
            'status' => $status,
            'orgId' => $orgId,
            'orgs' => Database::all('SELECT org_id, name FROM organisation ORDER BY name'),
        ]);
    }

    public function allocateForm(): void
    {
        $studentId = (int) \qget('student_id', 0);
        $year = $this->year();
        $student = null;
        if ($studentId > 0) {
            $student = Database::one(
                'SELECT s.*, u.first_name, u.last_name, u.email
                 FROM student s JOIN users u ON u.user_id = s.user_id WHERE s.student_id = ?',
                [$studentId]
            );
        }
        if ($student === null) {
            \flash('error', 'Choose a student from the applications queue.');
            \redirect('coordinator/applications');
        }
        $existing = Database::one(
            'SELECT placement_id FROM placement WHERE student_id = ? AND academic_year = ?',
            [(int) $student['student_id'], $year]
        );
        if ($existing !== null) {
            \flash('error', 'That student already has a placement for ' . $year . '. Open the placement to change the host.');
            \redirect('coordinator/placements/view', ['id' => (int) $existing['placement_id']]);
        }

        View::render('coordinator/allocate_form', [
            'title' => 'Allocate placement',
            'student' => $student,
            'year' => $year,
            'orgs' => Database::all(
                'SELECT o.*,
                        (SELECT COUNT(*) FROM placement p
                          WHERE p.org_id = o.org_id AND p.academic_year = ? AND p.status IN (?,?,?)) AS used
                 FROM organisation o WHERE o.status = ? ORDER BY o.name',
                [$year, 'proposed', 'approved', 'active', 'active']
            ),
            'mentors' => Database::all(
                'SELECT u.user_id, u.first_name, u.last_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE r.role_key = ? AND u.status = ? ORDER BY u.last_name',
                ['mentor', 'active']
            ),
            'supervisors' => Database::all(
                'SELECT u.user_id, u.first_name, u.last_name, u.organisation_id, o.name AS org_name
                 FROM users u JOIN roles r ON r.role_id = u.role_id LEFT JOIN organisation o ON o.org_id = u.organisation_id
                 WHERE r.role_key = ? AND u.status = ? ORDER BY o.name, u.last_name',
                ['supervisor', 'active']
            ),
            'prefs' => Database::all(
                'SELECT a.application_id, a.preference_rank, a.org_id, a.status, a.motivation,
                        o.name, o.district, o.sector, o.capacity, o.mou_status, o.status AS org_status,
                        (SELECT COUNT(*) FROM placement p
                          WHERE p.org_id = o.org_id AND p.academic_year = ? AND p.status IN (?,?,?)) AS used
                 FROM application a JOIN organisation o ON o.org_id = a.org_id
                 WHERE a.student_id = ? AND a.academic_year = ? AND a.status NOT IN (?, ?)
                 ORDER BY a.preference_rank',
                [$year, 'proposed', 'approved', 'active', (int) $student['student_id'], $year, 'rejected', 'withdrawn']
            ),
        ]);
    }

    public function allocate(): void
    {
        $studentId = (int) \post('student_id', 0);
        $orgId = (int) \post('org_id', 0);
        if ($orgId === 0) {
            $orgId = (int) \post('org_id_other', 0);
        }
        $mentorId = (int) \post('mentor_id', 0);
        $supervisorId = (int) \post('supervisor_id', 0);
        $start = (string) \post('start_date', '');
        $end = (string) \post('end_date', '');
        $type = (string) \post('internship_type', 'external');
        if (!in_array($type, ['external', 'in-house'], true)) {
            $type = 'external';
        }
        $year = (string) \post('academic_year', $this->year());

        $student = Database::one('SELECT * FROM student WHERE student_id = ?', [$studentId]);
        $org = Database::one('SELECT * FROM organisation WHERE org_id = ?', [$orgId]);
        $mentor = Database::one(
            'SELECT u.* FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ? AND r.role_key = ?',
            [$mentorId, 'mentor']
        );
        $supervisor = null;
        if ($supervisorId > 0) {
            $supervisor = Database::one(
                'SELECT u.* FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ? AND r.role_key = ?',
                [$supervisorId, 'supervisor']
            );
        }

        $errors = [];
        if ($student === null) {
            $errors[] = 'That student does not exist.';
        }
        if ($org === null || $org['status'] !== 'active') {
            $errors[] = 'Choose an active host organisation.';
        } elseif (!in_array((string) $org['mou_status'], ['signed', 'pending'], true)) {
            $errors[] = 'The MoU with ' . $org['name'] . ' is ' . $org['mou_status'] . '. Renew it before placing a student there.';
        }
        if ($mentor === null) {
            $errors[] = 'Choose an academic mentor.';
        }
        if ($supervisorId > 0 && $supervisor === null) {
            $errors[] = 'The selected host supervisor is not a supervisor account.';
        }
        if ($supervisor !== null && (int) $supervisor['organisation_id'] !== $orgId) {
            $errors[] = 'The host supervisor does not belong to the chosen organisation.';
        }
        if (!Validator::date($start) || !Validator::date($end)) {
            $errors[] = 'Enter valid start and end dates.';
        } elseif ($end <= $start) {
            $errors[] = 'The end date must be after the start date.';
        }
        if ($student !== null && Database::val('SELECT COUNT(*) FROM placement WHERE student_id = ? AND academic_year = ?', [$studentId, $year]) > 0) {
            $errors[] = 'That student already has a placement for ' . $year . '.';
        }
        if ($org !== null) {
            $used = (int) Database::val(
                'SELECT COUNT(*) FROM placement WHERE org_id = ? AND academic_year = ? AND status IN (?,?,?)',
                [$orgId, $year, 'proposed', 'approved', 'active']
            );
            if ($used >= (int) $org['capacity']) {
                $errors[] = 'The organisation is at capacity (' . (int) $org['capacity'] . ') for ' . $year . '.';
            }
        }

        if ($errors !== []) {
            foreach ($errors as $message) {
                \flash('error', $message);
            }
            \redirect('coordinator/placements/allocate', ['student_id' => $studentId]);
        }

        $weeks = (int) ceil((strtotime($end) - strtotime($start)) / 604800);

        Database::tx(static function () use ($studentId, $student, $orgId, $org, $mentorId, $supervisorId, $supervisor, $start, $end, $weeks, $year, $type): void {
            $placementId = Database::insert('placement', [
                'student_id' => $studentId,
                'org_id' => $orgId,
                'coordinator_id' => (int) Auth::id(),
                'mentor_id' => $mentorId,
                'supervisor_id' => $supervisorId > 0 ? $supervisorId : null,
                'start_date' => $start,
                'end_date' => $end,
                'total_weeks' => max(1, $weeks),
                'internship_type' => $type,
                'academic_year' => $year,
                'status' => 'proposed',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Audit::log('placement.created', 'placement', $placementId, null, [
                'student' => $student['reg_no'],
                'organisation' => $org['name'],
                'type' => $type,
                'start' => $start,
                'end' => $end,
            ]);

            $chosen = Database::one(
                'SELECT application_id FROM application WHERE student_id = ? AND academic_year = ? AND org_id = ?',
                [$studentId, $year, $orgId]
            );
            if ($chosen !== null) {
                Database::update('application', ['status' => 'allocated'], 'application_id = ?', [(int) $chosen['application_id']]);
            }
            $closed = Database::all(
                "SELECT a.application_id, o.name FROM application a JOIN organisation o ON o.org_id = a.org_id
                 WHERE a.student_id = ? AND a.academic_year = ? AND a.org_id <> ? AND a.status IN (?, ?)
                 ORDER BY a.preference_rank",
                [$studentId, $year, $orgId, 'submitted', 'shortlisted']
            );
            foreach ($closed as $other) {
                Database::update('application', ['status' => 'rejected'], 'application_id = ?', [(int) $other['application_id']]);
            }
            if ($chosen !== null || $closed !== []) {
                Audit::log('application.closed', 'application', $chosen !== null ? (int) $chosen['application_id'] : 0, null, [
                    'placement' => $placementId,
                    'allocated_org' => $org['name'],
                    'rejected' => array_column($closed, 'name'),
                ]);
            }

            $studentUser = (int) Database::val('SELECT user_id FROM student WHERE student_id = ?', [$studentId]);
            Notify::push(
                $studentUser,
                'placement',
                'Placement allocated',
                'The internship office allocated you to ' . $org['name'] . ' from ' . date('d M Y', strtotime($start)) . '. Your other choices are closed.',
                'index.php?r=student/placement'
            );
            Notify::push($mentorId, 'placement', 'New student assigned', 'You are the academic mentor for ' . $student['reg_no'] . ' at ' . $org['name'] . '.', 'index.php?r=mentor/students');
            if ($supervisor !== null) {
                Notify::push($supervisorId, 'placement', 'Internship student assigned', $student['reg_no'] . ' will join ' . $org['name'] . ' from ' . date('d M Y', strtotime($start)) . '.', 'index.php?r=supervisor/queue');
            }
        });

        \flash('success', 'Placement created as proposed. The application list is closed and the student, mentor and supervisor have been notified.');
        \redirect('coordinator/placements');
    }

    public function placementView(): void
    {
        $id = (int) \qget('id', 0);
        $placement = $this->findPlacement($id);
        if ($placement === null) {
            \flash('error', 'Placement not found.');
            \redirect('coordinator/placements');
        }
        $timeline = Database::all('SELECT * FROM audit_log WHERE entity_type = ? AND entity_id = ? ORDER BY created_at, audit_id', ['placement', $id]);
        $docs = Database::all(
            'SELECT d.*, rv.first_name AS reviewer_first, rv.last_name AS reviewer_last
             FROM document d LEFT JOIN users rv ON rv.user_id = d.reviewed_by
             WHERE d.placement_id = ? ORDER BY d.uploaded_at DESC',
            [$id]
        );
        $entries = Database::all('SELECT status, COUNT(*) AS total FROM logbook_entry WHERE placement_id = ? GROUP BY status', [$id]);

        View::render('coordinator/placement_view', [
            'title' => 'Placement ' . $placement['reg_no'],
            'placement' => $placement,
            'timeline' => $timeline,
            'docs' => $docs,
            'entryCounts' => $entries,
        ]);
    }

    public function approve(): void
    {
        $id = (int) \post('placement_id', 0);
        $placement = $this->findPlacement($id);
        if ($placement === null) {
            http_response_code(403);
            View::error(403);
            return;
        }
        if ($placement['status'] !== 'proposed') {
            \flash('error', 'Only proposed placements can be approved.');
            \redirect('coordinator/placements/view', ['id' => $id]);
        }

        Database::tx(static function () use ($placement, $id): void {
            Database::update('placement', [
                'status' => 'approved',
                'approved_by' => (int) Auth::id(),
                'approved_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'placement_id = ?', [$id]);
            Audit::log('placement.approved', 'placement', $id, ['status' => $placement['status']], ['status' => 'approved']);
            Notify::push(
                (int) $placement['student_user'],
                'placement',
                'Placement approved',
                'Your placement at ' . $placement['org_name'] . ' has been approved. You can now submit logbook entries once it is active.',
                'index.php?r=student/placement'
            );
            Notify::push((int) $placement['mentor_id'], 'placement', 'Placement approved', $placement['first_name'] . ' ' . $placement['last_name'] . ' is approved at ' . $placement['org_name'] . '.', 'index.php?r=mentor/students');
            if ($placement['supervisor_id'] !== null) {
                Notify::push((int) $placement['supervisor_id'], 'placement', 'Placement approved', $placement['first_name'] . ' ' . $placement['last_name'] . ' is approved at ' . $placement['org_name'] . '.', 'index.php?r=supervisor/queue');
            }
            Messenger::systemForPlacement($id, 'Placement approved by the internship coordinator. The placement is now active for logbook submissions.');
        });

        \flash('success', 'Placement approved.');
        \redirect('coordinator/placements/view', ['id' => $id]);
    }

    public function status(): void
    {
        $id = (int) \post('placement_id', 0);
        $to = (string) \post('to', '');
        $placement = $this->findPlacement($id);
        if ($placement === null) {
            http_response_code(403);
            View::error(403);
            return;
        }
        $allowed = [
            'approved' => ['active', 'terminated'],
            'active' => ['completed', 'terminated'],
            'completed' => [],
            'terminated' => [],
            'proposed' => ['terminated'],
        ];
        if (!in_array($to, $allowed[$placement['status']] ?? [], true)) {
            \flash('error', 'That status change is not allowed from ' . $placement['status'] . '.');
            \redirect('coordinator/placements/view', ['id' => $id]);
        }

        Database::tx(static function () use ($placement, $id, $to): void {
            Database::update('placement', [
                'status' => $to,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'placement_id = ?', [$id]);
            Audit::log('placement.status_changed', 'placement', $id, ['status' => $placement['status']], ['status' => $to]);
            Notify::push(
                (int) $placement['student_user'],
                'placement',
                'Placement ' . $to,
                'Your placement at ' . $placement['org_name'] . ' is now ' . $to . '.',
                'index.php?r=student/placement'
            );
        });

        \flash('success', 'Placement marked ' . $to . '.');
        \redirect('coordinator/placements/view', ['id' => $id]);
    }

    public function type(): void
    {
        $id = (int) \post('placement_id', 0);
        $type = (string) \post('internship_type', '');
        $placement = $this->findPlacement($id);
        if ($placement === null) {
            http_response_code(403);
            View::error(403);
            return;
        }
        if (!in_array($type, ['external', 'in-house'], true)) {
            \flash('error', 'Choose external or in-house.');
            \redirect('coordinator/placements/view', ['id' => $id]);
        }
        if ($placement['status'] === 'completed' || $placement['status'] === 'terminated') {
            \flash('error', 'A finished placement keeps its type.');
            \redirect('coordinator/placements/view', ['id' => $id]);
        }

        $previous = (string) ($placement['internship_type'] ?? 'external');
        if ($previous !== $type) {
            Database::update('placement', [
                'internship_type' => $type,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'placement_id = ?', [$id]);
            Audit::log('placement.type_changed', 'placement', $id, ['internship_type' => $previous], ['internship_type' => $type]);
            Notify::push(
                (int) $placement['student_user'],
                'placement',
                'Placement type updated',
                'Your placement is now recorded as ' . ($type === 'in-house' ? 'in-house on the LEAP platform' : 'an external placement') . '.',
                'index.php?r=student/placement'
            );
        }

        \flash('success', 'Placement type set to ' . ($type === 'in-house' ? 'in-house' : 'external') . '.');
        \redirect('coordinator/placements/view', ['id' => $id]);
    }

    public function visits(): void
    {
        $status = (string) \qget('status', '');
        $search = trim((string) \qget('q', ''));
        $from = (string) \qget('from', '');
        $to = (string) \qget('to', '');
        $where = ' WHERE 1 = 1';
        $params = [];
        if ($status !== '') {
            $where .= ' AND v.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where .= " AND CONCAT_WS(' ', u.first_name, u.last_name, s.reg_no, o.name) LIKE ?";
            $params[] = '%' . $search . '%';
        }
        if (Validator::date($from)) {
            $where .= ' AND v.scheduled_date >= ?';
            $params[] = $from;
        }
        if (Validator::date($to)) {
            $where .= ' AND v.scheduled_date <= ?';
            $params[] = $to;
        }
        $visits = Database::all(
            'SELECT v.*, u.first_name, u.last_name, s.reg_no, o.name AS org_name,
                    mu.first_name AS mentor_first, mu.last_name AS mentor_last
             FROM site_visit v
             JOIN placement p ON p.placement_id = v.placement_id
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             JOIN users mu ON mu.user_id = v.mentor_id' . $where . '
             ORDER BY v.scheduled_date DESC', $params
        );
        $today = date('Y-m-d');

        View::render('coordinator/visits', [
            'title' => 'Site visits overview',
            'status' => $status,
            'search' => $search,
            'from' => $from,
            'to' => $to,
            'visits' => $visits,
            'overdue' => array_values(array_filter($visits, static fn (array $v): bool => $v['status'] === 'scheduled' && $v['scheduled_date'] < $today)),
            'upcoming' => array_values(array_filter($visits, static fn (array $v): bool => $v['status'] === 'scheduled' && $v['scheduled_date'] >= $today)),
        ]);
    }

    public function grades(): void
    {
        $year = (string) \qget('year', $this->year());
        $rows = Database::all(
            'SELECT p.*, u.first_name, u.last_name, s.reg_no, o.name AS org_name,
                    m.mark_id, m.status AS mark_status, m.weighted_total, m.grade_id,
                    g.grade_letter,
                    (SELECT e.total_score FROM evaluation e WHERE e.placement_id = p.placement_id AND e.evaluation_type = ? AND e.status = ? ORDER BY e.evaluation_id DESC LIMIT 1) AS supervisor_total,
                    (SELECT e.total_score FROM evaluation e WHERE e.placement_id = p.placement_id AND e.evaluation_type = ? AND e.status = ? ORDER BY e.evaluation_id DESC LIMIT 1) AS self_total,
                    (SELECT r.score FROM internship_report r WHERE r.placement_id = p.placement_id) AS report_score,
                    (SELECT r.status FROM internship_report r WHERE r.placement_id = p.placement_id) AS report_status
             FROM placement p
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             LEFT JOIN mark m ON m.placement_id = p.placement_id
             LEFT JOIN grade_scale g ON g.grade_id = m.grade_id
             WHERE p.academic_year = ?
             ORDER BY u.last_name, u.first_name',
            ['supervisor', 'submitted', 'self', 'submitted', $year]
        );

        View::render('coordinator/grades', [
            'title' => 'Grade sheet',
            'rows' => $rows,
            'year' => $year,
        ]);
    }

    public function gradeView(): void
    {
        $id = (int) \qget('placement_id', 0);
        $placement = $this->findPlacement($id);
        if ($placement === null) {
            \flash('error', 'Placement not found.');
            \redirect('coordinator/grades');
        }
        $breakdown = $this->breakdown($placement);
        $mark = Database::one(
            'SELECT m.*, g.grade_letter, g.classification FROM mark m LEFT JOIN grade_scale g ON g.grade_id = m.grade_id WHERE m.placement_id = ?',
            [$id]
        );
        $suggested = $breakdown['complete'] ? $this->gradeFor($breakdown['weighted']) : null;
        $weights = $this->weights();

        View::render('coordinator/grade_view', [
            'title' => 'Grade sheet — ' . $placement['reg_no'],
            'placement' => $placement,
            'breakdown' => $breakdown,
            'mark' => $mark,
            'suggested' => $suggested,
            'weights' => $weights,
        ]);
    }

    public function compute(): void
    {
        $id = (int) \post('placement_id', 0);
        $placement = $this->findPlacement($id);
        if ($placement === null) {
            http_response_code(403);
            View::error(403);
            return;
        }
        $breakdown = $this->breakdown($placement);
        if (!$breakdown['complete']) {
            \flash('error', 'Missing components: ' . implode(', ', $breakdown['missing']) . '. Complete them before computing the mark.');
            \redirect('coordinator/grades/view', ['placement_id' => $id]);
        }
        $existing = Database::one('SELECT status FROM mark WHERE placement_id = ?', [$id]);
        if ($existing !== null && $existing['status'] === 'published') {
            \flash('error', 'A published mark cannot be recomputed.');
            \redirect('coordinator/grades/view', ['placement_id' => $id]);
        }
        $grade = $this->gradeFor($breakdown['weighted']);
        $gradeId = $grade === null ? null : (int) Database::val('SELECT grade_id FROM grade_scale WHERE grade_letter = ?', [$grade['grade_letter']]);

        Database::tx(static function () use ($id, $breakdown, $gradeId): void {
            $existing = Database::one('SELECT * FROM mark WHERE placement_id = ?', [$id]);
            $data = [
                'logbook_pct' => $breakdown['logbook'],
                'supervisor_pct' => $breakdown['supervisor'],
                'self_pct' => $breakdown['self'],
                'practical_pct' => $breakdown['practical'],
                'report_pct' => $breakdown['report'],
                'career_fair_pct' => $breakdown['career_fair'],
                'weighted_total' => $breakdown['weighted'],
                'grade_id' => $gradeId,
                'status' => 'draft',
                'computed_by' => (int) Auth::id(),
                'computed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($existing === null) {
                $markId = Database::insert('mark', $data + ['placement_id' => $id, 'created_at' => date('Y-m-d H:i:s')]);
                Audit::log('mark.computed', 'mark', $markId, null, $data);
            } else {
                if ($existing['status'] === 'published') {
                    throw new \RuntimeException('A published mark cannot be recomputed.');
                }
                Database::update('mark', $data, 'mark_id = ?', [(int) $existing['mark_id']]);
                Audit::log('mark.computed', 'mark', (int) $existing['mark_id'], $existing, $data);
            }
        });

        \flash('success', 'Mark computed from the source components for ' . $placement['academic_year'] . '.');
        \redirect('coordinator/grades/view', ['placement_id' => $id]);
    }

    public function careerFair(): void
    {
        $id = (int) \post('placement_id', 0);
        $placement = $this->findPlacement($id);
        if ($placement === null) {
            http_response_code(403);
            View::error(403);
            return;
        }
        $mark = Database::one('SELECT status FROM mark WHERE placement_id = ?', [$id]);
        if ($mark !== null && $mark['status'] === 'published') {
            \flash('error', 'A published mark keeps its career fair score.');
            \redirect('coordinator/grades/view', ['placement_id' => $id]);
        }
        $raw = trim((string) \post('career_fair_score', ''));
        if ($raw === '' || !Validator::decimal($raw, 0, 100)) {
            \flash('error', 'Enter a career fair score between 0 and 100.');
            \redirect('coordinator/grades/view', ['placement_id' => $id]);
        }
        $value = round((float) $raw, 2);
        $previous = $placement['career_fair_score'];

        Database::tx(static function () use ($id, $value, $previous): void {
            Database::update('placement', [
                'career_fair_score' => $value,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'placement_id = ?', [$id]);
            Audit::log('placement.career_fair_scored', 'placement', $id, ['career_fair_score' => $previous], ['career_fair_score' => $value]);
        });

        \flash('success', 'Career fair score recorded. It counts 25 of the 200 final marks.');
        \redirect('coordinator/grades/view', ['placement_id' => $id]);
    }

    public function moderate(): void
    {
        $id = (int) \post('placement_id', 0);
        $placement = $this->findPlacement($id);
        if ($placement === null) {
            http_response_code(403);
            View::error(403);
            return;
        }
        $mark = Database::one('SELECT * FROM mark WHERE placement_id = ?', [$id]);
        if ($mark === null) {
            \flash('error', 'Compute the mark before moderating it.');
            \redirect('coordinator/grades/view', ['placement_id' => $id]);
        }
        if ($mark['status'] !== 'draft') {
            \flash('error', 'Only draft marks can be moderated.');
            \redirect('coordinator/grades/view', ['placement_id' => $id]);
        }
        $reason = trim((string) \post('moderation_reason', ''));
        if (!Validator::length($reason, 10, 255)) {
            \flash('error', 'Record a moderation reason of at least ten characters.');
            \redirect('coordinator/grades/view', ['placement_id' => $id]);
        }

        Database::tx(static function () use ($mark, $reason): void {
            Database::update('mark', [
                'status' => 'moderated',
                'moderated_by' => (int) Auth::id(),
                'moderated_at' => date('Y-m-d H:i:s'),
                'moderation_reason' => $reason,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'mark_id = ?', [(int) $mark['mark_id']]);
            Audit::log('mark.moderated', 'mark', (int) $mark['mark_id'], [
                'status' => $mark['status'],
                'weighted_total' => $mark['weighted_total'],
            ], [
                'status' => 'moderated',
                'reason' => $reason,
            ]);
        });

        \flash('success', 'Mark moderated. It can now be published.');
        \redirect('coordinator/grades/view', ['placement_id' => $id]);
    }

    public function publish(): void
    {
        $id = (int) \post('placement_id', 0);
        $placement = $this->findPlacement($id);
        if ($placement === null) {
            http_response_code(403);
            View::error(403);
            return;
        }
        $mark = Database::one('SELECT m.*, g.grade_letter FROM mark m LEFT JOIN grade_scale g ON g.grade_id = m.grade_id WHERE m.placement_id = ?', [$id]);
        if ($mark === null) {
            \flash('error', 'There is no mark to publish.');
            \redirect('coordinator/grades/view', ['placement_id' => $id]);
        }
        if ($mark['status'] !== 'moderated') {
            \flash('error', 'Moderate the mark before publishing it.');
            \redirect('coordinator/grades/view', ['placement_id' => $id]);
        }

        Database::tx(static function () use ($mark, $placement): void {
            Database::update('mark', [
                'status' => 'published',
                'published_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'mark_id = ?', [(int) $mark['mark_id']]);
            Audit::log('mark.published', 'mark', (int) $mark['mark_id'], ['status' => 'moderated'], [
                'status' => 'published',
                'weighted_total' => $mark['weighted_total'],
                'grade' => $mark['grade_letter'],
            ]);
            Notify::push(
                (int) $placement['student_user'],
                'mark',
                'Final results published',
                'Your final mark for ' . $placement['org_name'] . ' is published. Open the results page to see the breakdown.',
                'index.php?r=student/results'
            );
            Notify::toRole('management', 'mark', 'Mark published', 'A final mark was published for ' . $placement['reg_no'] . ' (' . $mark['grade_letter'] . ').', 'index.php?r=management/kpis');
        });

        \flash('success', 'Mark published and the student notified.');
        \redirect('coordinator/grades/view', ['placement_id' => $id]);
    }

    private function findPlacement(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return Database::one(
            'SELECT p.*, u.first_name, u.last_name, s.reg_no, s.user_id AS student_user, o.name AS org_name, o.district, o.address,
                    m.first_name AS mentor_first, m.last_name AS mentor_last,
                    sup.first_name AS sup_first, sup.last_name AS sup_last
             FROM placement p
             JOIN student s ON s.student_id = p.student_id
             JOIN users u ON u.user_id = s.user_id
             JOIN organisation o ON o.org_id = p.org_id
             JOIN users m ON m.user_id = p.mentor_id
             LEFT JOIN users sup ON sup.user_id = p.supervisor_id
             WHERE p.placement_id = ?',
            [$id]
        );
    }

    private function breakdown(array $placement): array
    {
        $pid = (int) $placement['placement_id'];
        $approved = (int) Database::val(
            'SELECT COUNT(*) FROM logbook_entry WHERE placement_id = ? AND status IN (?,?)',
            [$pid, 'approved', 'mentor_reviewed']
        );
        $totalWeeks = max(1, (int) $placement['total_weeks']);
        $logbook = min(100, round($approved / $totalWeeks * 100, 2));

        $supervisor = Database::one(
            'SELECT total_score, status FROM evaluation WHERE placement_id = ? AND evaluation_type = ? ORDER BY evaluation_id DESC LIMIT 1',
            [$pid, 'supervisor']
        );
        $self = Database::one(
            'SELECT total_score, status FROM evaluation WHERE placement_id = ? AND evaluation_type = ? ORDER BY evaluation_id DESC LIMIT 1',
            [$pid, 'self']
        );
        $mentor = Database::one(
            'SELECT total_score, status FROM evaluation WHERE placement_id = ? AND evaluation_type = ? ORDER BY evaluation_id DESC LIMIT 1',
            [$pid, 'mentor']
        );
        $report = Database::one('SELECT score, status FROM internship_report WHERE placement_id = ?', [$pid]);
        $careerFair = $placement['career_fair_score'] === null ? null : (float) $placement['career_fair_score'];

        $missing = [];
        if ($supervisor === null || $supervisor['status'] !== 'submitted') {
            $missing[] = 'host supervisor evaluation (Form D)';
        }
        if ($self === null || $self['status'] !== 'submitted') {
            $missing[] = 'student self evaluation';
        }
        if ($report === null || $report['status'] !== 'assessed') {
            $missing[] = 'assessed internship report';
        }
        if ($careerFair === null) {
            $missing[] = 'career fair score';
        }

        $weights = $this->weights();
        $sv = $supervisor !== null && $supervisor['status'] === 'submitted' ? (float) $supervisor['total_score'] : 0.0;
        $se = $self !== null && $self['status'] === 'submitted' ? (float) $self['total_score'] : 0.0;
        $re = $report !== null && $report['status'] === 'assessed' ? (float) $report['score'] : 0.0;
        $cf = $careerFair ?? 0.0;

        $practicalWeight = $weights['logbook'] + $weights['supervisor'] + $weights['self'];
        $practical = $practicalWeight > 0
            ? round(($logbook * $weights['logbook'] + $sv * $weights['supervisor'] + $se * $weights['self']) / $practicalWeight, 2)
            : 0.0;
        $practicalMarks = 100.0;
        $totalMarks = $practicalMarks + $weights['report'] + $weights['career_fair'];
        $weighted = $totalMarks > 0
            ? round(($practical * $practicalMarks + $re * $weights['report'] + $cf * $weights['career_fair']) / $totalMarks, 2)
            : 0.0;

        return [
            'logbook' => $logbook,
            'supervisor' => round($sv, 2),
            'self' => round($se, 2),
            'practical' => $practical,
            'mentor' => $mentor !== null && $mentor['status'] === 'submitted' ? round((float) $mentor['total_score'], 2) : null,
            'report' => round($re, 2),
            'career_fair' => $careerFair,
            'weighted' => $weighted,
            'complete' => $missing === [],
            'missing' => $missing,
            'approved_weeks' => $approved,
            'total_weeks' => $totalWeeks,
            'supervisor_status' => $supervisor['status'] ?? 'missing',
            'self_status' => $self['status'] ?? 'missing',
            'mentor_status' => $mentor['status'] ?? 'missing',
            'report_status' => $report['status'] ?? 'missing',
        ];
    }

    private function weights(): array
    {
        return [
            'logbook' => (float) Settings::get('weight_logbook', 50),
            'supervisor' => (float) Settings::get('weight_supervisor', 40),
            'self' => (float) Settings::get('weight_self', 10),
            'report' => (float) Settings::get('weight_report', 75),
            'career_fair' => (float) Settings::get('weight_career_fair', 25),
        ];
    }

    private function gradeFor(float $score): ?array
    {
        return Database::one(
            'SELECT * FROM grade_scale WHERE ? >= min_score AND ? <= max_score ORDER BY min_score DESC LIMIT 1',
            [$score, $score]
        );
    }

    private function year(): string
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
