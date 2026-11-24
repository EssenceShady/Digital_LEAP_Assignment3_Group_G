<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\View;
use Leap\Services\Diva;

/**
 * The internship guidebook as a document: the published PDF, and the same
 * text page for page for readers who prefer or need plain text.
 */
final class GuidebookController
{
    public const PDF_FILE = '/templates/Internship_Guidebook_LEAP_Clean_Layout.pdf';

    public function index(): void
    {
        $path = STORAGE_PATH . self::PDF_FILE;
        $available = is_file($path);

        View::render('guidebook/index', [
            'title' => 'Internship guidebook',
            'contents' => Diva::contents(),
            'pdfAvailable' => $available,
            'pdfKb' => $available ? (int) round(filesize($path) / 1024) : 0,
        ]);
    }
}
