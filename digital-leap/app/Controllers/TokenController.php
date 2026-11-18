<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Database;
use Leap\Core\View;

final class TokenController
{
    public function form(): void
    {
        $token = (string) \qget('token', '');
        $row = $this->lookup($token);

        if ($row === null) {
            \flash('error', 'That invitation link is invalid, already used, or has expired.');
            \redirect('login');
        }

        View::render('auth/token', [
            'title' => 'Activate your supervisor account',
            'token' => $token,
            'user' => $row,
            'errors' => [],
        ]);
    }

    public function consume(): void
    {
        $token = (string) \post('token', '');
        $password = (string) \post('password', '');
        $confirm = (string) \post('password_confirm', '');
        $row = $this->lookup($token);

        if ($row === null) {
            \flash('error', 'That invitation link is invalid, already used, or has expired.');
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
            View::render('auth/token', [
                'title' => 'Activate your supervisor account',
                'token' => $token,
                'user' => $row,
                'errors' => $errors,
            ]);
            return;
        }

        $userId = (int) $row['user_id'];
        Database::tx(static function () use ($row, $password, $userId): void {
            Database::update('users', [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'status' => 'active',
                'must_change_password' => 0,
                'failed_attempts' => 0,
                'locked_until' => null,
            ], 'user_id = ?', [$userId]);
            Database::update('access_token', ['used_at' => date('Y-m-d H:i:s')], 'token_id = ?', [(int) $row['token_id']]);
            Audit::log('supervisor.activated', 'user', $userId);
        });

        \flash('success', 'Your account is active. Sign in with your email address and new password.');
        \redirect('login');
    }

    private function lookup(string $token): ?array
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $row = Database::one(
            'SELECT t.*, u.first_name, u.last_name, u.email
             FROM access_token t
             JOIN users u ON u.user_id = t.user_id
             WHERE t.token_hash = ? AND t.purpose = ? AND t.used_at IS NULL AND t.expires_at > ?',
            [hash('sha256', $token), 'supervisor_access', date('Y-m-d H:i:s')]
        );
        return $row ?: null;
    }
}
