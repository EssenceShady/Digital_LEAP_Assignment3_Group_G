<?php
use Leap\Core\Auth;

$tiers = ['rules' => 'Rules & records', 'retrieval' => 'Guidebook search', 'local' => 'Local model'];
$me = trim((string) Auth::name());
$first = explode(' ', $me)[0];
$chatId = (int) ($chat['chat_id'] ?? 0);
$last = $turns === [] ? null : $turns[count($turns) - 1];
$prompts = $last !== null ? $last['followups'] : $suggestions;
?>
<div class="page-head diva-page-head">
    <div class="diva-brand">
        <img src="<?= e(asset('img/diva-mark-sm.png')) ?>" width="40" height="40" alt="">
        <div><h1>DIVA</h1><p>Your Digital Internship Virtual Assistant</p></div>
    </div>
    <a class="btn btn-sm btn-outline" href="<?= e(url('diva/report-check')) ?>">Check a report draft</a>
</div>
<div class="diva-shell" data-diva>
    <aside class="card diva-aside" aria-label="DIVA conversations">
        <a class="btn btn-block" href="<?= e(url('diva', ['from' => $from])) ?>">+ New conversation</a>
        <details class="diva-history" open>
            <summary>Conversation history <span class="muted"><?= count($chats) ?></span></summary>
            <label class="sr-only" for="diva-history-search">Search your conversations</label>
            <input id="diva-history-search" type="search" placeholder="Find a conversation…" data-diva-search>
            <nav class="diva-history-list" aria-label="Saved conversations">
                <?php foreach ($chats as $saved): ?>
                    <a class="diva-history-item<?= (int) $saved['chat_id'] === $chatId ? ' is-current' : '' ?>" href="<?= e(url('diva', ['c' => (int) $saved['chat_id']])) ?>" data-diva-history-item<?= (int) $saved['chat_id'] === $chatId ? ' aria-current="page"' : '' ?>>
                        <strong><?= e($saved['title']) ?></strong>
                        <span><?= e(fmt_dt($saved['updated_at'])) ?> · <?= (int) $saved['turn_count'] ?> questions</span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <p class="hint" data-diva-history-empty<?= $chats !== [] ? ' hidden' : '' ?>><?= $chats === [] ? 'Your conversations appear here after you send a question.' : 'No matching conversations.' ?></p>
        </details>
        <div class="diva-privacy">
            <strong>Saved to your account</strong>
            <p>Questions and answers stay on this installation, including after sign-out. Other accounts cannot open your history. Database administrators can access stored data; do not share passwords.</p>
        </div>
        <details class="diva-about">
            <summary>Capabilities & limits</summary>
            <p>I use your permitted records, the page guide and the internship guidebook. I can explain deadlines, weights and next steps, and check a report draft.</p>
            <p>I save chats, but cannot change internship records, approve work or send messages. I have no web access and am not a general-purpose language model.</p>
            <p>Short follow-ups use recent questions in this conversation. Saved answers are snapshots; ask again for current records.</p>
        </details>
    </aside>
    <div class="diva-main">
        <section class="card diva-chat chat-card" aria-label="Conversation">
            <header class="diva-chat-head">
                <div><h2><?= e($chat['title'] ?? 'A little clarity. Your next step.') ?></h2><span class="small muted">Local assistant · No external API</span></div>
                <?php if ($chat !== null): ?>
                    <form method="post" action="<?= e(url('diva/archive')) ?>" data-confirm="Archive this conversation? It will leave your history, but its stored records will not be deleted.">
                        <?= csrf_field() ?><input type="hidden" name="chat_id" value="<?= $chatId ?>">
                        <button class="btn btn-sm btn-ghost" type="submit">Archive</button>
                    </form>
                <?php endif; ?>
            </header>
            <div class="chat-stream diva-stream" data-diva-stream tabindex="0" aria-label="Conversation messages">
                <?php if ($turns === []): ?>
                    <div class="diva-welcome">
                        <img class="diva-mark" src="<?= e(asset('img/diva-mark.png')) ?>" width="64" height="64" alt="">
                        <p class="eyebrow">Grounded in Digital LEAP</p>
                        <h2>Hello<?= $first !== '' ? ', ' . e($first) : '' ?>.<br>What can we work through?</h2>
                        <p>Understand a deadline, find your next step, or make sense of the guidebook. Start here; your conversation will be saved.</p>
                        <div class="diva-prompt-grid">
                            <?php foreach (array_slice($suggestions, 0, 4) as $suggestion): ?>
                                <form method="post" action="<?= e(url('diva/ask')) ?>" data-diva-prompt>
                                    <?= csrf_field() ?><input type="hidden" name="from" value="<?= e($from) ?>">
                                    <button type="submit" class="diva-prompt" name="question" value="<?= e($suggestion) ?>"><?= e($suggestion) ?><span aria-hidden="true">↗</span></button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php foreach ($turns as $turn): ?>
                    <article class="chat-msg is-mine" id="diva-turn-<?= (int) $turn['turn_id'] ?>">
                        <div class="chat-bubble">
                            <div class="chat-meta"><strong>You</strong><time class="small muted" datetime="<?= e(str_replace(' ', 'T', $turn['asked_at'])) ?>"><?= e(fmt_dt($turn['asked_at'])) ?></time></div>
                            <p class="chat-body diva-message-text"><?= e($turn['question']) ?></p>
                        </div>
                    </article>
                    <article class="chat-msg diva-response">
                        <span class="chat-avatar diva-avatar" aria-hidden="true"><img src="<?= e(asset('img/diva-mark-sm.png')) ?>" width="30" height="30" alt=""></span>
                        <div class="chat-bubble">
                            <div class="chat-meta"><strong>DIVA</strong><span class="small muted"><?= e($tiers[$turn['tier']] ?? $turn['tier']) ?></span></div>
                            <?php if ($turn['carried'] !== null): ?><p class="diva-carried small">Following up on: <?= e($turn['carried']) ?></p><?php endif; ?>
                            <div class="chat-body diva-message-text"><?= e($turn['answer']) ?></div>
                            <?php if ($turn['reasoning'] !== []): ?>
                                <details class="diva-basis">
                                    <summary>Answer basis & checks <span class="muted">· <?= e(ucfirst($turn['confidence'])) ?> match confidence</span></summary>
                                    <p class="hint">A record of the rules and sources used, not a guarantee that the answer is complete.</p>
                                    <ol><?php foreach ($turn['reasoning'] as $step): ?><li><?= e($step) ?></li><?php endforeach; ?></ol>
                                </details>
                            <?php endif; ?>
                            <?php if ($turn['sources'] !== []): ?>
                                <details class="diva-sources">
                                    <summary><?= count($turn['sources']) ?> guidebook sources</summary>
                                    <ul><?php foreach ($turn['sources'] as $source): ?><li><a href="<?= e(url('diva/source', ['page' => (int) $source['page']])) ?>">Guidebook page <?= (int) $source['page'] ?></a><p class="small muted"><?= e($source['snippet']) ?></p></li><?php endforeach; ?></ul>
                                </details>
                            <?php endif; ?>
                            <?php if ($turn['links'] !== []): ?><div class="diva-links"><?php foreach ($turn['links'] as $link): ?><a class="btn btn-sm btn-outline" href="<?= e(url($link['route'], $link['params'])) ?>"><?= e($link['label']) ?></a><?php endforeach; ?></div><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($last !== null && $prompts !== []): ?>
                <form class="diva-followup-form" method="post" action="<?= e(url('diva/ask')) ?>" data-diva-prompt>
                    <?= csrf_field() ?><input type="hidden" name="chat_id" value="<?= $chatId ?>"><input type="hidden" name="from" value="<?= e($from) ?>">
                    <span class="hint">Keep exploring</span>
                    <div class="chip-row"><?php foreach (array_slice($prompts, 0, 3) as $prompt): ?><button type="submit" class="chip" name="question" value="<?= e($prompt) ?>"><?= e($prompt) ?></button><?php endforeach; ?></div>
                </form>
            <?php endif; ?>
            <form class="chat-composer diva-composer" method="post" action="<?= e(url('diva/ask')) ?>" data-diva-form>
                <?= csrf_field() ?><input type="hidden" name="chat_id" value="<?= $chatId ?>"><input type="hidden" name="from" value="<?= e($from) ?>">
                <label class="sr-only" for="question">Your question</label>
                <textarea id="question" name="question" rows="2" maxlength="500" required placeholder="Ask DIVA about your internship…" aria-describedby="diva-composer-hint"><?= e($question) ?></textarea>
                <div class="chat-composer-actions">
                    <span class="hint" id="diva-composer-hint">Enter to send · Shift+Enter for a new line · <span data-diva-count><?= mb_strlen($question) ?></span>/500</span>
                    <button class="btn" type="submit">Send question <span aria-hidden="true">↑</span></button>
                </div>
                <p class="hint diva-status" role="status" data-diva-status hidden>Checking the available records and guidebook…</p>
            </form>
        </section>
        <?php if ($context !== null): ?><p class="hint diva-context-note">Page context: <a href="<?= e(url($context['route'])) ?>"><?= e($context['title']) ?></a></p><?php endif; ?>
        <details class="card diva-resources">
            <summary>Your live next steps & workspace guide</summary>
            <h3>Your next actions</h3>
            <?php if ($nextActions === []): ?><p class="hint">Nothing is waiting on you right now.</p><?php endif; ?>
            <ul class="diva-actions"><?php foreach ($nextActions as $action): ?><li><div><strong class="diva-action-title"><?= e($action['title']) ?></strong><p class="diva-action-detail"><?= e($action['detail']) ?></p></div><a class="btn btn-sm" href="<?= e(url($action['route'], $action['params'])) ?>"><?= e($action['cta']) ?></a></li><?php endforeach; ?></ul>
            <div class="diva-groups"><?php foreach ($capabilities as $group): ?><div class="diva-group"><h3><?= e($group['title']) ?></h3><ul class="diva-pages"><?php foreach ($group['items'] as $item): ?><li class="diva-page"><h4><?= e($item['title']) ?></h4><p><?= e($item['body']) ?></p><a href="<?= e(url($item['route'], $item['params'])) ?>"><?= e($item['label']) ?></a></li><?php endforeach; ?></ul></div><?php endforeach; ?></div>
        </details>
    </div>
</div>
