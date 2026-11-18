<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\Validator;
use Leap\Core\View;

final class AuthController
{
    public function landing(): void
    {
        if (Auth::check()) {
            \redirect('dashboard');
        }
        View::render('auth/landing', ['title' => 'Internship management made visible']);
    }

    public function loginForm(): void
    {
        if (Auth::check()) {
            \redirect('dashboard');
        }
        View::render('auth/login', [
            'title' => 'Sign in',
            'email' => (string) ($_SESSION['_last_email'] ?? ''),
        ]);
    }

    public function login(): void
    {
        $email = trim((string) \post('email', ''));
        $password = (string) \post('password', '');

        if ($email === '' || $password === '') {
            \flash('error', 'Enter your email address and password.');
            \redirect('login');
        }

        $_SESSION['_last_email'] = $email;
        $error = Auth::attempt($email, $password);

        if ($error !== '') {
            \flash('error', $error);
            \redirect('login');
        }

        unset($_SESSION['_last_email']);
        \redirect('dashboard');
    }

    public function logout(): void
    {
        Audit::log('logout', 'user', Auth::id());
        Auth::logout();
        if (!headers_sent()) {
            header('Clear-Site-Data: "cache"');
        }
        \flash('info', 'You have been signed out.');
        \redirect('login');
    }

    public function registerForm(): void
    {
        if (Auth::check()) {
            \redirect('dashboard');
        }
        View::render('auth/register', ['title' => 'Student registration', 'errors' => [], 'old' => []]);
    }

