<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\View;
use Leap\Services\Diva;

final class DivaController
{
    private const HISTORY_TURNS = 6;

    public function index(): void
    {
        // A question in the URL only pre-fills the composer; asking is a POST
        // with a CSRF token, so a link can never write to someone's history.
        $question = mb_substr(trim((string) \qget('q', '')), 0, 500);
        $from = $this->fromRoute((string) \qget('from', ''));

        $chat = null;
        $turns = [];
        $chatId = (int) \qget('c', 0);
        if ($chatId > 0) {
            $chat = $this->ownedChat($chatId);
            if ($chat === null) {
                http_response_code(403);
                View::error(403);
                return;
            }
            if ((string) \qget('from', '') === '') {
                $from = (string) ($chat['from_route'] ?? '');
            }
            $turns = array_map([$this, 'hydrate'], Database::all(
                'SELECT * FROM diva_turn WHERE chat_id = ? ORDER BY turn_id',
                [$chatId]
            ));
        }

        View::render('diva/index', [
            'title' => 'DIVA',
            'chat' => $chat,
            'turns' => $turns,
            'chats' => $this->history(),
            'question' => $question,
            'from' => $from,
            'context' => Diva::context($from),
            'nextActions' => Diva::nextActions(),
            'capabilities' => Diva::capabilities(),
            'suggestions' => $this->suggestions($from),
            'localStatus' => Diva::localStatus(),
        ]);
    }

    public function ask(): void
    {
        $question = trim((string) \post('question', ''));
        $chatId = (int) \post('chat_id', 0);
        if ($question === '') {
            \flash('error', 'Type a question first.');
            \redirect('diva', $chatId > 0 ? ['c' => $chatId] : []);
        }

        $from = $this->fromRoute((string) \post('from', ''));
        $outcome = $this->answerAndStore($question, $chatId, $from);
        if (!$outcome['ok']) {
            if (($outcome['code'] ?? 400) === 403) {
                http_response_code(403);
                View::error(403);
                return;
            }
            \flash('error', $outcome['message']);
            \redirect('diva', $chatId > 0 ? ['c' => $chatId] : []);
        }

        \redirect('diva', ['c' => $outcome['chat_id']]);
    }

