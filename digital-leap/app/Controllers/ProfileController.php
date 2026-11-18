<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\Upload;
use Leap\Core\View;
use RuntimeException;

final class ProfileController
{
    public function show(): void
    {
        $userId = (int) Auth::id();
        $user = Database::one(
            'SELECT u.*, r.role_name, r.role_key, o.name AS org_name
             FROM users u JOIN roles r ON r.role_id = u.role_id
             LEFT JOIN organisation o ON o.org_id = u.organisation_id
             WHERE u.user_id = ?',
            [$userId]
        );
        $student = Database::one('SELECT * FROM student WHERE user_id = ?', [$userId]);
        $cv = null;
        if ($student !== null) {
            $cv = Database::one(
                "SELECT * FROM document WHERE student_id = ? AND doc_type = 'cv' ORDER BY uploaded_at DESC LIMIT 1",
                [(int) $student['student_id']]
            );
        }

        View::render('profile', [
            'title' => 'My profile',
            'user' => $user,
            'student' => $student,
            'cv' => $cv,
        ]);
    }

    public function save(): void
    {
        $first = trim((string) \post('first_name', ''));
        $last = trim((string) \post('last_name', ''));
        $phone = trim((string) \post('phone', ''));
        $current = (string) \post('current_password', '');
        $new = (string) \post('new_password', '');
        $confirm = (string) \post('new_password_confirm', '');

        $errors = [];
        if (mb_strlen($first) < 2 || mb_strlen($last) < 2) {
            $errors[] = 'Enter both your first name and surname.';
        }
        if ($new !== '') {
            if ($current === '') {
                $errors[] = 'Enter your current password to change it.';
            }
            if (($policy = \password_policy_error($new)) !== null) {
                $errors[] = $policy;
            }
            if ($new !== $confirm) {
                $errors[] = 'The new passwords do not match.';
            }
        }

        $user = Database::one('SELECT * FROM users WHERE user_id = ?', [(int) Auth::id()]);

        if ($errors === [] && $new !== '') {
            if ($user === null || !password_verify($current, (string) $user['password_hash'])) {
                $errors[] = 'Your current password is incorrect.';
            }
        }

        if ($errors !== []) {
            foreach ($errors as $message) {
                \flash('error', $message);
            }
            \redirect('profile');
        }

        $data = ['first_name' => $first, 'last_name' => $last, 'phone' => $phone === '' ? null : $phone];
        if ($new !== '') {
            $data['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
            $data['must_change_password'] = 0;
            $_SESSION['must_change_password'] = 0;
        }
        Database::update('users', $data, 'user_id = ?', [(int) Auth::id()]);

        $_SESSION['name'] = $first . ' ' . $last;
        Audit::log('profile.update', 'user', (int) Auth::id(), null, ['password_changed' => $new !== '']);
        \flash('success', 'Your profile has been updated.');
        \redirect('profile');
    }

    public function cv(): void
    {
        $return = (string) \post('return', '') === 'apply' ? 'student/apply' : 'profile';

        if (Auth::role() !== 'student') {
            \flash('error', 'Only students upload a CV.');
            \redirect('profile');
        }

        $student = Database::one('SELECT * FROM student WHERE user_id = ?', [(int) Auth::id()]);
        if ($student === null) {
            \flash('error', 'Your student record is not available yet.');
            \redirect('profile');
        }

        $previous = Database::one(
            "SELECT doc_id FROM document WHERE student_id = ? AND doc_type = 'cv' ORDER BY uploaded_at DESC LIMIT 1",
            [(int) $student['student_id']]
        );

        try {
            $docId = Upload::store($_FILES['cv'] ?? [], 'cv', ['student_id' => (int) $student['student_id']]);
            $file = Database::one('SELECT original_name, file_size FROM document WHERE doc_id = ?', [$docId]);
            Audit::log('document.upload', 'document', $docId, null, [
                'type' => 'cv',
                'replaced' => $previous !== null ? (int) $previous['doc_id'] : null,
            ]);
            $size = $file !== null
                ? ($file['file_size'] >= 1048576
                    ? number_format((int) $file['file_size'] / 1048576, 1) . ' MB'
                    : max(1, (int) round((int) $file['file_size'] / 1024)) . ' KB')
                : '';
            \flash('success', $previous !== null
                ? 'New CV received: ' . $file['original_name'] . ' (' . $size . '). Reviewers now see this file instead of your previous one.'
                : 'CV received: ' . $file['original_name'] . ' (' . $size . '). The internship office can now review your application.');
        } catch (RuntimeException $e) {
            \flash('error', $e->getMessage());
        }

        \redirect($return);
    }

    public function avatarUpload(): void
    {
        $maxBytes = 8 * 1024 * 1024;
        $file = $_FILES['avatar'] ?? [];
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($file === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            \flash('error', 'That photo is larger than the server accepts. Choose an image under 8 MB.');
            \redirect('profile');
        }
        if ($error === UPLOAD_ERR_NO_FILE) {
            \flash('error', 'Choose a JPG, PNG or WebP image first.');
            \redirect('profile');
        }
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            \flash('error', 'The photo is too large for the server. Choose an image under 8 MB.');
            \redirect('profile');
        }
        if ($error === UPLOAD_ERR_PARTIAL) {
            \flash('error', 'The upload was interrupted. Check your connection and try again.');
            \redirect('profile');
        }
        if ($error !== UPLOAD_ERR_OK) {
            \flash('error', 'The photo could not be uploaded. Try again with a JPG, PNG or WebP image.');
            \redirect('profile');
        }
        if ((int) $file['size'] <= 0) {
            \flash('error', 'That file is empty. Choose another image.');
            \redirect('profile');
        }
        if ((int) $file['size'] > $maxBytes) {
            \flash('error', 'The profile photo must be 8 MB or smaller.');
            \redirect('profile');
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        if (!in_array($ext, ['jpg', 'png', 'webp'], true)) {
            \flash('error', 'Only JPG, PNG and WebP profile photos are accepted.');
            \redirect('profile');
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $allowed = match ($ext) {
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            default => ['image/jpeg', 'image/pjpeg', 'image/jpg'],
        };
        if (!in_array($mime, $allowed, true) || @getimagesize((string) $file['tmp_name']) === false) {
            \flash('error', 'That file is not a valid image. Choose a JPG, PNG or WebP photo.');
            \redirect('profile');
        }

        $dir = STORAGE_PATH . '/uploads/avatars';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            \flash('error', 'The profile photo folder is not writable.');
            \redirect('profile');
        }

        $userId = (int) Auth::id();
        $target = $dir . '/' . $userId . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            \flash('error', 'The profile photo could not be stored. Check folder permissions.');
            \redirect('profile');
        }
        foreach (glob($dir . '/' . $userId . '.*') ?: [] as $old) {
            if ($old !== $target) {
                @unlink($old);
            }
        }

        Audit::log('profile.avatar', 'user', $userId, null, ['mime' => $mime, 'bytes' => (int) $file['size']]);
        \flash('success', 'Your profile photo has been updated.');
        \redirect('profile');
    }