    public function register(): void
    {
        $first = trim((string) \post('first_name', ''));
        $last = trim((string) \post('last_name', ''));
        $email = strtolower(trim((string) \post('email', '')));
        $phone = trim((string) \post('phone', ''));
        $regNo = trim((string) \post('reg_no', ''));
        $programme = trim((string) \post('programme', ''));
        $level = trim((string) \post('level', ''));
        $semester = trim((string) \post('semester', ''));
        $password = (string) \post('password', '');
        $confirm = (string) \post('password_confirm', '');

        $errors = [];
        if (!Validator::length($first, 2, 60)) {
            $errors['first_name'] = 'Enter your first name.';
        }
        if (!Validator::length($last, 2, 60)) {
            $errors['last_name'] = 'Enter your surname.';
        }
        if (!Validator::email($email)) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif ((int) Database::val('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
            $errors['email'] = 'An account already uses that email address.';
        }
        if (!Validator::length($regNo, 4, 20)) {
            $errors['reg_no'] = 'Enter your registration number.';
        } elseif ((int) Database::val('SELECT COUNT(*) FROM student WHERE reg_no = ?', [$regNo]) > 0) {
            $errors['reg_no'] = 'That registration number is already registered.';
        }
        if ($programme === '') {
            $errors['programme'] = 'Choose your programme.';
        }
        if (($policy = \password_policy_error($password)) !== null) {
            $errors['password'] = $policy;
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }

        if ($errors !== []) {
            View::render('auth/register', [
                'title' => 'Student registration',
                'errors' => $errors,
                'old' => compact('first', 'last', 'email', 'phone', 'regNo', 'programme', 'level', 'semester'),
            ]);
            return;
        }

        $roleId = (int) Database::val('SELECT role_id FROM roles WHERE role_key = ?', ['student']);
        $year = date('Y') . '/' . (date('Y') + 1);

        $userId = Database::tx(static function () use ($first, $last, $email, $phone, $password, $regNo, $programme, $level, $semester, $roleId, $year): int {
            $id = Database::insert('users', [
                'role_id' => $roleId,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'phone' => $phone === '' ? null : $phone,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'status' => 'active',
                'must_change_password' => 0,
            ]);
            Database::insert('student', [
                'user_id' => $id,
                'reg_no' => $regNo,
                'programme' => $programme,
                'level' => $level === '' ? 'Year 3' : $level,
                'semester' => $semester === '' ? 'Semester 5' : $semester,
                'academic_year' => $year,
                'status' => 'active',
            ]);
            Audit::log('user.register', 'user', $id, null, ['email' => $email, 'reg_no' => $regNo]);
            return $id;
        });

        Audit::log('account.created', 'user', $userId);
        $user = Database::one(
            'SELECT u.*, r.role_key, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ?',
            [$userId]
        );
        if ($user !== null) {
            Auth::establish($user);
            Audit::log('login.success', 'user', $userId, null, ['via' => 'registration']);
            $_SESSION['_tour_prompt'] = 1;
            \flash('success', 'Your account is ready. Welcome to Digital LEAP.');
            \redirect('dashboard');
        }
        \flash('success', 'Your student account was created. Sign in to continue.');
        \redirect('login');
    }

    public function forgotForm(): void
    {
        View::render('auth/forgot', ['title' => 'Reset your password']);
    }

    public function forgot(): void
    {
        $email = strtolower(trim((string) \post('email', '')));
        $user = Database::one(
            'SELECT user_id, email, first_name FROM users WHERE email = ? AND status = ?',
            [$email, 'active']
        );

        if ($user !== null) {
            $token = bin2hex(random_bytes(32));
            Database::insert('access_token', [
                'user_id' => (int) $user['user_id'],
                'purpose' => 'password_reset',
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $link = \url('reset', ['token' => $token]);
            Database::insert('email_queue', [
                'user_id' => (int) $user['user_id'],
                'to_email' => $user['email'],
                'subject' => 'Reset your Digital LEAP password',
                'body' => 'Hello ' . $user['first_name'] . ', open this link within one hour to set a new password: ' . $link,
                'status' => 'queued',
                'attempts' => 0,
                'scheduled_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Audit::log('password.reset_requested', 'user', (int) $user['user_id']);
        }

        \flash('info', 'If that address is registered, a reset link has been sent.');
        \redirect('login');
    }

    public function resetForm(): void
    {
        $token = (string) \qget('token', '');
        $row = $this->validToken($token, 'password_reset');

        if ($row === null) {
            \flash('error', 'That reset link is invalid or has expired.');
            \redirect('login');
        }

        View::render('auth/reset', ['title' => 'Set a new password', 'token' => $token, 'errors' => []]);
    }

    public function reset(): void
    {
        $token = (string) \post('token', '');
        $password = (string) \post('password', '');
        $confirm = (string) \post('password_confirm', '');
        $row = $this->validToken($token, 'password_reset');

        if ($row === null) {
            \flash('error', 'That reset link is invalid or has expired.');
            \redirect('login');
        }

        $errors = [];
        if (($policy = \password_policy_error($password)) !== null) {
            $errors['password'] = $policy;
        }
        if ($password !== $confirm) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }

        if ($errors !== []) {
            View::render('auth/reset', ['title' => 'Set a new password', 'token' => $token, 'errors' => $errors]);
            return;
        }

        $userId = (int) $row['user_id'];
        Database::tx(static function () use ($row, $password, $userId): void {
            Database::update('users', [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'failed_attempts' => 0,
                'locked_until' => null,
            ], 'user_id = ?', [$userId]);
            Database::update('access_token', ['used_at' => date('Y-m-d H:i:s')], 'token_id = ?', [(int) $row['token_id']]);
            Database::q(
                'UPDATE access_token SET used_at = ? WHERE user_id = ? AND used_at IS NULL AND token_id <> ?',
                [date('Y-m-d H:i:s'), $userId, (int) $row['token_id']]
            );
            Audit::log('password.reset', 'user', $userId);
        });

        \flash('success', 'Your password has been changed. Sign in with the new password.');
        \redirect('login');
    }

    public function validToken(string $token, string $purpose): ?array
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $hash = hash('sha256', $token);
        $row = Database::one(
            'SELECT * FROM access_token WHERE token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > ?',
            [$hash, $purpose, date('Y-m-d H:i:s')]
        );
        return $row ?: null;
    }
}