    /**
     * One answer path for the form post and the offline shell's API. Returns
     * the contract, the stored turn and the conversation it belongs to.
     *
     * @return array{ok:true,chat_id:int,turn_id:int,result:array,contract:array}|array{ok:false,code:int,message:string}
     */
    public function answerAndStore(string $question, int $chatId, string $from): array
    {
        $chat = $chatId > 0 ? $this->ownedChat($chatId) : null;
        if ($chatId !== 0 && $chat === null) {
            return ['ok' => false, 'code' => 403, 'message' => 'That conversation belongs to another account or was archived.'];
        }
        if (mb_strlen($question) > 500) {
            return ['ok' => false, 'code' => 422, 'message' => 'Keep your question within 500 characters.'];
        }

        $history = [];
        if ($chat !== null) {
            $rows = Database::all(
                'SELECT * FROM diva_turn WHERE chat_id = ? ORDER BY turn_id DESC LIMIT ' . self::HISTORY_TURNS,
                [(int) $chat['chat_id']]
            );
            $history = array_reverse(array_map([$this, 'hydrate'], $rows));
        }

        $result = $this->answer($question, $from, $history);
        $now = date('Y-m-d H:i:s');
        $turnId = 0;

        $chatId = Database::tx(function () use ($chat, $result, $from, $now, &$turnId): int {
            if ($chat === null) {
                $id = Database::insert('diva_chat', [
                    'user_id' => (int) Auth::id(),
                    'title' => mb_strimwidth($result['question'], 0, 80, '…'),
                    'from_route' => $from !== '' ? $from : null,
                    'turn_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $id = (int) $chat['chat_id'];
                $current = Database::one(
                    'SELECT chat_id FROM diva_chat WHERE chat_id = ? AND user_id = ? AND archived_at IS NULL FOR UPDATE',
                    [$id, (int) Auth::id()]
                );
                if ($current === null) {
                    return 0;
                }
            }

            $turnId = Database::insert('diva_turn', [
                'chat_id' => $id,
                'question' => $result['question'],
                'answer' => $result['answer'],
                'intent' => mb_substr($result['intent'], 0, 40),
                'tier' => $result['tier'],
                'confidence' => $result['confidence'],
                'meta_json' => json_encode([
                    'sources' => $result['sources'],
                    'links' => $result['links'],
                    'followups' => $result['followups'],
                    'reasoning' => $result['reasoning'],
                    'keywords' => $result['keywords'],
                    'resolved' => $result['resolved'],
                    'carried' => $result['carried'],
                    'slots' => $result['slots'] ?? null,
                    'evidence' => $result['evidence'] ?? [],
                    'next_action' => $result['next_action'] ?? null,
                    'context' => $result['context'] !== null
                        ? ['route' => $result['context']['route'], 'title' => $result['context']['title']]
                        : null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                'created_at' => $now,
            ]);
            Database::q('UPDATE diva_chat SET turn_count = turn_count + 1, updated_at = ? WHERE chat_id = ?', [$now, $id]);

            return $id;
        });

        if ($chatId === 0) {
            return ['ok' => false, 'code' => 409, 'message' => 'This conversation was archived. Start a new conversation to continue.'];
        }

        return [
            'ok' => true,
            'chat_id' => $chatId,
            'turn_id' => $turnId,
            'result' => $result,
            'contract' => [
                'mode' => 'online',
                'intent' => (string) $result['intent'],
                'slots' => $result['slots'],
                'evidence' => $result['evidence'],
                'confidence' => (string) $result['confidence'],
                'answer' => (string) $result['answer'],
                'next_action' => $result['next_action'],
            ],
        ];
    }

    public function archive(): void
    {
        $chat = $this->ownedChat((int) \post('chat_id', 0));
        if ($chat === null) {
            http_response_code(403);
            View::error(403);
            return;
        }

        Database::tx(static function () use ($chat): void {
            Database::update('diva_chat', ['archived_at' => date('Y-m-d H:i:s')], 'chat_id = ? AND user_id = ?', [(int) $chat['chat_id'], (int) Auth::id()]);
            Audit::log('diva.chat_archived', 'diva_chat', (int) $chat['chat_id'], null, ['turns' => (int) $chat['turn_count']]);
        });
        \flash('success', 'Conversation archived. Stored messages have not been deleted.');
        \redirect('diva');
    }

    public function source(): void
    {
        $number = (int) \qget('page', 0);
        $page = $number > 0 ? Diva::page($number) : null;

        if ($page === null) {
            \flash('error', 'That page is not in the guidebook. It has 25 pages.');
            \redirect('diva');
        }

        View::render('diva/source', [
            'title' => 'Guidebook page ' . $page['page'],
            'page' => $page,
        ]);
    }

    public function reportCheckForm(): void
    {
        $result = null;
        if (isset($_SESSION['diva']['critique']) && is_array($_SESSION['diva']['critique'])) {
            $result = $_SESSION['diva']['critique'];
        }

        View::render('diva/report_check', [
            'title' => 'Check my report',
            'result' => $result,
        ]);
    }

    public function reportCheck(): void
    {
        $pasted = trim((string) \post('report_text', ''));
        $format = 'text';
        $stats = [];
        $text = $pasted;

        $file = $_FILES['report'] ?? null;
        $hasFile = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($hasFile) {
            $read = Diva::readDocument($file);
            if (!$read['ok']) {
                \flash('error', $read['message']);
                \redirect('diva/report-check');
            }
            $text = $read['text'];
            $format = $read['format'];
            $stats = $read['stats'];
        }

        if ($text === '') {
            \flash('error', 'Upload a .docx file or paste the report text.');
            \redirect('diva/report-check');
        }
        if (mb_strlen($text) > 300000) {
            \flash('error', 'That text is too long for one check. Keep it under 300,000 characters.');
            \redirect('diva/report-check');
        }

        $result = Diva::critique($text, ['format' => $format, 'stats' => $stats]);
        if (!$result['ok']) {
            \flash('error', $result['message']);
            \redirect('diva/report-check');
        }

        $counts = ['present' => 0, 'thin' => 0, 'missing' => 0];
        foreach ($result['criteria'] as $criterion) {
            $counts[$criterion['status']] = ($counts[$criterion['status']] ?? 0) + 1;
        }
        Audit::log('diva.report_check', 'report', null, null, [
            'format' => $format,
            'words' => $result['words'],
            'criteria' => $counts,
            'layout_checks' => count($result['layout']),
        ]);

        $_SESSION['diva']['critique'] = $result;
        \redirect('diva/report-check');
    }

    /** @return array{question:string,intent:string,tier:string,confidence:string,answer:string,sources:list<array>,links:list<array>,followups:list<string>,context:?array} */
    private function answer(string $question, string $from = '', array $history = []): array
    {
        if (mb_strlen($question) > 500) {
            $question = mb_substr($question, 0, 500);
        }

        $result = Diva::ask($question, $from, $history);

        Audit::log('diva.query', 'diva', null, null, [
            'intent' => $result['intent'],
            'tier' => $result['tier'],
            'confidence' => $result['confidence'] ?? 'high',
            'chars' => mb_strlen($question),
            'question_hash' => substr(hash('sha256', $question), 0, 16),
            'from' => $from,
            'follow_up' => $result['carried'] !== null,
            'pages' => array_map(static fn (array $source): int => (int) $source['page'], $result['sources']),
        ]);

        return $result;
    }

    /** Only the account that asked can open, continue or archive a conversation. */
    private function ownedChat(int $chatId): ?array
    {
        if ($chatId <= 0) {
            return null;
        }
        return Database::one(
            'SELECT * FROM diva_chat WHERE chat_id = ? AND user_id = ? AND archived_at IS NULL',
            [$chatId, (int) Auth::id()]
        );
    }

    /** @return list<array> */
    private function history(): array
    {
        return Database::all(
            'SELECT chat_id, title, turn_count, updated_at FROM diva_chat
             WHERE user_id = ? AND archived_at IS NULL
             ORDER BY updated_at DESC, chat_id DESC',
            [(int) Auth::id()]
        );
    }

    private function hydrate(array $row): array
    {
        $meta = json_decode((string) ($row['meta_json'] ?? ''), true);
        $meta = is_array($meta) ? $meta : [];

        return [
            'turn_id' => (int) $row['turn_id'],
            'question' => (string) $row['question'],
            'answer' => (string) $row['answer'],
            'intent' => (string) $row['intent'],
            'tier' => (string) $row['tier'],
            'confidence' => (string) $row['confidence'],
            'asked_at' => (string) $row['created_at'],
            'sources' => is_array($meta['sources'] ?? null) ? $meta['sources'] : [],
            'links' => is_array($meta['links'] ?? null) ? $meta['links'] : [],
            'followups' => is_array($meta['followups'] ?? null) ? $meta['followups'] : [],
            'reasoning' => is_array($meta['reasoning'] ?? null) ? $meta['reasoning'] : [],
            'resolved' => (string) ($meta['resolved'] ?? $row['question']),
            'carried' => isset($meta['carried']) ? (string) $meta['carried'] : null,
            'context' => is_array($meta['context'] ?? null) ? $meta['context'] : null,
            'slots' => is_array($meta['slots'] ?? null) ? $meta['slots'] : null,
            'evidence' => is_array($meta['evidence'] ?? null) ? $meta['evidence'] : [],
            'next_action' => is_array($meta['next_action'] ?? null) ? $meta['next_action'] : null,
        ];
    }

    /**
     * Which page the visitor came from. A referrer is only trusted when it
     * names a real route, and DIVA's own pages never become the context.
     */
    private function fromRoute(string $candidate): string
    {
        $from = trim($candidate);
        if ($from === '') {
            $referrer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            if ($referrer !== '') {
                $query = parse_url($referrer, PHP_URL_QUERY);
                if (is_string($query)) {
                    parse_str($query, $params);
                    $from = trim((string) ($params['r'] ?? ''));
                }
            }
        }

        $routes = require BASE_PATH . '/app/routes.php';
        if ($from === '' || !isset($routes['GET ' . $from]) || str_starts_with($from, 'diva')) {
            $stored = (string) ($_SESSION['diva']['from'] ?? '');
            return isset($routes['GET ' . $stored]) && !str_starts_with($stored, 'diva') ? $stored : '';
        }

        $_SESSION['diva']['from'] = $from;
        return $from;
    }

    /** @return list<string> */
    private function suggestions(string $from): array
    {
        $role = Auth::role();

        $context = Diva::context($from);
        $start = ['What should I do next?'];
        if ($context !== null) {
            $start[] = 'What can I do on this page?';
        }

        $student = [
            'When is my next logbook entry due?',
            'How do I apply for an internship?',
            'Who chooses the host organisation?',
            'What does Form E mark for the internship report?',
            'Can I see my results?',
        ];

        $roleQuestions = match ($role) {
            'student' => $student,
            'supervisor' => [
                'What is waiting for me to review?',
                'What should I look for before approving an entry?',
                'How is the logbook weighted?',
                'What does Form D cover?',
            ],
            'mentor' => [
                'What is waiting for me to review?',
                'What are the report criteria?',
                'How do I record a visit?',
                'Who chooses the host organisation?',
            ],
            'coordinator' => [
                'What still needs my approval?',
                'How many placements are active?',
                'Who chooses the host organisation?',
                'What does Form E mark for the internship report?',
            ],
            'management', 'aqa' => [
                'How many placements are active?',
                'How many marks are published?',
                'What are the assessment weights?',
                'What are the two internship types?',
            ],
            default => [
                'What can you do?',
                'Where do I find the audit log?',
                'How many accounts are there?',
                'What are the two internship types?',
            ],
        };

        return array_slice(array_values(array_unique(array_merge($start, $roleQuestions))), 0, 6);
    }
}