    public function avatarRemove(): void
    {
        $userId = (int) Auth::id();
        $removed = false;
        foreach (glob(STORAGE_PATH . '/uploads/avatars/' . $userId . '.*') ?: [] as $file) {
            if (@unlink($file)) {
                $removed = true;
            }
        }

        if ($removed) {
            Audit::log('profile.avatar.remove', 'user', $userId);
            \flash('success', 'Your profile photo has been removed.');
        } else {
            \flash('error', 'There is no profile photo to remove.');
        }
        \redirect('profile');
    }

    public function avatar(): void
    {
        $targetId = (int) \qget('u', 0);
        if ($targetId < 1) {
            $targetId = (int) Auth::id();
        }
        $file = self::canViewAvatar($targetId) ? avatar_file($targetId) : null;
        if ($file === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'No profile photo.';
            return;
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($file));
        header('Cache-Control: private, max-age=300');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
    }

    /**
     * A viewer may load another user's photo only when they could already see
     * that person in the product: themselves, staff with directory access, or
     * someone recorded on the same placement, conversation or logbook entry.
     */
    public static function canViewAvatar(int $targetId): bool
    {
        $viewer = (int) Auth::id();
        if ($viewer < 1 || $targetId < 1) {
            return false;
        }
        if ($targetId === $viewer) {
            return true;
        }

        static $cache = [];
        if (array_key_exists($targetId, $cache)) {
            return $cache[$targetId];
        }

        $allowed = in_array(Auth::role(), ['admin', 'coordinator'], true);

        if (!$allowed) {
            $allowed = Database::val(
                'SELECT 1
                 FROM placement p
                 WHERE (p.mentor_id = ? OR p.supervisor_id = ? OR p.coordinator_id = ?
                        OR p.student_id IN (SELECT student_id FROM student WHERE user_id = ?))
                   AND (p.mentor_id = ? OR p.supervisor_id = ? OR p.coordinator_id = ?
                        OR p.student_id IN (SELECT student_id FROM student WHERE user_id = ?))
                 LIMIT 1',
                [$viewer, $viewer, $viewer, $viewer, $targetId, $targetId, $targetId, $targetId]
            ) !== null;
        }

        if (!$allowed) {
            $allowed = Database::val(
                'SELECT 1
                 FROM conversation_member a
                 JOIN conversation_member b ON b.conversation_id = a.conversation_id AND b.user_id = ?
                 WHERE a.user_id = ?
                 LIMIT 1',
                [$targetId, $viewer]
            ) !== null;
        }

        if (!$allowed) {
            $allowed = Database::val(
                'SELECT 1
                 FROM logbook_comment a
                 JOIN logbook_comment b ON b.entry_id = a.entry_id AND b.user_id = ?
                 WHERE a.user_id = ?
                 LIMIT 1',
                [$targetId, $viewer]
            ) !== null;
        }

        $cache[$targetId] = $allowed;
        return $allowed;
    }
}
