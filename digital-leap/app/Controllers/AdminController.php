<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\Mailer;
use Leap\Core\Settings;
use Leap\Core\Validator;
use Leap\Core\View;

final class AdminController
{
    public function users(): void
    {
        $roleId = (int) \qget('role_id', 0);
        $status = (string) \qget('status', '');
        $locked = (string) \qget('locked', '');
        $search = trim((string) \qget('q', ''));

        $sql = 'SELECT u.*, r.role_key, r.role_name, o.name AS org_name,
                       (SELECT COUNT(*) FROM access_token t
                         WHERE t.user_id = u.user_id AND t.used_at IS NULL AND t.expires_at > NOW()) AS open_tokens
                FROM users u
                JOIN roles r ON r.role_id = u.role_id
                LEFT JOIN organisation o ON o.org_id = u.organisation_id
                WHERE 1 = 1';
        $params = [];
        if ($roleId > 0) {
            $sql .= ' AND u.role_id = ?';
            $params[] = $roleId;
        }
        if (in_array($status, ['invited', 'active', 'suspended'], true)) {
            $sql .= ' AND u.status = ?';
            $params[] = $status;
        }
        if ($locked === '1') {
            $sql .= ' AND u.locked_until IS NOT NULL AND u.locked_until > NOW()';
        }
        if ($search !== '') {
            $sql .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }
        $sql .= ' ORDER BY u.last_name, u.first_name';

        View::render('admin/users', [
            'title' => 'User accounts',
            'users' => Database::all($sql, $params),
            'roles' => Database::all('SELECT role_id, role_name FROM roles ORDER BY role_id'),
            'roleId' => $roleId,
            'status' => $status,
            'locked' => $locked,
            'search' => $search,
            'selfId' => (int) Auth::id(),
        ]);
    }

    public function userForm(): void
    {
        $id = (int) \qget('id', 0);
        $user = null;
        $student = null;
        if ($id > 0) {
            $user = Database::one('SELECT * FROM users WHERE user_id = ?', [$id]);
            if ($user === null) {
                \flash('error', 'That account does not exist.');
                \redirect('admin/users');
            }
            $student = Database::one('SELECT * FROM student WHERE user_id = ?', [$id]);
        }

        View::render('admin/user_form', [
            'title' => $user === null ? 'Create account' : 'Edit account',
            'user' => $user,
            'student' => $student,
            'roles' => Database::all('SELECT role_id, role_key, role_name FROM roles ORDER BY role_id'),
            'orgs' => Database::all('SELECT org_id, name FROM organisation ORDER BY name'),
        ]);
    }

