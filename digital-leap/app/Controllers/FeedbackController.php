<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\View;

final class FeedbackController
{
    public function save(): void
    {
        $rating = (int) \post('rating', 0);
        $thoughts = trim((string) \post('thoughts', ''));
        $suggestions = trim((string) \post('suggestions', ''));
        $page = trim((string) \post('page', ''));
        $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

        $errors = [];
        if ($rating < 1 || $rating > 5) {
            $errors[] = 'Choose a rating from 1 to 5.';
        }
        if (mb_strlen($thoughts) > 1000) {
            $errors[] = 'Keep your thoughts under 1,000 characters.';
        }
        if (mb_strlen($suggestions) > 1000) {
            $errors[] = 'Keep your suggestions under 1,000 characters.';
        }
        if ($page !== '' && preg_match('/^[a-z0-9_\/-]{1,120}$/i', $page) !== 1) {
            $page = '';
        }

        if ($errors !== []) {
            if ($wantsJson) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => implode(' ', $errors)]);
                return;
            }
            foreach ($errors as $message) {
                \flash('error', $message);
            }
            \redirect('dashboard');
        }

        $id = Database::insert('feedback', [
            'user_id' => (int) Auth::id(),
            'rating' => $rating,
            'thoughts' => $thoughts === '' ? null : $thoughts,
            'suggestions' => $suggestions === '' ? null : $suggestions,
            'page' => $page === '' ? null : $page,
        ]);
        Audit::log('feedback.submit', 'feedback', $id, null, ['rating' => $rating]);

        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'message' => 'Thank you. Your feedback has been recorded.']);
            return;
        }
        \flash('success', 'Thank you. Your feedback has been recorded.');
        \redirect('dashboard');
    }

    public function admin(): void
    {
        $ratingFilter = (int) \qget('rating', 0);
        $pageFilter = trim((string) \qget('screen', ''));
        $where = ' WHERE 1 = 1';
        $params = [];
        if ($ratingFilter !== 0) {
            $where .= ' AND f.rating = ?';
            $params[] = $ratingFilter;
        }
        if ($pageFilter !== '') {
            $where .= ' AND f.page = ?';
            $params[] = $pageFilter;
        }
        $rows = Database::all(
            'SELECT f.*, u.first_name, u.last_name, r.role_name
             FROM feedback f
             LEFT JOIN users u ON u.user_id = f.user_id
             LEFT JOIN roles r ON r.role_id = u.role_id' . $where . '
             ORDER BY f.created_at DESC, f.feedback_id DESC
             LIMIT 200', $params
        );
        $summary = Database::one(
            'SELECT COUNT(*) AS total,
                    ROUND(AVG(rating), 1) AS average,
                    COALESCE(SUM(rating >= 4), 0) AS positive,
                    COALESCE(SUM(rating <= 2), 0) AS negative
             FROM feedback'
        );
        $distribution = Database::all(
            'SELECT rating, COUNT(*) AS total FROM feedback GROUP BY rating ORDER BY rating DESC'
        );

        View::render('admin/feedback', [
            'title' => 'Feedback',
            'rows' => $rows,
            'summary' => $summary,
            'distribution' => $distribution,
            'ratingFilter' => $ratingFilter,
            'pageFilter' => $pageFilter,
            'screens' => Database::all("SELECT DISTINCT page FROM feedback WHERE page IS NOT NULL AND page <> '' ORDER BY page"),
        ]);
    }
}
