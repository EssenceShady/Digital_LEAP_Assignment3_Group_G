/* DIVA on the device.
 *
 * The second host for the DIVA answer contract. When the server cannot be
 * reached, this file runs the same stages as the PHP engine over the shipped
 * index (public/assets/data/diva-index.json):
 *
 *   route -> slots -> retrieve (BM25 + synonyms) -> memory -> compose -> verify
 *
 * It answers from the guidebook, the marking model and the snapshots this
 * device saved while online. Live records are never invented offline: if no
 * saved answer covers the question, DIVA says so and asks for a connection.
 *
 * When the server is reachable the composer posts to ?r=api/diva and the same
 * contract comes back from PHP, so the two hosts agree on shape and checks.
 */
'use strict';

(function () {
    var INDEX_URL = null;
    var index = null;
    var stats = null;
    var loadPromise = null;
    var LAST = { question: '', contract: null };

    var STOP = null;
    var SYNONYMS = null;
    var SYN_INDEX = null;

    var CATEGORY_LABEL = { records: 'Rules & records', guidebook: 'Guidebook search', assessment: 'Marking model', self: 'DIVA', memory: 'Follow-up', clarify: 'Clarify', out_of_scope: 'Out of scope' };

    /* --- text ---------------------------------------------------------- */

    function fold(text) {
        return String(text || '').toLowerCase().replace(/\blog[\s-]+book\b/g, 'logbook');
    }

    function normalise(text) {
        return fold(text).replace(/\s+/g, ' ').replace(/^\s+|\s+$/g, '');
    }

    function tokens(text) {
        var folded = fold(text);
        var found = folded.match(/[a-z0-9]{2,}/g) || [];
        var out = [];
        for (var i = 0; i < found.length; i++) {
            if (STOP.indexOf(found[i]) === -1) { out.push(found[i]); }
        }
        return out;
    }

    function unique(list) {
        var seen = {};
        var out = [];
        for (var i = 0; i < list.length; i++) {
            if (!seen[list[i]]) { seen[list[i]] = true; out.push(list[i]); }
        }
        return out;
    }

    function expand(base) {
        var out = base.slice();
        for (var canonical in SYNONYMS) {
            if (!Object.prototype.hasOwnProperty.call(SYNONYMS, canonical)) { continue; }
            var group = [canonical].concat(SYNONYMS[canonical]);
            var hit = false;
            for (var i = 0; i < base.length; i++) {
                if (group.indexOf(base[i]) !== -1) { hit = true; break; }
            }
            if (!hit) { continue; }
            for (var j = 0; j < group.length; j++) {
                var word = group[j].replace(/\s+/g, '');
                if (word.length > 2) { out.push(word); }
            }
        }
        return unique(out);
    }

    function numbers(text) {
        return unique(String(text || '').match(/\b\d{1,4}\b/g) || []);
    }

    function stripDates(text) {
        return String(text || '')
            .replace(/\b\d{4}-\d{2}-\d{2}\b/g, ' ')
            .replace(/\b\d{1,2}:\d{2}\b/g, ' ');
    }

    function trim(text, limit) {
        var clean = String(text || '').replace(/\s+/g, ' ').replace(/^\s+|\s+$/g, '');
        if (clean.length <= limit) { return clean; }
        var cut = clean.slice(0, limit);
        var space = cut.lastIndexOf(' ');
        return (space > 0 ? cut.slice(0, space) : cut).replace(/[\s,.;:]+$/, '') + '…';
    }

    function plural(count, singular, many) {
        return count + ' ' + (count === 1 ? singular : (many || singular + 's'));
    }

    /* --- retrieval ------------------------------------------------------ */

    function buildStats() {
        if (stats) { return stats; }
        var chunks = index.chunks;
        var df = {};
        var totalLength = 0;
        var prepared = [];
        for (var i = 0; i < chunks.length; i++) {
            var counts = {};
            var list = tokens(chunks[i].text);
            totalLength += list.length;
            for (var j = 0; j < list.length; j++) {
                counts[list[j]] = (counts[list[j]] || 0) + 1;
            }
            for (var token in counts) {
                if (Object.prototype.hasOwnProperty.call(counts, token)) { df[token] = (df[token] || 0) + 1; }
            }
            prepared.push({ page: chunks[i].page, text: chunks[i].text, counts: counts, length: list.length });
        }
        stats = { prepared: prepared, df: df, total: chunks.length, avg: chunks.length ? totalLength / chunks.length : 1 };
        return stats;
    }

    function idf(token) {
        var s = buildStats();
        return Math.log(1 + (s.total - (s.df[token] || 0) + 0.5) / ((s.df[token] || 0) + 0.5));
    }

    function retrieve(question, limit) {
        var pageMatch = /page\s+(\d{1,2})\b/i.exec(question);
        if (pageMatch) {
            var wanted = pageOf(parseInt(pageMatch[1], 10));
            if (wanted) {
                return [{ page: wanted.page, snippet: trim(wanted.text, 700), score: 99, coverage: 1 }];
            }
        }

        var base = unique(tokens(question));
        if (!base.length) { return []; }
        var expanded = expand(base);
        var s = buildStats();
        var k1 = 1.2;
        var b = 0.75;
        var best = {};

        for (var i = 0; i < s.prepared.length; i++) {
            var chunk = s.prepared[i];
            var score = 0;
            for (var t = 0; t < expanded.length; t++) {
                var token = expanded[t];
                var frequency = chunk.counts[token] || 0;
                if (!frequency) { continue; }
                var weight = base.indexOf(token) !== -1 ? 1 : 0.5;
                score += weight * idf(token) * (frequency * (k1 + 1)) / (frequency + k1 * (1 - b + b * chunk.length / s.avg));
            }
            if (score <= 0) { continue; }
            var current = best[chunk.page];
            if (!current || score > current.score) {
                best[chunk.page] = { page: chunk.page, text: chunk.text, score: score };
            }
        }

        var ranked = [];
        for (var page in best) {
            if (!Object.prototype.hasOwnProperty.call(best, page)) { continue; }
            var hit = best[page];
            var pageText = fold((pageOf(hit.page) || {}).text || '');
            var matched = 0;
            for (var m = 0; m < base.length; m++) {
                if (pageText.indexOf(base[m]) !== -1) { matched++; }
            }
            var full = (pageOf(hit.page) || {}).text || hit.text;
            var offset = full.indexOf(hit.text);
            var excerpt = offset >= 0 ? full.slice(offset) : full;
            ranked.push({
                page: hit.page,
                snippet: trim(excerpt, 700),
                score: Math.round(hit.score * 1000) / 1000,
                coverage: Math.round((matched / base.length) * 1000) / 1000,
            });
        }

        ranked.sort(function (a, b) { return b.score - a.score; });
        return ranked.slice(0, limit);
    }

    function pageOf(number) {
        for (var i = 0; i < index.pages.length; i++) {
            if (index.pages[i].page === number) { return index.pages[i]; }
        }
        return null;
    }

    /* --- routing and slots ---------------------------------------------- */

    var INTENTS = [
        ['greeting', /^(hi+|hello+|hey+|good (morning|afternoon|evening|day))[\s\p{P}]*$/iu],
        ['gratitude', /^(thanks|thank you|thx|cheers)[\s\p{P}]*$/iu],
        ['capabilities', /^(help|what can you (not )?do|what do you do|who are you|what are you|what is diva|who is diva|what can diva do)/i],
        ['weighting.logbook', /log\s*book.*(weight|percent|mark)|weight.*log\s*book/i],
        ['weighting', /weight(ing)?|rubric|criteria|how.*(marked|assessed)|form e|assessment.*percent/i],
        ['evaluation', /form d\b|supervisor evaluation|self[- ]evaluation|evaluation form/i],
        ['internship.duration', /how many weeks|how long|duration|16 weeks/i],
        ['internship.type', /in[\s-]?house|external|types? of internship|two types/i],
        ['deadline.career_fair', /career\s*fair|participation report/i],
        ['deadline.report', /report.*(deadline|due|when)|deadline.*report|when.*(submit|upload).*report/i],
        ['deadline.logbook', /log\s*book.*(deadline|due|when|late)|next entry|week.*(due|deadline)/i],
        ['results', /\bresults?\b|\bgrade\b|\bmark(s)?\b|published/i],
        ['progress', /progress|how far|percent|complete|milestone/i],
        ['placement', /placement|organisation|organization|where.*(work|placed)|supervisor|mentor/i],
        ['apply', /\bapply\b|application|cv|resume|rank(ed)? list|preference list/i],
        ['howto', /how (do|does|can|should) (i|we|you|they)|what should|what do i need|explain/i],
    ];

    var WHY = /^(why|how do you know|how did you (get|work out|know) (that|this)|where (does|did) (that|this) come from|sources?)\b[\s?.!]*$/i;
    var ELABORATE = /^(tell me more|more( detail| details| please)?|go on|go deeper|elaborate|expand( on that)?|explain( that| more)?)\b[\s?.!]*$/i;

    function route(question) {
        for (var i = 0; i < INTENTS.length; i++) {
            if (INTENTS[i][1].test(question)) { return INTENTS[i][0]; }
        }
        return null;
    }

    function slotsFor(question, intent, tier) {
        var lower = question.toLowerCase();
        var subject = null;
        var order = [['career fair', 'career_fair'], ['log book', 'logbook'], ['logbook', 'logbook'], ['report', 'report'],
            ['placement', 'placement'], ['result', 'results'], ['mark', 'results'], ['account', 'accounts'],
            ['evaluation', 'evaluation'], ['application', 'apply']];
        for (var i = 0; i < order.length; i++) {
            if (lower.indexOf(order[i][0]) !== -1) { subject = order[i][1]; break; }
        }
        if (!subject && /^deadline\./.test(intent || '')) { subject = (intent || '').replace('deadline.', '').replace('career_fair', 'career_fair'); }
        if (!subject && /^weighting/.test(intent || '')) { subject = 'report'; }

        var week = /\bweek\s*(\d{1,2})\b/i.exec(lower);
        var form = null;
        if (/\bform\s*(e|ix)\b/i.test(lower)) { form = 'E'; }
        else if (/\bform\s*d\s*1\b|\bd1\b/i.test(lower)) { form = 'D1'; }
        else if (/\bform\s*d\s*2\b|\bd2\b/i.test(lower)) { form = 'D2'; }
        else if (/\bform\s*d\b/i.test(lower)) { form = 'D'; }
        else { var letter = /\bform\s*(a2|a3|b|c)\b/i.exec(lower); if (letter) { form = letter[1].toUpperCase(); } }

        var component = null;
        if (/log\s*book|diary|weekly record/i.test(lower)) { component = 'logbook'; }
        else if (/form\s*d|supervisor evaluation/i.test(lower)) { component = 'form_d'; }
        else if (/self[- ]evaluation/i.test(lower)) { component = 'self_evaluation'; }
        else if (/career\s*fair/i.test(lower)) { component = 'career_fair'; }
        else if (/report/i.test(lower)) { component = 'report'; }

        return {
            category: categoryFor(intent, tier),
            role: role(),
            subject: subject,
            component: component,
            week: week ? parseInt(week[1], 10) : null,
            form: form,
        };
    }

    function categoryFor(intent, tier) {
        if (!intent) { return tier === 'retrieval' ? 'guidebook' : 'out_of_scope'; }
        if (intent === 'greeting' || intent === 'gratitude' || intent === 'capabilities') { return 'self'; }
        if (/^weighting|^evaluation|^internship\./.test(intent)) { return 'assessment'; }
        if (intent === 'howto') { return 'guidebook'; }
        return 'records';
    }

    function role() {
        return (document.body && document.body.getAttribute('data-role')) || 'student';
    }

    /* --- memory --------------------------------------------------------- */

    function lastName(history) {
        for (var i = history.length - 1; i >= 0; i--) {
            var turn = history[i] || {};
            if (turn.intent === 'greeting' || turn.intent === 'gratitude' || turn.intent === 'capabilities' || turn.intent === 'clarify') { continue; }
            return turn;
        }
        return null;
    }

    function resolveFollowUp(question, history) {
        var named = /^(?:and|what about|how about|same for)\s+(?:(?:the|my)\s+)?(report|log\s*book|career\s*fair)[\s?.!]*$/i.exec(question);
        var due = /^(?:and\s+)?when is (?:it|that|this) due[\s?.!]*$/i.test(question);
        if (!named && !due) { return null; }

        var previous = lastName(history);
        if (!previous) { return { question: question, intent: 'clarify' }; }

        var family = /^deadline\./.test(previous.intent || '') ? 'deadline'
            : (/^weighting/.test(previous.intent || '') ? 'weighting' : null);
        var subject = named ? named[1].replace(/\s+/g, '').toLowerCase() : null;
        if (due && previous) {
            family = 'deadline';
            var m = /(career\s*fair|log\s*book|report)/i.exec(previous.question || '');
            subject = m ? m[1].replace(/\s+/g, '').toLowerCase() : null;
            if (!subject) {
                if (/logbook/.test(previous.intent || '')) { subject = 'logbook'; }
                else if (/report/.test(previous.intent || '')) { subject = 'report'; }
                else if (/career/.test(previous.intent || '')) { subject = 'careerfair'; }
            }
        }
        if (!family || !subject) { return { question: question, intent: 'clarify' }; }
        var label = subject === 'careerfair' ? 'Career Fair' : subject;
        return {
            question: family === 'deadline' ? 'When is the ' + label + ' due?' : 'What is the ' + label + ' weighting?',
            intent: family === 'deadline'
                ? 'deadline.' + (subject === 'careerfair' ? 'career_fair' : subject)
                : (subject === 'logbook' ? 'weighting.logbook' : 'weighting'),
        };
    }

    /* --- composition ---------------------------------------------------- */

    function marks() { return index.marks; }

    function weightingAnswer() {
        var a = marks();
        var lines = [];
        for (var i = 0; i < index.rubric.length; i++) {
            lines.push('· ' + index.rubric[i].label + ' — ' + index.rubric[i].weight + '%');
        }
        return 'The final mark is out of ' + a.total + ':\n\n'
            + '· Practical Internship — ' + a.practical + ' marks: logbook ' + a.logbook + ', Form D host supervisor evaluation ' + a.form_d + ', student self evaluation ' + a.self_evaluation + '.\n'
            + '· Internship Report — ' + a.report + ' marks, marked with these eight criteria from Form E (guidebook pages 24–25):\n' + lines.join('\n') + '\n'
            + '· Career Fair — ' + a.career_fair + ' marks, recorded by the coordinator.\n\n'
            + 'The academic mentor\'s evaluation is advisory and carries no marks.';
    }

    function arithmetic(question) {
        var a = marks();
        var parts = {
            logbook: { label: 'Logbook', max: a.logbook, score: null },
            form_d: { label: 'Form D supervisor evaluation', max: a.form_d, score: null },
            self_evaluation: { label: 'Student self evaluation', max: a.self_evaluation, score: null },
            report: { label: 'Internship report', max: a.report, score: null },
            career_fair: { label: 'Career Fair', max: a.career_fair, score: null },
        };
        var aliases = [
            ['logbook', /\b(?:log\s*book|diary)\b/i],
            ['form_d', /\b(?:form\s*d1?|form\s*d2|supervisor evaluation)\b/i],
            ['self_evaluation', /\bself[- ]evaluation\b/i],
            ['report', /\breport\b/i],
            ['career_fair', /\bcareer\s*fair\b/i],
        ];
        var found = 0;
        for (var i = 0; i < aliases.length; i++) {
            var key = aliases[i][0];
            var re = new RegExp(aliases[i][1].source + '\\D{0,20}?(\\d{1,3})(?:\\s*(?:out of|\\/)\\s*(\\d{1,3}))?', 'i');
            var m = re.exec(question);
            if (m) {
                var score = parseInt(m[1], 10);
                if (score >= 0 && score <= parts[key].max) {
                    parts[key].score = score;
                    found++;
                }
            }
        }
        if (!found) { return null; }

        var steps = [];
        var known = 0;
        var maxKnown = 0;
        for (var name in parts) {
            if (!Object.prototype.hasOwnProperty.call(parts, name)) { continue; }
            var part = parts[name];
            if (part.score === null) { continue; }
            known += part.score;
            maxKnown += part.max;
            steps.push('· ' + part.label + ': ' + part.score + ' of ' + part.max);
        }
        var percent = Math.round((known / maxKnown) * 1000) / 10;
        var answer = 'Working through the guidebook marking model:\n\n' + steps.join('\n') + '\n\n'
            + 'Those give ' + known + ' of the ' + maxKnown + ' marks you supplied, which is ' + percent + '%.\n'
            + 'The whole award is ' + a.total + ' marks: Practical Internship ' + a.practical + ', Internship Report ' + a.report + ', Career Fair ' + a.career_fair + '.';
        if (maxKnown === a.total) {
            answer = 'Working through the guidebook marking model:\n\n' + steps.join('\n') + '\n\n'
                + 'Final mark: ' + known + ' of ' + a.total + ', which is ' + percent + '%.';
        }
        return { answer: answer, numbers: String(known + ' ' + maxKnown + ' ' + a.total + ' ' + a.practical + ' ' + a.report + ' ' + a.career_fair) };
    }

    function guideAnswer(sources) {
        var top = sources[0];
        var answer = 'Quoted from the internship guidebook, page ' + top.page + ':\n\n' + top.snippet;
        if (sources[1] && sources[1].score >= top.score * 0.55) {
            answer += '\n\nAlso quoted, page ' + sources[1].page + ':\n' + trim(sources[1].snippet, 420);
        }
        return answer;
    }

    /* --- snapshots ------------------------------------------------------ */

    var SNAP_KEY = 'leap.offline.diva.snap';
    var PENDING_KEY = 'leap.offline.diva.pending';

    // Saved answers and queued questions may carry personal figures, so they
    // live under the signed-in user's id; another account on this device
    // cannot read them even before the purge at sign-out runs.
    function storageKey(base) {
        var host = shell();
        var uid = host && host.getAttribute('data-user');
        return uid ? base + '.' + uid : base;
    }

    function readStore(key) {
        try { return JSON.parse(window.localStorage.getItem(key) || '{}') || {}; } catch (error) { return {}; }
    }

    function writeStore(key, value) {
        try { window.localStorage.setItem(key, JSON.stringify(value)); } catch (error) { /* private mode */ }
    }

    function remember(question, contract) {
        var store = readStore(storageKey(SNAP_KEY));
        var key = normalise(question);
        if (!key) { return; }
        store[key] = {
            question: question,
            answer: contract.answer,
            evidence: contract.evidence,
            next_action: contract.next_action,
            slots: contract.slots,
            intent: contract.intent,
            saved_at: new Date().toISOString(),
        };
        var keys = Object.keys(store);
        while (keys.length > 40) { delete store[keys.shift()]; }
        writeStore(storageKey(SNAP_KEY), store);
    }

    function findSnapshot(question, slots) {
        var store = readStore(storageKey(SNAP_KEY));
        var exact = store[normalise(question)];
        if (exact) { return exact; }
        // A differently worded records question can reuse a saved answer only
        // when it asks about the same subject with the same intent family.
        var best = null;
        for (var key in store) {
            if (!Object.prototype.hasOwnProperty.call(store, key)) { continue; }
            var row = store[key];
            if (!row.slots || row.slots.category !== 'records') { continue; }
            if (slots.category !== 'records') { continue; }
            if (row.slots.subject && slots.subject && row.slots.subject === slots.subject) {
                best = row;
            }
        }
        return best;
    }

    function queuePending(question) {
        var list = [];
        try { list = JSON.parse(window.localStorage.getItem(storageKey(PENDING_KEY)) || '[]') || []; } catch (error) { list = []; }
        // Asking the same thing twice offline is one question to save.
        for (var i = list.length - 1; i >= 0; i--) {
            if (list[i] && list[i].question === question) { list.splice(i, 1); }
        }
        list.push({ question: question, at: new Date().toISOString(), chat_id: shell() ? shell().getAttribute('data-chat') || '0' : '0' });
        while (list.length > 20) { list.shift(); }
        try { window.localStorage.setItem(storageKey(PENDING_KEY), JSON.stringify(list)); } catch (error) { /* ignore */ }
    }

    /* --- verification --------------------------------------------------- */

    function verify(answer, evidence, sources, question, slots) {
        for (var i = 0; i < evidence.length; i++) {
            if (evidence[i].type !== 'guidebook_quote') { continue; }
            var page = pageOf(evidence[i].page);
            if (!page) { return { ok: false, reason: 'a quotation cites a page the guidebook does not have', unverified: [] }; }
            var quote = normalise(evidence[i].value).replace(/\s*…\s*$/, '');
            if (quote && normalise(page.text).indexOf(quote) === -1) {
                return { ok: false, reason: 'a quotation is not found word for word on its page', unverified: [] };
            }
        }

        var allowed = {};
        function add(text) {
            var found = numbers(text);
            for (var k = 0; k < found.length; k++) { allowed[found[k]] = true; }
        }
        for (var e = 0; e < evidence.length; e++) {
            add(evidence[e].value);
            if (evidence[e].page) { add(String(evidence[e].page)); }
        }
        for (var s = 0; s < sources.length; s++) {
            var cited = pageOf(sources[s].page);
            if (cited) { add(cited.text); }
            add(String(sources[s].page));
        }
        add(question);
        add(JSON.stringify(slots));
        for (var key in marks()) {
            if (Object.prototype.hasOwnProperty.call(marks(), key)) { add(String(marks()[key])); }
        }
        add(String(index.guidebook.pages));
        var snap = findSnapshot(question, slots);
        if (snap) { add(snap.answer); }

        var unverified = [];
        var claimed = numbers(stripDates(answer));
        for (var n = 0; n < claimed.length; n++) {
            if (!allowed[claimed[n]]) { unverified.push(claimed[n]); }
        }
        if (unverified.length) {
            return { ok: false, reason: 'a figure could not be traced to the records or the guidebook', unverified: unverified };
        }
        return { ok: true, reason: '', unverified: [] };
    }

    function refusal(check) {
        var detail = check.unverified.length ? ' The figures ' + check.unverified.join(', ') + ' could not be traced.' : '';
        return 'I stopped short of answering: ' + check.reason + '.' + detail + '\n\n'
            + 'Every answer I give must be checkable against the guidebook or your own records, so I will not present a draft that fails that check.\n\n'
            + 'Try rephrasing the question, or ask about a deadline, the logbook, the internship report or the assessment weights.';
    }

    /* --- contract ------------------------------------------------------- */

    function nextActionFor(slots, sources) {
        var route = null;
        var label = null;
        var params = {};
        if (slots.subject === 'logbook') { route = role() === 'student' ? 'student/logbook' : 'mentor/logbook'; label = 'Open the logbook'; }
        else if (slots.subject === 'report') { route = role() === 'student' ? 'student/report' : 'mentor/reports'; label = 'Open the report'; }
        else if (slots.subject === 'career_fair') { route = 'student/career-fair'; label = 'Open Career Fair 2026'; }
        else if (slots.subject === 'placement') { route = role() === 'student' ? 'student/placement' : 'coordinator/placements'; label = 'Open placements'; }
        else if (slots.subject === 'results') { route = 'student/results'; label = 'Open my results'; }
        else if (sources && sources.length) { route = 'diva/source'; label = 'Guidebook page ' + sources[0].page; params = { page: sources[0].page }; }
        else { route = 'diva'; label = 'Ask something else'; }
        return route ? { label: label, route: route, params: params } : null;
    }

    function answer(question, history) {
        var text = String(question || '').replace(/\s+/g, ' ').replace(/^\s+|\s+$/g, '');
        if (!text) {
            return Promise.resolve({
                mode: 'offline', intent: 'empty', slots: { category: 'self', role: role(), subject: null, component: null, week: null, form: null },
                evidence: [], confidence: 'high',
                answer: 'Ask me about a deadline, the logbook, the report, the assessment weights or a page of the guidebook.',
                next_action: null,
            });
        }

        return load().then(function () {
            history = history || [];
            var working = text.replace(/^(hi+|hello+|hey+|thanks|thank you|thx|cheers)\b[\s\p{P}]*/iu, '') || text;
            var why = WHY.test(working);
            var elaborate = ELABORATE.test(working);
            var previous = lastName(history);
            var intent = null;
            var carried = null;
            var sources = [];
            var evidence = [];
            var confidence = 'high';
            var tier = 'rules';
            var answerText = '';
            var resolved = working;

            if (why) {
                intent = 'followup.why';
                var lastTurn = history.length ? history[history.length - 1] : null;
                if (lastTurn && lastTurn.reasoning && lastTurn.reasoning.length) {
                    var steps = [];
                    for (var i = 0; i < lastTurn.reasoning.length; i++) { steps.push((i + 1) + '. ' + lastTurn.reasoning[i]); }
                    answerText = 'Here is the recorded basis for my answer to “' + lastTurn.question + '”:\n\n' + steps.join('\n');
                    evidence = [{ type: 'rule', value: 'recorded basis of the previous offline answer' }];
                } else {
                    intent = 'clarify';
                    answerText = 'There is no previous answer on this device to explain. Ask your question and I will record how I reached the answer.';
                    evidence = [{ type: 'rule', value: 'no previous answer' }];
                }
            } else if (elaborate) {
                intent = 'followup.more';
                if (!previous) {
                    intent = 'clarify';
                    answerText = 'Which internship topic would you like more detail on?';
                    evidence = [{ type: 'rule', value: 'no recent topic' }];
                } else {
                    carried = previous.question;
                    resolved = previous.resolved || previous.question;
                    sources = retrieve(resolved, 4);
                    if (!sources.length) {
                        answerText = 'I could not find additional guidebook excerpts for that topic on this device.';
                        evidence = [{ type: 'rule', value: 'no further excerpts' }];
                    } else {
                        tier = 'retrieval';
                        answerText = 'For “' + previous.question + '”, here are related excerpts, limited to the guidebook pages returned by this search:';
                        for (var s = 0; s < sources.length; s++) {
                            answerText += '\n\nPage ' + sources[s].page + ':\n' + trim(sources[s].snippet, 420);
                            evidence.push({ type: 'guidebook_quote', value: sources[s].snippet, page: sources[s].page });
                        }
                        confidence = confidenceFor(sources);
                    }
                }
            } else {
                var resolvedFollow = resolveFollowUp(working, history);
                if (resolvedFollow) {
                    working = resolvedFollow.question;
                    resolved = working;
                    if (resolvedFollow.intent === 'clarify') {
                        intent = 'clarify';
                    } else {
                        intent = resolvedFollow.intent;
                        carried = (lastName(history) || {}).question || null;
                    }
                } else {
                    intent = route(working);
                }

                if (intent === 'clarify') {
                    answerText = 'Which subject do you mean, and what would you like to know about it — its deadline, weighting or status? Name the report, logbook, Career Fair or another internship topic.';
                    evidence = [{ type: 'rule', value: 'no unambiguous subject' }];
                } else if (intent === 'greeting') {
                    answerText = 'Hello. I am DIVA, running on this device. Without a connection I can quote the internship guidebook, explain the marking model and show the answers this device saved while it was online. Live records need a connection.';
                    evidence = [{ type: 'rule', value: 'greeting' }];
                } else if (intent === 'gratitude') {
                    answerText = 'You are welcome. Ask me about the guidebook or the marking model any time — they work without a connection.';
                    evidence = [{ type: 'rule', value: 'gratitude' }];
                } else if (intent === 'capabilities') {
                    answerText = 'I am DIVA, the Digital Internship Virtual Assistant, running here on your device.\n\nOffline I can: quote the 25-page internship guidebook with page numbers; explain the 200-mark assessment model and do the arithmetic; and repeat answers this device saved while online.\n\nOffline I cannot read live records, save a conversation, approve work or send messages. Those need the server.';
                    evidence = [{ type: 'rule', value: 'self-description' }];
                } else if (intent === 'weighting' || intent === 'weighting.logbook') {
                    var worked = arithmetic(working);
                    if (worked) {
                        answerText = worked.answer;
                        evidence = [{ type: 'figure', value: 'marks from the question: ' + worked.numbers }];
                    } else {
                        answerText = weightingAnswer();
                        evidence = [{ type: 'figure', value: 'Assessment model: practical ' + marks().practical + ', report ' + marks().report + ', career fair ' + marks().career_fair + ', total ' + marks().total, page: 24 }];
                    }
                    sources = retrieve('form e assessment weighting', 2);
                    for (var q = 0; q < sources.length; q++) {
                        evidence.push({ type: 'guidebook_quote', value: sources[q].snippet, page: sources[q].page });
                    }
                } else if (intent === 'evaluation' || intent === 'internship.duration' || intent === 'internship.type') {
                    sources = retrieve(working, 3);
                    if (sources.length) {
                        tier = 'retrieval';
                        answerText = guideAnswer(sources);
                        confidence = confidenceFor(sources);
                        for (var r = 0; r < sources.length; r++) {
                            evidence.push({ type: 'guidebook_quote', value: sources[r].snippet, page: sources[r].page });
                        }
                    } else {
                        answerText = 'I could not find that in the guidebook copy on this device.';
                        evidence = [{ type: 'rule', value: 'no passage' }];
                        confidence = 'low';
                    }
                } else if (intent && /^deadline\.|^results$|^progress$|^placement$|^apply$/.test(intent)) {
                    var slots = slotsFor(working, intent, 'rules');
                    var saved = findSnapshot(working, slots);
                    if (saved) {
                        answerText = saved.answer + '\n\n(saved on this device on ' + new Date(saved.saved_at).toLocaleString() + ')';
                        evidence = [{ type: 'cached_snapshot', value: 'answer saved while online on ' + saved.saved_at }];
                        carried = null;
                    } else {
                        answerText = 'That answer needs your live records, and this device is offline. I have not saved an answer to that question yet. When you are back online, ask it once and I will keep the answer here for next time.';
                        evidence = [{ type: 'rule', value: 'live records unavailable offline' }];
                        confidence = 'low';
                    }
                } else {
                    sources = retrieve(working, 3);
                    var supported = sources.length && sources[0].coverage >= 0.34 && sources[0].score >= 2;
                    if (!supported) {
                        answerText = 'I could not find a sufficiently supported answer to that question in the guidebook copy on this device.\n\nIf a passage only shares a word or two with your question, I will not quote it — an unrelated quotation is worse than an honest refusal.';
                        evidence = [{ type: 'rule', value: 'no supported passage' }];
                        confidence = 'low';
                    } else {
                        tier = 'retrieval';
                        answerText = guideAnswer(sources);
                        confidence = confidenceFor(sources);
                        for (var u = 0; u < sources.length; u++) {
                            evidence.push({ type: 'guidebook_quote', value: sources[u].snippet, page: sources[u].page });
                        }
                        if (confidence === 'low') {
                            answerText += '\n\nNote: the guidebook match is weak, so check the source before relying on this.';
                        }
                    }
                }
            }

            var slotsFinal = slotsFor(resolved, intent || '', tier);
            var check = verify(answerText, evidence, sources, resolved, slotsFinal);
            if (!check.ok) {
                answerText = refusal(check);
                evidence = [{ type: 'rule', value: 'verification.refused' }];
                sources = [];
                confidence = 'low';
            }

            LAST = { question: text, contract: null };
            var contract = {
                mode: 'offline',
                intent: intent || 'none',
                slots: slotsFinal,
                evidence: evidence,
                confidence: confidence,
                answer: answerText,
                next_action: nextActionFor(slotsFinal, sources),
            };
            LAST.contract = contract;
            return contract;
        });
    }

    function confidenceFor(sources) {
        if (!sources.length) { return 'low'; }
        var coverage = sources[0].coverage;
        if (coverage >= 0.75 && sources[0].score >= 4) { return 'high'; }
        if (coverage >= 0.5) { return 'medium'; }
        return 'low';
    }

    /* --- loading -------------------------------------------------------- */

    function load() {
        if (index) { return Promise.resolve(index); }
        if (loadPromise) { return loadPromise; }
        var url = INDEX_URL || indexUrl();
        loadPromise = fetch(url, { credentials: 'same-origin' }).then(function (response) {
            if (!response.ok) { throw new Error('index unavailable'); }
            return response.json();
        }).then(function (data) {
            index = data;
            STOP = data.stop || [];
            SYNONYMS = data.synonyms || {};
            stats = null;
            return index;
        }).catch(function (error) {
            loadPromise = null;
            throw error;
        });
        return loadPromise;
    }

    function indexUrl() {
        var host = shell();
        var given = host && host.getAttribute('data-index');
        if (given) { return given; }
        return 'public/assets/data/diva-index.json';
    }

    /* --- chat UI -------------------------------------------------------- */

    function shell() {
        return document.querySelector('[data-diva-shell]');
    }

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    function historyFromDom() {
        var turns = [];
        var nodes = document.querySelectorAll('[data-diva-turn]');
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            var intent = node.getAttribute('data-intent') || '';
            var sources = [];
            try { sources = JSON.parse(node.getAttribute('data-sources') || '[]') || []; } catch (error) { sources = []; }
            turns.push({
                question: node.getAttribute('data-question') || '',
                resolved: node.getAttribute('data-resolved') || node.getAttribute('data-question') || '',
                intent: intent,
                tier: node.getAttribute('data-tier') || 'rules',
                reasoning: [],
                sources: sources,
            });
        }
        return turns.slice(-6);
    }

    function metaLine(text) {
        var span = document.createElement('span');
        span.className = 'small muted';
        span.textContent = text;
        return span;
    }

    function renderTurn(question, contract, saved) {
        var stream = document.querySelector('[data-diva-stream]');
        if (!stream) { return; }

        var welcome = stream.querySelector('.diva-welcome');
        if (welcome) { welcome.parentNode.removeChild(welcome); }

        var mine = document.createElement('article');
        mine.className = 'chat-msg is-mine';
        mine.setAttribute('data-diva-turn', '');
        mine.setAttribute('data-question', question);
        mine.setAttribute('data-resolved', question);
        mine.setAttribute('data-intent', contract.intent || '');
        mine.setAttribute('data-tier', contract.mode === 'online' ? 'server' : 'local');
        mine.setAttribute('data-sources', JSON.stringify(contract.evidence.filter(function (item) {
            return item.type === 'guidebook_quote';
        }).map(function (item) { return { page: item.page, snippet: trim(item.value, 240) }; })));
        var mineBubble = document.createElement('div');
        mineBubble.className = 'chat-bubble';
        var mineMeta = document.createElement('div');
        mineMeta.className = 'chat-meta';
        var mineName = document.createElement('strong');
        mineName.textContent = 'You';
        mineMeta.appendChild(mineName);
        var mineBody = document.createElement('p');
        mineBody.className = 'chat-body diva-message-text';
        mineBody.textContent = question;
        mineBubble.appendChild(mineMeta);
        mineBubble.appendChild(mineBody);
        mine.appendChild(mineBubble);
        stream.appendChild(mine);

        var reply = document.createElement('article');
        reply.className = 'chat-msg diva-response';
        var avatar = document.createElement('span');
        avatar.className = 'chat-avatar diva-avatar';
        avatar.setAttribute('aria-hidden', 'true');
        var avatarImg = document.createElement('img');
        avatarImg.src = (document.querySelector('.diva-brand img') || {}).src || '';
        avatarImg.width = 30;
        avatarImg.height = 30;
        avatarImg.alt = '';
        avatar.appendChild(avatarImg);

        var bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        var meta = document.createElement('div');
        meta.className = 'chat-meta';
        var who = document.createElement('strong');
        who.textContent = 'DIVA';
        meta.appendChild(who);
        var where = contract.mode === 'online'
            ? 'saved to your account'
            : 'on this device' + (saved ? ' · saved answer' : '');
        meta.appendChild(metaLine((CATEGORY_LABEL[(contract.slots || {}).category] || 'Guidebook') + ' · ' + where));
        bubble.appendChild(meta);

        if (contract.intent === 'none' || contract.confidence === 'low') {
            bubble.appendChild(metaLine('Match confidence: ' + contract.confidence));
        }

        var body = document.createElement('div');
        body.className = 'chat-body diva-message-text';
        body.textContent = contract.answer;
        bubble.appendChild(body);

        var quotes = contract.evidence.filter(function (item) { return item.type === 'guidebook_quote'; });
        if (quotes.length) {
            var details = document.createElement('details');
            details.className = 'diva-sources';
            var summary = document.createElement('summary');
            summary.textContent = plural(quotes.length, 'guidebook source');
            details.appendChild(summary);
            var list = document.createElement('ul');
            quotes.forEach(function (item) {
                var li = document.createElement('li');
                var link = document.createElement('a');
                link.href = routeUrl('diva/source', { page: item.page });
                link.textContent = 'Guidebook page ' + item.page;
                var snippet = document.createElement('p');
                snippet.className = 'small muted';
                snippet.textContent = trim(item.value, 240);
                li.appendChild(link);
                li.appendChild(snippet);
                list.appendChild(li);
            });
            details.appendChild(list);
            bubble.appendChild(details);
        }

        if (contract.next_action) {
            var links = document.createElement('div');
            links.className = 'diva-links';
            var cta = document.createElement('a');
            cta.className = 'btn btn-sm btn-outline';
            cta.href = routeUrl(contract.next_action.route, contract.next_action.params);
            cta.textContent = contract.next_action.label;
            links.appendChild(cta);
            bubble.appendChild(links);
        }

        reply.appendChild(avatar);
        reply.appendChild(bubble);
        stream.appendChild(reply);

        if (contract.mode !== 'online') {
            var offlineNote = document.createElement('p');
            offlineNote.className = 'hint';
            offlineNote.textContent = 'Answered on this device. It is not saved to your account and will be saved when you reconnect.';
            if (saved) { offlineNote.textContent = 'Answered from a copy saved on this device while online. Ask again when connected for current records.'; }
            stream.appendChild(offlineNote);
        }

        reply.scrollIntoView({ block: 'end', behavior: 'smooth' });
    }

    function routeUrl(route, params) {
        var base = (shell() && shell().getAttribute('data-base')) || '';
        var query = 'r=' + encodeURIComponent(route);
        params = params || {};
        for (var key in params) {
            if (Object.prototype.hasOwnProperty.call(params, key)) {
                query += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
            }
        }
        return base + '/index.php?' + query;
    }

    function setBusy(busy) {
        var form = document.querySelector('[data-diva-form]');
        if (!form) { return; }
        var button = form.querySelector('button[type="submit"]');
        var status = form.querySelector('[data-diva-status]');
        if (button) {
            button.disabled = busy;
            button.textContent = busy ? 'Thinking…' : 'Send question ↑';
        }
        if (status) { status.hidden = !busy; }
    }

    function postToServer(question, chatId) {
        var host = shell();
        var api = host && host.getAttribute('data-api');
        if (!api) { return Promise.reject(new Error('no api')); }
        var body = new FormData();
        body.append('_csrf', csrf());
        body.append('question', question);
        body.append('chat_id', chatId || host.getAttribute('data-chat') || '0');
        body.append('from', host.getAttribute('data-from') || '');
        return fetch(api, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'leap-diva' },
        }).then(function (response) {
            return response.json().catch(function () { return { ok: false }; }).then(function (json) {
                return { status: response.status, json: json };
            });
        });
    }

    function ask(question) {
        var text = String(question || '').trim();
        if (!text) { return; }
        setBusy(true);
        LAST.question = text;

        var online = navigator.onLine && window.LeapServerReachable !== false;
        var run = online
            ? postToServer(text).then(function (result) {
                if (result.status === 200 && result.json && result.json.ok) {
                    var turn = result.json.turn;
                    var contract = result.json.contract;
                    remember(text, contract);
                    renderTurn(text, contract, false);
                    if (result.json.chat_id) {
                        var host = shell();
                        if (host) { host.setAttribute('data-chat', String(result.json.chat_id)); }
                    }
                    return true;
                }
                return false;
            }).catch(function () { return false; })
            : Promise.resolve(false);

        run.then(function (saved) {
            if (saved) { return; }
            var offline = !navigator.onLine || window.LeapServerReachable === false;
            window.LeapServerReachable = false;
            return answer(text, historyFromDom()).then(function (contract) {
                renderTurn(text, contract, contract.evidence.some(function (item) { return item.type === 'cached_snapshot'; }));
                queuePending(text);
            });
        }).catch(function () {
            var stack = document.querySelector('[data-toasts]');
            if (stack) {
                var node = document.createElement('div');
                node.className = 'toast';
                node.textContent = 'DIVA could not read its offline index on this device.';
                stack.appendChild(node);
                window.setTimeout(function () { if (node.parentNode) { node.parentNode.removeChild(node); } }, 5200);
            }
        }).then(function () { setBusy(false); });
    }

    function bind() {
        var host = shell();
        if (!host) { return; }

        // Older builds stored answers without a user id; that data can belong
        // to anyone who used this device, so drop it rather than reuse it.
        ['leap.offline.diva.snap', 'leap.offline.diva.pending'].forEach(function (key) {
            try { window.localStorage.removeItem(key); } catch (error) { /* ignore */ }
        });

        var forms = document.querySelectorAll('form[data-diva-form], form[data-diva-prompt]');
        for (var i = 0; i < forms.length; i++) {
            (function (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    var button = event.submitter;
                    var question = button && button.name === 'question' ? button.value : '';
                    if (!question) {
                        var field = form.querySelector('textarea[name="question"], input[name="question"]');
                        question = field ? field.value : '';
                    }
                    ask(question);
                    if (form.hasAttribute('data-diva-form')) {
                        var field2 = form.querySelector('textarea[name="question"]');
                        if (field2) { field2.value = ''; }
                    }
                });
            })(forms[i]);
        }

        var input = document.querySelector('[data-diva-form] textarea[name="question"]');
        if (input) {
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    var form = input.closest('form');
                    if (form) { form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true })); }
                }
            });
        }

        window.addEventListener('online', function () {
            var pending = [];
            try { pending = JSON.parse(window.localStorage.getItem(storageKey(PENDING_KEY)) || '[]') || []; } catch (error) { pending = []; }
            if (!pending.length) { return; }
            var done = 0;
            var chatId = (shell() && shell().getAttribute('data-chat')) || '0';
            var chain = Promise.resolve();
            pending.forEach(function (row) {
                chain = chain.then(function () {
                    var target = row.chat_id && row.chat_id !== '0' ? row.chat_id : chatId;
                    return postToServer(row.question, target).then(function (result) {
                        if (result.status === 200 && result.json && result.json.ok) {
                            done++;
                            chatId = String(result.json.chat_id || chatId);
                            var host = shell();
                            if (host && chatId !== '0') { host.setAttribute('data-chat', chatId); }
                            remember(row.question, result.json.contract);
                        }
                    }).catch(function () { /* next time */ });
                });
            });
            chain.then(function () {
                if (done > 0) {
                    try { window.localStorage.setItem(storageKey(PENDING_KEY), '[]'); } catch (error) { /* ignore */ }
                    var stack = document.querySelector('[data-toasts]');
                    if (stack) {
                        var node = document.createElement('div');
                        node.className = 'toast';
                        node.textContent = plural(done, 'question') + ' answered on this device ' + (done === 1 ? 'has' : 'have') + ' been saved to your account.';
                        stack.appendChild(node);
                        window.setTimeout(function () { if (node.parentNode) { node.parentNode.removeChild(node); } }, 5600);
                    }
                }
            });
        });
    }

    window.LeapDiva = {
        answer: answer,
        load: load,
        version: 1,
        indexUrl: function (url) { if (url) { INDEX_URL = url; } return INDEX_URL; },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