    public function userSave(): void
    {
        $id = (int) \post('user_id', 0);
        $first = trim((string) \post('first_name', ''));
        $last = trim((string) \post('last_name', ''));
        $email = strtolower(trim((string) \post('email', '')));
        $phone = trim((string) \post('phone', ''));
        $roleId = (int) \post('role_id', 0);
        $orgId = (int) \post('organisation_id', 0);
        $password = (string) \post('password', '');

        $role = Database::one('SELECT * FROM roles WHERE role_id = ?', [$roleId]);
        $existing = $id > 0 ? Database::one('SELECT * FROM users WHERE user_id = ?', [$id]) : null;
        if ($id > 0 && $existing === null) {
            \flash('error', 'That account does not exist.');
            \redirect('admin/users');
        }

        $errors = [];
        if (!Validator::length($first, 2, 60)) {
            $errors[] = 'Enter a first name of at least two characters.';
        }
        if (!Validator::length($last, 2, 60)) {
            $errors[] = 'Enter a surname of at least two characters.';
        }
        if (!Validator::email($email)) {
            $errors[] = 'Enter a valid email address.';
        } elseif ((int) Database::val('SELECT COUNT(*) FROM users WHERE email = ? AND user_id <> ?', [$email, $id]) > 0) {
            $errors[] = 'Another account already uses that email address.';
        }
        if ($role === null) {
            $errors[] = 'Choose a role for the account.';
        }
        if ($role !== null && $role['role_key'] === 'supervisor' && $orgId <= 0) {
            $errors[] = 'A host supervisor must belong to a host organisation.';
        }
        if ($id > 0 && (int) $existing['user_id'] === (int) Auth::id() && $role !== null && (int) $existing['role_id'] !== $roleId) {
            $errors[] = 'You cannot change the role on your own account.';
        }

        $isStudent = $role !== null && $role['role_key'] === 'student';
        $regNo = trim((string) \post('reg_no', ''));
        $programme = trim((string) \post('programme', ''));
        if ($isStudent) {
            if (!Validator::length($regNo, 4, 20)) {
                $errors[] = 'Enter the student registration number.';
            } elseif ((int) Database::val(
                'SELECT COUNT(*) FROM student WHERE reg_no = ? AND user_id <> ?',
                [$regNo, $id]
            ) > 0) {
                $errors[] = 'That registration number is already recorded.';
            }
            if ($programme === '') {
                $errors[] = 'Choose the student programme.';
            }
        } elseif ($id > 0 && (int) Database::val('SELECT COUNT(*) FROM student WHERE user_id = ?', [$id]) > 0) {
            $errors[] = 'That account has a student record, so the student role cannot be removed.';
        }

        $invite = $id <= 0 && $role !== null && $role['role_key'] === 'supervisor' && $password === '';
        if ($id <= 0 && !$invite && \password_policy_error($password) !== null) {
            $errors[] = 'Set an initial password of at least eight characters with an uppercase letter, a lowercase letter and a number.';
        }

        if ($errors !== []) {
            foreach ($errors as $message) {
                \flash('error', $message);
            }
            \redirect('admin/users/form', $id > 0 ? ['id' => $id] : []);
        }

        if ($id > 0) {
            $this->updateUser($existing, $first, $last, $email, $phone, $roleId, $isStudent, $orgId);
            \flash('success', 'Account details updated.');
            \redirect('admin/users');
        }
        $newId = Database::tx(static function () use ($first, $last, $email, $phone, $roleId, $role, $orgId, $password, $isStudent, $regNo, $programme): int {
            $data = [
                'role_id' => $roleId,
                'organisation_id' => $orgId > 0 ? $orgId : null,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'phone' => $phone === '' ? null : $phone,
                'status' => 'active',
                'must_change_password' => 1,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($role['role_key'] === 'supervisor' && $password === '') {
                $data['status'] = 'invited';
                $data['password_hash'] = null;
            }
            $userId = Database::insert('users', $data);

            if ($isStudent) {
                Database::insert('student', [
                    'user_id' => $userId,
                    'reg_no' => $regNo,
                    'programme' => $programme,
                    'level' => (string) \post('level', 'Year 3'),
                    'semester' => (string) \post('semester', 'Semester 5'),
                    'academic_year' => date('Y') . '/' . (date('Y') + 1),
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            if ($data['status'] === 'invited') {
                $token = bin2hex(random_bytes(32));
                Database::insert('access_token', [
                    'user_id' => $userId,
                    'purpose' => 'supervisor_access',
                    'token_hash' => hash('sha256', $token),
                    'expires_at' => date('Y-m-d H:i:s', time() + 604800),
                    'created_by' => (int) Auth::id(),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                Database::insert('email_queue', [
                    'user_id' => $userId,
                    'to_email' => $email,
                    'subject' => 'Activate your Digital LEAP account',
                    'body' => 'Hello ' . $first . ', open this link within seven days to set your password: '
                        . \url('token/supervisor', ['token' => $token]),
                    'status' => 'queued',
                    'attempts' => 0,
                    'scheduled_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            Audit::log('user.created', 'user', $userId, null, [
                'email' => $email,
                'role' => $role['role_key'],
                'organisation_id' => $orgId > 0 ? $orgId : null,
                'status' => $data['status'],
            ]);
            return $userId;
        });

        \flash('success', $invite
            ? 'Supervisor account created as invited. The activation link is waiting in the mail queue.'
            : 'Account created. The user must change the password at first sign-in.');
        \redirect('admin/users/form', ['id' => $newId]);
    }

    public function toggle(): void
    {
        $id = (int) \post('user_id', 0);
        $user = Database::one('SELECT * FROM users WHERE user_id = ?', [$id]);
        if ($user === null) {
            \flash('error', 'That account does not exist.');
            \redirect('admin/users');
        }
        if ($id === (int) Auth::id()) {
            \flash('error', 'You cannot suspend your own account.');
            \redirect('admin/users');
        }
        if ($user['status'] === 'invited') {
            \flash('error', 'That account has not been activated yet. Resend the invitation instead.');
            \redirect('admin/users');
        }

        $next = $user['status'] === 'active' ? 'suspended' : 'active';
        Database::tx(static function () use ($user, $id, $next): void {
            Database::update('users', [
                'status' => $next,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'user_id = ?', [$id]);
            Audit::log($next === 'suspended' ? 'user.suspended' : 'user.reactivated', 'user', $id, [
                'status' => $user['status'],
            ], ['status' => $next]);
        });

        \flash('success', 'Account ' . ($next === 'suspended' ? 'suspended' : 'reactivated') . '.');
        \redirect('admin/users');
    }

    public function unlock(): void
    {
        $id = (int) \post('user_id', 0);
        $user = Database::one('SELECT * FROM users WHERE user_id = ?', [$id]);
        if ($user === null) {
            \flash('error', 'That account does not exist.');
            \redirect('admin/users');
        }

        Database::tx(static function () use ($user, $id): void {
            Database::update('users', [
                'failed_attempts' => 0,
                'locked_until' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'user_id = ?', [$id]);
            Audit::log('user.unlocked', 'user', $id, [
                'failed_attempts' => (int) $user['failed_attempts'],
                'locked_until' => $user['locked_until'],
            ], ['failed_attempts' => 0, 'locked_until' => null]);
        });

        \flash('success', 'Failed attempts cleared and the lock removed.');
        \redirect('admin/users');
    }

    public function resetPassword(): void
    {
        $id = (int) \post('user_id', 0);
        $user = Database::one(
            'SELECT u.*, r.role_key FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ?',
            [$id]
        );
        if ($user === null) {
            \flash('error', 'That account does not exist.');
            \redirect('admin/users');
        }
        if ($user['status'] === 'suspended') {
            \flash('error', 'Reactivate the account before sending a reset link.');
            \redirect('admin/users');
        }

        $reactivate = $user['status'] === 'invited' || $user['password_hash'] === null;
        $purpose = $reactivate ? 'supervisor_access' : 'password_reset';
        $subject = $reactivate ? 'Activate your Digital LEAP account' : 'Reset your Digital LEAP password';
        $route = $reactivate ? 'token/supervisor' : 'reset';
        $minutes = $reactivate ? 10080 : 60;

        Database::tx(static function () use ($user, $id, $purpose, $subject, $route, $minutes): void {
            Database::q(
                'UPDATE access_token SET used_at = ? WHERE user_id = ? AND purpose = ? AND used_at IS NULL',
                [date('Y-m-d H:i:s'), $id, $purpose]
            );
            $token = bin2hex(random_bytes(32));
            Database::insert('access_token', [
                'user_id' => $id,
                'purpose' => $purpose,
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + $minutes * 60),
                'created_by' => (int) Auth::id(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Database::insert('email_queue', [
                'user_id' => $id,
                'to_email' => $user['email'],
                'subject' => $subject,
                'body' => 'Hello ' . $user['first_name'] . ', open this link to continue: '
                    . \url($route, ['token' => $token]),
                'status' => 'queued',
                'attempts' => 0,
                'scheduled_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Audit::log('user.' . ($reactivate ? 'invitation_resent' : 'password_reset_sent'), 'user', $id);
        });

        \flash('success', 'A single-use link has been queued. Copy it from the mail queue to hand it to the user.');
        \redirect('admin/users');
    }

    public function roles(): void
    {
        $roles = Database::all('SELECT * FROM roles ORDER BY role_id');
        $permissions = Database::all('SELECT * FROM permissions ORDER BY permission_id');
        $granted = [];
        foreach (Database::all('SELECT role_id, permission_id FROM role_permissions') as $row) {
            $granted[(int) $row['role_id']][(int) $row['permission_id']] = true;
        }

        View::render('admin/roles', [
            'title' => 'Role and permission matrix',
            'roles' => $roles,
            'permissions' => $permissions,
            'granted' => $granted,
        ]);
    }

    public function settings(): void
    {
        $rows = Database::all('SELECT * FROM setting ORDER BY setting_key');

        View::render('admin/settings', [
            'title' => 'System settings',
            'settings' => $rows,
        ]);
    }

    public function settingsSave(): void
    {
        $submitted = $_POST['setting'] ?? [];
        if (!is_array($submitted)) {
            $submitted = [];
        }
        $current = [];
        foreach (Database::all('SELECT * FROM setting') as $row) {
            $current[$row['setting_key']] = $row;
        }

        $errors = [];
        $updates = [];
        foreach ($current as $key => $row) {
            if (!array_key_exists($key, $submitted)) {
                continue;
            }
            $value = trim((string) $submitted[$key]);
            if ($value === '') {
                $errors[] = 'The value for ' . $key . ' cannot be empty.';
                continue;
            }
            if ($row['data_type'] === 'int' || $row['data_type'] === 'decimal') {
                $min = $row['data_type'] === 'decimal' ? 0.0 : 0;
                $max = $row['data_type'] === 'decimal' ? 100.0 : 604800;
                if (!Validator::decimal($value, (float) $min, (float) $max)) {
                    $errors[] = 'The value for ' . $key . ' must be a number between ' . $min . ' and ' . $max . '.';
                    continue;
                }
            }
            if (mb_strlen($value) > 255) {
                $errors[] = 'The value for ' . $key . ' is too long.';
                continue;
            }
            if ($value !== (string) $row['setting_value']) {
                $updates[$key] = $value;
            }
        }

        if (isset($updates['logbook_due_day']) && !in_array($updates['logbook_due_day'], ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], true)) {
            $errors[] = 'The logbook due day must be a weekday name.';
        }
        if (isset($updates['mail_mode']) && !in_array($updates['mail_mode'], ['log', 'smtp'], true)) {
            $errors[] = 'Mail mode must be log or smtp.';
        }
        if (isset($updates['session_idle_minutes']) && !Validator::int($updates['session_idle_minutes'], 5, 120)) {
            $errors[] = 'The idle timeout must be between 5 and 120 minutes.';
        }
        if (isset($updates['max_login_attempts']) && !Validator::int($updates['max_login_attempts'], 3, 10)) {
            $errors[] = 'Failed attempts before lockout must be between 3 and 10.';
        }
        if (isset($updates['lockout_minutes']) && !Validator::int($updates['lockout_minutes'], 5, 60)) {
            $errors[] = 'The lockout duration must be between 5 and 60 minutes.';
        }
        if (isset($updates['reminder_lead_days']) && !Validator::int($updates['reminder_lead_days'], 0, 7)) {
            $errors[] = 'Reminder lead days must be between 0 and 7.';
        }

        $practicalWeights = ['weight_logbook', 'weight_supervisor', 'weight_self'];
        $sum = 0.0;
        foreach ($practicalWeights as $key) {
            $value = $updates[$key] ?? ($current[$key]['setting_value'] ?? '0');
            $sum += (float) $value;
        }
        if (abs($sum - 100.0) > 0.001) {
            $errors[] = 'The practical weights (logbook, Form D, self evaluation) must add up to exactly 100 (currently ' . number_format($sum, 2) . ').';
        }

        if ($errors !== []) {
            foreach ($errors as $message) {
                \flash('error', $message);
            }
            \redirect('admin/settings');
        }

        if ($updates === []) {
            \flash('info', 'No setting values changed.');
            \redirect('admin/settings');
        }

        Database::tx(static function () use ($updates, $current): void {
            foreach ($updates as $key => $value) {
                Database::update('setting', [
                    'setting_value' => $value,
                    'updated_by' => (int) Auth::id(),
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'setting_key = ?', [$key]);
                Audit::log('setting.updated', 'setting', null, [
                    'key' => $key,
                    'value' => $current[$key]['setting_value'],
                ], ['key' => $key, 'value' => $value]);
            }
        });

        \flash('success', count($updates) . ' setting(s) updated. Mark weights apply at the next computation.');
        \redirect('admin/settings');
    }

    public function audit(): void
    {
        $action = trim((string) \qget('action', ''));
        $entity = trim((string) \qget('entity_type', ''));
        $userId = (int) \qget('user_id', 0);
        $from = trim((string) \qget('from', ''));
        $to = trim((string) \qget('to', ''));
        $page = max(1, (int) \qget('page', 1));
        $perPage = 40;

        $where = ' WHERE 1 = 1';
        $params = [];
        if ($action !== '') {
            $where .= ' AND a.action LIKE ?';
            $params[] = '%' . $action . '%';
        }
        if ($entity !== '') {
            $where .= ' AND a.entity_type = ?';
            $params[] = $entity;
        }
        if ($userId > 0) {
            $where .= ' AND a.user_id = ?';
            $params[] = $userId;
        }
        if (Validator::date($from)) {
            $where .= ' AND a.created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if (Validator::date($to)) {
            $where .= ' AND a.created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }

        $total = (int) Database::val('SELECT COUNT(*) FROM audit_log a' . $where, $params);
        $rows = Database::all(
            'SELECT a.*, u.first_name, u.last_name
             FROM audit_log a LEFT JOIN users u ON u.user_id = a.user_id' . $where . '
             ORDER BY a.audit_id DESC
             LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );

        View::render('admin/audit', [
            'title' => 'Audit log',
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'action' => $action,
            'entity' => $entity,
            'userId' => $userId,
            'from' => $from,
            'to' => $to,
            'entities' => Database::all('SELECT DISTINCT entity_type FROM audit_log WHERE entity_type IS NOT NULL ORDER BY entity_type'),
            'actions' => Database::all('SELECT DISTINCT action FROM audit_log ORDER BY action'),
            'staff' => Database::all('SELECT user_id, first_name, last_name FROM users ORDER BY last_name'),
        ]);
    }

    public function auditView(): void
    {
        $id = (int) \qget('id', 0);
        $row = Database::one(
            'SELECT a.*, u.first_name, u.last_name, u.email
             FROM audit_log a LEFT JOIN users u ON u.user_id = a.user_id WHERE a.audit_id = ?',
            [$id]
        );
        if ($row === null) {
            \flash('error', 'That audit entry does not exist.');
            \redirect('admin/audit');
        }
        $before = $row['before_json'] === null ? [] : (array) json_decode((string) $row['before_json'], true);
        $after = $row['after_json'] === null ? [] : (array) json_decode((string) $row['after_json'], true);
        $changes = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old !== $new) {
                $changes[] = ['key' => $key, 'before' => $old, 'after' => $new];
            }
        }

        View::render('admin/audit_view', [
            'title' => 'Audit entry ' . $id,
            'row' => $row,
            'before' => $before,
            'after' => $after,
            'changes' => $changes,
        ]);
    }

    public function mail(): void
    {
        $status = (string) \qget('status', '');
        $sql = 'SELECT e.*, u.first_name, u.last_name FROM email_queue e LEFT JOIN users u ON u.user_id = e.user_id';
        $params = [];
        if (in_array($status, ['queued', 'sent', 'failed'], true)) {
            $sql .= ' WHERE e.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY e.queue_id DESC LIMIT 200';

        View::render('admin/mail', [
            'title' => 'Mail queue',
            'mails' => Database::all($sql, $params),
            'status' => $status,
            'counts' => [
                'queued' => (int) Database::val('SELECT COUNT(*) FROM email_queue WHERE status = ?', ['queued']),
                'sent' => (int) Database::val('SELECT COUNT(*) FROM email_queue WHERE status = ?', ['sent']),
                'failed' => (int) Database::val('SELECT COUNT(*) FROM email_queue WHERE status = ?', ['failed']),
            ],
        ]);
    }

    public function mailResend(): void
    {
        $id = (int) \post('queue_id', 0);
        $mail = Database::one('SELECT * FROM email_queue WHERE queue_id = ?', [$id]);
        if ($mail === null) {
            \flash('error', 'That queued message does not exist.');
            \redirect('admin/mail');
        }
        if ($mail['status'] === 'queued') {
            \flash('info', 'That message is already queued.');
            \redirect('admin/mail');
        }

        Database::tx(static function () use ($mail, $id): void {
            Database::update('email_queue', [
                'status' => 'queued',
                'attempts' => 0,
                'last_error' => null,
                'scheduled_at' => date('Y-m-d H:i:s'),
            ], 'queue_id = ?', [$id]);
            Audit::log('mail.requeued', 'email_queue', $id, ['status' => $mail['status']], ['status' => 'queued']);
        });

        \flash('success', 'Message returned to the queue.');
        \redirect('admin/mail');
    }

    public function mailProcess(): void
    {
        $queued = Database::all(
            'SELECT * FROM email_queue WHERE status = ? AND scheduled_at <= ? ORDER BY queue_id LIMIT 50',
            ['queued', date('Y-m-d H:i:s')]
        );

        $sent = 0;
        $failed = 0;
        foreach ($queued as $mail) {
            [$ok, $error] = Mailer::send((string) $mail['to_email'], (string) $mail['subject'], (string) $mail['body']);
            $data = [
                'attempts' => (int) $mail['attempts'] + 1,
                'status' => $ok ? 'sent' : 'failed',
                'last_error' => $ok ? null : (string) $error,
                'sent_at' => $ok ? date('Y-m-d H:i:s') : null,
            ];
            Database::update('email_queue', $data, 'queue_id = ?', [(int) $mail['queue_id']]);
            if ($ok) {
                $sent++;
            } else {
                $failed++;
            }
        }

        Audit::log('mail.processed', 'email_queue', null, null, ['sent' => $sent, 'failed' => $failed, 'candidates' => count($queued)]);

        if ($queued === []) {
            \flash('info', 'No queued messages were due for delivery.');
        } else {
            \flash($failed > 0 ? 'error' : 'success', $sent . ' sent and ' . $failed . ' failed out of ' . count($queued) . ' processed.');
        }
        \redirect('admin/mail');
    }

    private function updateUser(array $existing, string $first, string $last, string $email, string $phone, int $roleId, bool $isStudent, int $orgId): void
    {
        $id = (int) $existing['user_id'];
        $data = [
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'phone' => $phone === '' ? null : $phone,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id !== (int) Auth::id()) {
            $data['role_id'] = $roleId;
            $data['organisation_id'] = $orgId > 0 ? $orgId : null;
        }
        $studentData = [
            'reg_no' => trim((string) \post('reg_no', '')),
            'programme' => trim((string) \post('programme', '')),
            'level' => (string) \post('level', 'Year 3'),
            'semester' => (string) \post('semester', 'Semester 5'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        Database::tx(static function () use ($existing, $id, $data, $isStudent, $studentData): void {
            Database::update('users', $data, 'user_id = ?', [$id]);
            Audit::log('user.updated', 'user', $id,
                array_intersect_key($existing, $data),
                array_intersect_key($data, $existing)
            );

            $student = Database::one('SELECT * FROM student WHERE user_id = ?', [$id]);
            if ($isStudent && $student === null) {
                Database::insert('student', $studentData + [
                    'user_id' => $id,
                    'academic_year' => date('Y') . '/' . (date('Y') + 1),
                    'status' => 'active',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } elseif ($isStudent && $student !== null) {
                Database::update('student', $studentData, 'student_id = ?', [(int) $student['student_id']]);
            }
        });
    }
}
