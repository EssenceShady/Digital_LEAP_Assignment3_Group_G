const fs = require('fs');
const outline = fs.readFileSync('outline.txt', 'utf8');
const all = outline.split('\n').map(l => l.split('\t').slice(2).join('\t')).join('\n');

const edits = {
  replacements: [
    ["The mapping produced twenty-four tables (Table 3, Figure 2).",
     "The mapping produced twenty-four tables (Table 3, Figure 2). A later module for placement messaging adds four conversation tables, so the final schema has twenty-eight, all catalogued in Table 5."],
    ["the other thirteen are operational", "the other seventeen are operational"],
    ["The data tier is MariaDB with twenty-four InnoDB tables.", "The data tier is MariaDB with twenty-eight InnoDB tables."],
    ["thirty-six permissions span six roles", "thirty-eight permissions span six roles"],
    ["The 36 permission keys used by guards and navigation", "The 38 permission keys used by guards and navigation"],
    ["36 permissions, route-level Guard", "38 permissions, route-level Guard"],
    ["nine data stores, twenty-four normalised tables", "nine data stores, twenty-eight normalised tables"],
    ["Realistic seed data and sixty-one executed cases demonstrate the system end to end.", "Realistic seed data and ninety-one executed cases demonstrate the system end to end."],
    ["seventeen defects were found and fixed.", "defects were found in every pass and each was fixed before the next run; docs/test-log.md numbers them D1 to D26."],
    ["found six more.", "found nine more (D12–D20)."],
    ["Sixty-one cases were executed and all passed after the fixes (Table 8), a 100 per cent rate for the executed set.", "Ninety-one cases were executed and all passed after the fixes (Table 8), a 100 per cent rate for the executed set."],
    ["The idle timeout and SMTP delivery were not executed live", "The idle timeout, SMTP delivery and the optional local model were not executed live"],
    ["contains the sixty-one cases and fixes", "contains the ninety-one cases and fixes"],
    ["Twenty-four tables preserve the evidence behind every mark", "Twenty-eight tables preserve the evidence behind every mark"],
    ["Date of submission: 14 November 2026", "Date of submission: 15 November 2026"],
    ["Part E role workflows and marking; Part F evaluation.", "Part E role workflows, placement messaging, the assistant and marking; Part F evaluation."]
  ],
  insertsAfter: [
    ["found no horizontal page overflow.",
     " The fifth pass repeated the audit at 279 px in the application’s embedded browser."],
    ["no page should scroll sideways.",
     " The fifth pass repeated the check at 279 px and also ran the twenty-question DIVA evaluation set with bin/diva-eval.php."],
    ["Security is tested rather than assumed, and the audit trail supports accountability.",
     " The assistant answers only from local records and the offline guidebook, and its audit rows record that it was asked without storing the question."],
    ["printing is optimised for A4 rather than arbitrary paper.",
     " Two limits belong to the newest modules. DIVA cannot read PDF text offline, so a report supplied only as PDF must be re-sent as .docx or pasted, and the optional local model was bounded and disabled but never exercised against a running model. Messaging is a placement channel rather than a general inbox: there is no search, no delivery receipt and no thread with anyone outside the placement."],
    ["The artefact is deliberately small and offline-first.",
     " A three-tier assistant and placement messaging extend the same record instead of opening a second product."],
    ["both Apache and the built-in server preserve.",
     " The profile, log book and ranked-list work added three more: the photo preview now runs from a blob URL the image policy allows, asset URLs carry a file version so a change is fetched at once, and the ranked preferences row became a card that keeps its controls with the row."]
  ],
  sections: [
    { level: 2, text: "5.8 Placement messaging" },
    { level: 0, text: "A supervision question asked by email is lost to the placement record, so messaging is attached to the placement rather than to a private inbox. Four tables — conversation, conversation_member, message and message_reaction — carry the thread, its members and their read positions. Membership is decided by the service, not by the form: a student can reach only the assigned mentor and the coordinator, a mentor reaches the students they supervise and the coordinator, and a crafted start request naming another student returns HTTP 403 and writes a message.denied event." },
    { level: 0, text: "The thread behaves like a familiar messenger without becoming a social network. A reply stores parent_message_id and renders the quoted parent above the new text; reactions are limited to five emoji; unread counts derive from conversation_member.last_read_message_id and appear in the navigation. A sender may edit a text message for fifteen minutes, after which the control disappears and the server refuses the change; deletion writes deleted_at and leaves a tombstone so replies keep their anchor. A client_token makes a retried send idempotent, attachments pass through Upload::store against the placement, and the open thread polls every eight seconds while the tab is visible. Every messaging table is utf8mb4, so emoji survive the round trip." },
    { level: 0, text: "Messaging never bypasses the record: message.sent, message.edited, message.deleted and message.denied join the audit trail. The host supervisor keeps the three-way logbook discussion in logbook_comment and has no messaging permission, so the placement conversation stays between the student, the mentor and the coordinator." },
    { level: 2, text: "5.9 DIVA assistant" },
    { level: 0, text: "Students and staff need a policy answer while they are filling a form, which is when leaving the system is most disruptive. DIVA, the Digital Internship Virtual Assistant, answers from two sources only: the records the signed-in account may already read, and an offline text extract of the internship guidebook in storage/knowledge/guidebook.txt. It makes no cloud call, carries no key and sends no question to an outside service. An optional third tier calls a model on 127.0.0.1 or localhost, is disabled by default and is abandoned after 400 ms, so an unavailable model cannot delay an answer; every reply names the tier that produced it." },
    { level: 0, text: "Intent rules run first: a deadline, a weighting, a queue or a progress question is answered with a number read from the database, scoped by role, and linked to the page that acts on it. If no rule applies, a ranked search over the 25-page extract returns the strongest passages with page citations, and a source page opens inside the application without losing the question. The report checker reads a .docx with ZipArchive or pasted text, judges the eight Form E criteria against the text, quotes the student’s own line as evidence and runs presentation checks. It never prints a mark and never rewrites a sentence; PDFs are refused with an explanation because the build has no PDF parser. Questions are capped at 500 characters, and each query writes one diva.query audit row with the intent, tier, page numbers and a hash of the question — never its text." },
    { level: 0, text: "The assistant is read-only by construction: no DIVA route writes, approves, publishes or sends. The twenty-question evaluation set in bin/diva-eval.php exercises all six roles and reports the intent, the answering tier and the cited pages; the recorded run matched both the expected intent and tier twenty times, with eighteen answers from rules and two from guidebook retrieval, while the local tier reported itself disabled on a machine without a model." }
  ],
  paragraphBefore: {
    heading: "6.3 Limitations",
    text: "The final pass, which added the assistant and placement messaging, found six more (D21–D26): an early return in the local tier that made every answer claim a model, an intent order that answered count questions from the wrong rule, a missing logbook-weighting intent, guidebook pages attached to answers that cited none, a citation pattern that lost the second page of a range, and report evidence quoted from the Introduction for every criterion. All six were fixed and re-tested."
  },
  tables: {
    five: [
      ["conversation", "Placement thread with subject, topic and last activity", "placement_id, created_by"],
      ["conversation_member", "Who may read and post, with their last read message", "conversation_id, user_id"],
      ["message", "Text, system note or file message with parent and tombstone", "conversation_id, sender_id, parent_message_id, document_id"],
      ["message_reaction", "One emoji per user per message", "message_id, user_id"]
    ],
    six: [
      ["Send placement message", "Yes", "No", "Yes", "Yes", "No", "No"],
      ["Ask DIVA", "Yes", "Yes", "Yes", "Yes", "Yes", "Yes"]
    ],
    seven: [
      ["Message scope", "Placement-scoped service; a student reaches only the assigned mentor and coordinator", "TC-88; message.denied"],
      ["Assistant containment", "No DIVA route writes; answers are scoped by role; the optional model is loopback-only", "TC-72 to TC-80"],
      ["Assistant data minimisation", "diva.query stores the intent, tier, cited pages and a question hash only", "TC-80"]
    ],
    eight: [
      ["Authentication and session", "9", "9", "Lockout, unlock, forced password change, reset and invitation tokens, password policy, back-button cache"],
      ["Authorisation and input", "13", "13", "403 on six cross-role routes, IDOR letter and document, XSS escaping, CSRF 419 on both servers"],
      ["Logbook workflow", "6", "6", "Submit, return with and without comment, resubmit, mentor review"],
      ["Marking, publication and allocation", "8", "8", "Incomplete compute blocked, 77.10 B+, publish, recompute refused, duplicate year, supervisor mismatch, capacity, rank swap"],
      ["Documents, settings and exports", "6", "6", "PDF upload and download, review labels, weight validation, audit diff, mail queue, CSV"],
      ["Packaged build and presentation", "1", "1", "Apache subfolder install served with full styling"],
      ["Account migration, onboarding and interface revision", "7", "7", "Member accounts, password policy, alert height, review labels, back button, new registration"],
      ["Interface revision: mobile, feedback, tracking and guidance", "11", "11", "Four mobile widths, stacked tables, chart bars, three-way replies, loading bar, tracker, feedback, sample downloads, route crawl"],
      ["Programme list, log book, profile and charts", "6", "6", "Official programme list, 16-page log book template, photo preview and removal, ranked cards, chart labels, asset cache"],
      ["Career Fair 2026 and guide book styling", "4", "4", "Student upload and late flag, template delivery, styled log book, lint and routes"],
      ["DIVA assistant and placement messaging", "20", "20", "Three tiers, source pages, report critique and refusal, audit, evaluation set, message scope, replies, edit window, tombstones, idempotent token, polling"]
    ]
  }
};

const problems = [];
for (const [oldStr] of edits.replacements) {
  const n = all.split(oldStr).length - 1;
  if (n !== 1) problems.push('replacement count ' + n + ': ' + oldStr.slice(0, 60));
}
for (const [anchor] of edits.insertsAfter) {
  const n = all.split(anchor).length - 1;
  if (n !== 1) problems.push('anchor count ' + n + ': ' + anchor.slice(0, 60));
}
if (!all.includes('6. Part F — Evaluation')) problems.push('missing Part F heading');
if (!all.includes('6.3 Limitations')) problems.push('missing 6.3 heading');
if (problems.length) { console.error(problems.join('\n')); process.exit(1); }
fs.writeFileSync('edits.json', JSON.stringify(edits, null, 1), 'utf8');
console.log('edits.json written; replacements', edits.replacements.length, 'inserts', edits.insertsAfter.length, 'sections', edits.sections.length);
