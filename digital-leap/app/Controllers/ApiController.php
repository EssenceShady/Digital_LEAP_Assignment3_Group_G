<?php
declare(strict_types=1);

namespace Leap\Controllers;

use Leap\Core\Audit;
use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\SyncRejected;
use PDOException;
use RuntimeException;

/**
 * Internal JSON endpoints used by the offline shell. Not a public API:
 * every call is same-origin, session-authenticated and CSRF-checked.
 */
final class ApiController
{
    /** Receives queued logbook operations from the offline outbox. */
    public function sync(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $opId = trim((string) \post('op_id', ''));
        $raw = (string) \post('payload', '');
        $payload = json_decode($raw, true);

        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $opId)) {
            $this->json(400, ['ok' => false, 'error' => 'A valid operation id is required.']);
            return;
        }
        if (!is_array($payload) || !is_array($payload['fields'] ?? null)) {
            $this->json(400, ['ok' => false, 'error' => 'The operation payload could not be read.']);
            return;
        }
        if (($payload['kind'] ?? '') !== 'logbook.entry') {
            $this->json(400, ['ok' => false, 'error' => 'Unsupported operation type.']);
            return;
        }

        $userId = (int) Auth::id();
        $fields = $payload['fields'];
        $submit = (bool) ($payload['submit'] ?? false);
        $hash = hash('sha256', $raw);

        $existing = Database::one('SELECT * FROM sync_op WHERE op_id = ?', [$opId]);
        if ($existing !== null) {
            if ((int) $existing['user_id'] !== $userId) {
                $this->json(409, ['ok' => false, 'error' => 'This operation belongs to another account.']);
                return;
            }
            $this->json(200, [
                'ok' => true,
                'status' => 'duplicate',
                'op_id' => $opId,
                'entry_id' => $existing['result_entry_id'] === null ? null : (int) $existing['result_entry_id'],
                'entry_status' => $existing['result_status'],
            ]);
            return;
        }

        $student = new StudentController();
        try {
            $result = Database::tx(static function () use ($opId, $userId, $hash, $fields, $submit, $student): array {
                Database::insert('sync_op', [
                    'op_id' => $opId,
                    'user_id' => $userId,
                    'op_kind' => 'logbook.entry',
                    'payload_hash' => $hash,
                    'result_entry_id' => null,
                    'result_status' => null,
                    'status' => 'processing',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $outcome = $student->persistEntry($fields, $submit);
                if (!$outcome['ok']) {
                    throw new SyncRejected($outcome['errors']);
                }
                Database::update('sync_op', [
                    'status' => 'applied',
                    'result_entry_id' => $outcome['entry_id'],
                    'result_status' => $outcome['status'],
                ], 'op_id = ?', [$opId]);
                return $outcome;
            });
        } catch (SyncRejected $rejected) {
            $this->json(422, ['ok' => false, 'error' => 'The entry was not accepted.', 'errors' => $rejected->reasons]);
            return;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                // Either two devices raced on the same operation (the first one
                // wins and the claim row exists), or the entry itself conflicts
                // with a week that already has a logbook — a rejection, not a
                // duplicate.
                $winner = Database::one('SELECT * FROM sync_op WHERE op_id = ?', [$opId]);
                if ($winner !== null) {
                    $this->json(200, [
                        'ok' => true,
                        'status' => 'duplicate',
                        'op_id' => $opId,
                        'entry_id' => $winner['result_entry_id'] === null ? null : (int) $winner['result_entry_id'],
                        'entry_status' => $winner['result_status'] ?? null,
                    ]);
                    return;
                }
                $week = (int) ($fields['week_no'] ?? 0);
                Audit::log('sync.rejected', 'sync_op', null, null, ['op_id' => $opId, 'reason' => 'week conflict']);
                $this->json(409, [
                    'ok' => false,
                    'error' => $week > 0
                        ? 'Week ' . $week . ' already has a log book entry. Open that week and edit it instead.'
                        : 'This entry already exists.',
                ]);
                return;
            }
            throw $exception;
        } catch (RuntimeException $exception) {
            Audit::log('sync.rejected', 'sync_op', null, null, ['op_id' => $opId, 'reason' => $exception->getMessage()]);
            $this->json(422, ['ok' => false, 'error' => $exception->getMessage()]);
            return;
        }

        Audit::log('sync.applied', 'sync_op', null, null, [
            'op_id' => $opId,
            'entry_id' => $result['entry_id'],
            'status' => $result['status'],
        ]);
        $this->json(200, [
            'ok' => true,
            'status' => 'applied',
            'op_id' => $opId,
            'entry_id' => $result['entry_id'],
            'entry_status' => $result['status'],
        ]);
    }

    /**
     * One DIVA turn for the shell's offline JS engine. When the server is
     * reachable the composer posts here instead of the form, so the answer
     * comes from the PHP engine with the same contract the device would
     * build locally — and it is stored on the account, not just on the device.
     */
    public function diva(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $question = trim((string) \post('question', ''));
        if ($question === '') {
            $this->json(400, ['ok' => false, 'error' => 'Type a question first.']);
            return;
        }

        $outcome = (new DivaController())->answerAndStore(
            $question,
            (int) \post('chat_id', 0),
            trim((string) \post('from', ''))
        );
        if (!$outcome['ok']) {
            $this->json($outcome['code'], ['ok' => false, 'error' => $outcome['message']]);
            return;
        }

        $result = $outcome['result'];
        $this->json(200, [
            'ok' => true,
            'chat_id' => $outcome['chat_id'],
            'turn_id' => $outcome['turn_id'],
            'contract' => $outcome['contract'],
            'turn' => [
                'question' => (string) $result['question'],
                'answer' => (string) $result['answer'],
                'intent' => (string) $result['intent'],
                'tier' => (string) $result['tier'],
                'confidence' => (string) $result['confidence'],
                'sources' => $result['sources'],
                'links' => $result['links'],
                'evidence' => $result['evidence'],
                'next_action' => $result['next_action'],
            ],
        ]);
    }

    private function json(int $code, array $body): void
    {
        http_response_code($code);
        echo json_encode(
            $body,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }
}
