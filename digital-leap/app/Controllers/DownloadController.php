<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\View;

final class DownloadController
{
    public function file(): void
    {
        $docId = (int) \qget('id', 0);
        $doc = Database::one(
            'SELECT d.*, s.user_id AS student_user, p.mentor_id, p.supervisor_id, p.student_id AS placement_student,
                    v.mentor_id AS visit_mentor
             FROM document d
             LEFT JOIN student s ON s.student_id = d.student_id
             LEFT JOIN placement p ON p.placement_id = d.placement_id
             LEFT JOIN site_visit v ON v.visit_id = d.visit_id
             WHERE d.doc_id = ?',
            [$docId]
        );

        if ($doc === null) {
            http_response_code(404);
            View::error(404);
            return;
        }

        if (!$this->mayAccess($doc)) {
            Audit::log('document.denied', 'document', $docId, null, ['user_id' => Auth::id()]);
            http_response_code(403);
            View::error(403);
            return;
        }

        $path = STORAGE_PATH . '/uploads/' . basename((string) $doc['stored_name']);
        if (!is_file($path)) {
            http_response_code(404);
            View::error(404);
            return;
        }

        Audit::log('document.download', 'document', $docId);

        $reviewerRoles = ['mentor', 'supervisor', 'coordinator', 'management', 'admin'];
        if (in_array(Auth::role(), $reviewerRoles, true)
            && (int) ($doc['uploaded_by'] ?? 0) !== (int) Auth::id()
            && ((int) ($doc['reviewed_by'] ?? 0) !== (int) Auth::id() || $doc['reviewed_at'] === null)) {
            Database::update('document', [
                'reviewed_by' => (int) Auth::id(),
                'reviewed_at' => date('Y-m-d H:i:s'),
            ], 'doc_id = ?', [$docId]);
            Audit::log('document.reviewed', 'document', $docId, null, ['reviewer_id' => (int) Auth::id()]);
        }

        $inline = (string) \qget('inline', '') === '1'
            && in_array((string) $doc['mime_type'], ['application/pdf', 'image/jpeg', 'image/png'], true);

        header('Content-Type: ' . $doc['mime_type']);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode((string) $doc['original_name']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function logbookTemplate(): void
    {
        $this->sendTemplate(
            STORAGE_PATH . '/templates/internship_log_book.pdf',
            'Limkokwing_Internship_Log_Book.pdf',
            'logbook.template.download'
        );
    }

    public function careerFairTemplate(): void
    {
        $this->sendTemplate(
            STORAGE_PATH . '/templates/career_fair_2026_report_template.pdf',
            'Career_Fair_2026_Student_Participation_Report.pdf',
            'career_fair.template.download'
        );
    }

    public function guidebookPdf(): void
    {
        $this->sendTemplate(
            STORAGE_PATH . GuidebookController::PDF_FILE,
            'Internship_Guidebook_LEAP_Clean_Layout.pdf',
            'guidebook.download'
        );
    }

    private function sendTemplate(string $path, string $filename, string $auditAction): void
    {
        if (!is_file($path)) {
            http_response_code(404);
            View::error(404);
            return;
        }

        $download = (string) \qget('download', '') === '1';
        if ($download) {
            Audit::log($auditAction);
        }

        header('Content-Type: application/pdf');
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    private function mayAccess(array $doc): bool
    {
        $role = Auth::role();
        if (in_array($role, ['coordinator', 'management', 'admin'], true)) {
            return true;
        }

        $userId = (int) Auth::id();

        if ((int) ($doc['uploaded_by'] ?? 0) === $userId) {
            return true;
        }

        if ($doc['student_id'] !== null && (int) ($doc['student_user'] ?? 0) === $userId) {
            return true;
        }

        if ($doc['placement_id'] !== null) {
            $placement = Database::one(
                'SELECT p.*, s.user_id AS student_user FROM placement p JOIN student s ON s.student_id = p.student_id WHERE p.placement_id = ?',
                [(int) $doc['placement_id']]
            );
            if ($placement !== null) {
                if ((int) $placement['student_user'] === $userId) {
                    return true;
                }
                if ((int) $placement['mentor_id'] === $userId || (int) ($placement['supervisor_id'] ?? 0) === $userId) {
                    return true;
                }
            }
        }

        if ($doc['entry_id'] !== null) {
            $entry = Database::one(
                'SELECT p.mentor_id, p.supervisor_id, s.user_id AS student_user
                 FROM logbook_entry e
                 JOIN placement p ON p.placement_id = e.placement_id
                 JOIN student s ON s.student_id = p.student_id
                 WHERE e.entry_id = ?',
                [(int) $doc['entry_id']]
            );
            if ($entry !== null) {
                return (int) $entry['student_user'] === $userId
                    || (int) $entry['mentor_id'] === $userId
                    || (int) ($entry['supervisor_id'] ?? 0) === $userId;
            }
        }

        if ($doc['visit_id'] !== null) {
            return (int) ($doc['visit_mentor'] ?? 0) === $userId;
        }

        return false;
    }
}
