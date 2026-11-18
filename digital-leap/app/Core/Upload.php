<?php
declare(strict_types=1);

namespace Leap\Core;

use finfo;
use RuntimeException;

final class Upload
{
    private const ALLOWED = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    /**
     * @param array $owner exactly one of student_id, placement_id, entry_id, visit_id
     */
    public static function store(array $file, string $docType, array $owner): int
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No file was received. Choose a file and try again.');
        }

        $max = (int) ($GLOBALS['config']['upload']['max_bytes'] ?? 5242880);
        if ((int) $file['size'] > $max) {
            throw new RuntimeException('The file is larger than the ' . round($max / 1048576, 1) . ' MB limit.');
        }

        $original = (string) $file['name'];
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            throw new RuntimeException('Only PDF, Word, JPG and PNG files are accepted.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file((string) $file['tmp_name']);

        $expected = [self::ALLOWED[$ext]];
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $expected[] = 'image/pjpeg';
            $expected[] = 'image/jpg';
        }
        if (!in_array($mime, $expected, true)) {
            throw new RuntimeException('The file contents do not match its extension.');
        }

        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $target = STORAGE_PATH . '/uploads/' . $stored;

        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            throw new RuntimeException('The file could not be stored. Check the storage folder permissions.');
        }

        return Database::insert('document', [
            'doc_type' => $docType,
            'student_id' => $owner['student_id'] ?? null,
            'placement_id' => $owner['placement_id'] ?? null,
            'entry_id' => $owner['entry_id'] ?? null,
            'visit_id' => $owner['visit_id'] ?? null,
            'original_name' => substr($original, 0, 180),
            'stored_name' => $stored,
            'mime_type' => $mime,
            'file_size' => (int) $file['size'],
            'uploaded_by' => Auth::id(),
            'uploaded_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
