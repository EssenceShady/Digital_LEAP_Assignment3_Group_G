import fs from 'node:fs';
import path from 'node:path';

const ROOT = 'C:/xampp/htdocs/Digital_LEAP_Assignment3_Group_G';
const SQL = fs.readFileSync(path.join(ROOT, 'database/digital_leap.sql'), 'utf8');
const OUT = path.join(ROOT, 'REPORT/figures');
fs.mkdirSync(OUT, { recursive: true });

const INK = '#1F2933', GREEN = '#0F3B2E', GOLD = '#B8953F', CREAM = '#FBF7EE',
  LINE = '#7A93A0', MUTED = '#5B6873', WHITE = '#FFFFFF';

/* ---------------- schema parse ---------------- */
const tables = {};
const re = /CREATE TABLE `?(\w+)`? \(([\s\S]*?)\n\) ENGINE/gi;
let m;
while ((m = re.exec(SQL))) {
  const [, name, body] = m;
  const cols = [], fk = {}, pk = new Set();
  for (const raw of body.split('\n')) {
    const line = raw.trim().replace(/,$/, '');
    if (!line) continue;
    const pkm = line.match(/^PRIMARY KEY \((.+)\)$/i);
    if (pkm) { pkm[1].replace(/[`\s]/g, '').split(',').forEach((c) => c && pk.add(c)); continue; }
    const fkm = line.match(/^CONSTRAINT \w+ FOREIGN KEY \(`?(\w+)`?\) REFERENCES `?(\w+)`?/i);
    if (fkm) { fk[fkm[1]] = fkm[2]; continue; }
    if (/^(CONSTRAINT|UNIQUE|KEY|INDEX)\b/i.test(line)) continue;
    const cm = line.match(/^`?(\w+)`?\s+([A-Za-z]+(?:\([^)]*\))?)/);
    if (!cm) continue;
    let type = cm[2].toUpperCase().replace(/^INT\(\d+\)$/, 'INT');
    cols.push({ name: cm[1], type, pk: pk.has(cm[1]), fk: fk[cm[1]] || null });
  }
  cols.forEach((c) => { c.pk = pk.has(c.name); c.fk = fk[c.name] || null; });
  tables[name] = cols;
}

/* ---------------- svg helpers ---------------- */
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const escAttr = (s) => String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
const svgOpen = (w, h) => `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}" font-family="Tahoma, Segoe UI, sans-serif">`;
const rect = (x, y, w, h, fill, stroke, rx = 8, sw = 1.4, extra = '') =>
  `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${rx}" fill="${fill}" stroke="${stroke}" stroke-width="${sw}" ${extra}/>`;
const txt = (x, y, s, size, fill, weight = 'normal', anchor = 'start', extra = '') =>
  `<text x="${x}" y="${y}" font-size="${size}" fill="${fill}" font-weight="${weight}" text-anchor="${anchor}" ${extra}>${esc(s)}</text>`;

const arrow = (x1, y1, x2, y2, color, width, dash = '') => {
  const ang = Math.atan2(y2 - y1, x2 - x1), len = 11;
  const p = (a) => `${(x2 - len * Math.cos(a)).toFixed(1)},${(y2 - len * Math.sin(a)).toFixed(1)}`;
  return `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="${color}" stroke-width="${width}" ${dash ? `stroke-dasharray="${dash}"` : ''}/>` +
    `<polygon points="${x2},${y2} ${p(ang - 0.42)} ${p(ang + 0.42)}" fill="${color}"/>`;
};

const cardWidth = 300;

const shortType = (t) => {
  if (/^ENUM/i.test(t)) return 'ENUM';
  return t.length > 12 ? t.slice(0, 11) + '\u2026' : t;
};

function drawCard(t, x, y, accent) {
  const cols = t.cols;
  const h = 40 + cols.length * 20 + 10;
  let s = rect(x, y, cardWidth, h, WHITE, accent, 8, 1.6);
  s += `<path d="M${x} ${y + 40} h${cardWidth}" stroke="${accent}" stroke-width="1.2"/>`;
  s += rect(x, y, cardWidth, 40, accent, accent, 8, 0);
  s += `<path d="M${x} ${y + 31} h${cardWidth} v9 h-${cardWidth} z" fill="${accent}"/>`;
  s += txt(x + 12, y + 26, t.name, 17, WHITE, 'bold');
  s += txt(x + cardWidth - 12, y + 26, t.tag, 11, '#EFE4C9', 'normal', 'end');
  cols.forEach((c, i) => {
    const cy = y + 40 + 20 * i + 15;
    const label = (c.pk ? 'PK ' : c.fk ? 'FK ' : '') + c.name;
    const col = c.pk ? GREEN : c.fk ? '#8A6D1F' : INK;
    s += txt(x + 12, cy, label, 13, col, c.pk ? 'bold' : 'normal');
    s += txt(x + cardWidth - 12, cy, shortType(c.type), 11.5, MUTED, 'normal', 'end');
  });
  return { svg: s, h };
}

const crop = (a, b) => {
  const cx = a.x + a.w / 2, cy = a.y + a.h / 2, dx = b.x + b.w / 2, dy = b.y + b.h / 2;
  const t = (hw, hh) => {
    const sx = dx - cx, sy = dy - cy;
    const k = Math.min(sx ? hw / Math.abs(sx) : Infinity, sy ? hh / Math.abs(sy) : Infinity);
    return [cx + sx * k, cy + sy * k];
  };
  return [t(a.w / 2, a.h / 2), t(b.w / 2, b.h / 2)];
};

/* ================= FIGURE 2 — logical ERD ================= */
function buildLogical() {
  const bands = [
    { title: 'Identity and access control', tables: ['roles', 'permissions', 'role_permissions', 'users', 'access_token'] },
    { title: 'Student, organisation and placement', tables: ['organisation', 'student', 'application', 'placement'] },
    { title: 'Logbook, visits and evidence', tables: ['logbook_entry', 'logbook_comment', 'site_visit', 'document', 'internship_report'] },
    { title: 'Evaluation and final mark', tables: ['criterion', 'evaluation', 'evaluation_score', 'grade_scale', 'mark'] },
    { title: 'Placement communication', tables: ['conversation', 'conversation_member', 'message', 'message_reaction'] },
    { title: 'Platform services', tables: ['notification', 'email_queue', 'audit_log', 'setting', 'feedback', 'diva_chat', 'diva_turn'] },
  ];
  const marginX = 60, bandGap = 96, gap = 40, header = 62;
  const maxCols = 7;
  const W = marginX * 2 + maxCols * cardWidth + (maxCols - 1) * gap;
  const nodes = {};
  let y = 130, body = '';

  bands.forEach((band, bi) => {
    const cards = band.tables.map((name) => {
      const t = tables[name];
      if (!t) throw new Error('missing table ' + name);
      return { name, cols: t };
    });
    const heights = cards.map((c) => 40 + c.cols.length * 20 + 10);
    const bandH = Math.max(...heights) + 28;
    body += rect(marginX - 18, y - 46, W - (marginX - 18) * 2, bandH + 74, CREAM, '#E7DCC4', 14, 1);
    body += txt(marginX - 2, y - 20, band.title, 17, GREEN, 'bold');
    cards.forEach((c, i) => {
      const x = marginX + i * (cardWidth + gap);
      c.tag = c.cols.length + ' cols';
      const d = drawCard(c, x, y, bi % 2 === 0 ? GREEN : '#8A5A2B');
      body += d.svg;
      nodes[c.name] = { x, y, w: cardWidth, h: d.h };
    });
    y += bandH + bandGap;
  });

  const edges = [];
  for (const [name, cols] of Object.entries(tables)) {
    for (const c of cols) if (c.fk && nodes[c.fk] && nodes[name]) edges.push([c.fk, name, c.name]);
  }
  const H = y + 40;
  let lines = '';
  const seen = new Set();
  for (const [from, to] of edges) {
    const key = from + '>' + to;
    if (seen.has(key)) continue;
    seen.add(key);
    const [p1, p2] = crop(nodes[from], nodes[to]);
    const strong = from === 'placement' || to === 'placement' || from === 'users' || to === 'users';
    lines += arrow(p1[0], p1[1], p2[0], p2[1], strong ? GOLD : LINE, strong ? 2.2 : 1.3, strong ? '' : '');
  }
  lines += arrow(0, 0, 0, 0, 'none', 0);

  const head = rect(W / 2 - 330, 42, 660, 56, GREEN, GREEN, 10, 0) +
    txt(W / 2, 78, 'Figure 2  Digital LEAP logical data model (30 tables)', 20, WHITE, 'bold', 'middle');

  return svgOpen(W, H) + `<rect width="${W}" height="${H}" fill="#FFFFFF"/>` + head + lines + body + svgOpen.length * 0 + '</svg>';
}

/* ================= FIGURE 1 — conceptual ERD ================= */
const CONCEPT = (() => {
  const W = 1900, H = 1180;
  const nodes = {
    Role: { x: 820, y: 140, w: 360, h: 90, note: 'administrator, coordinator, mentor, supervisor, student' },
    User: { x: 820, y: 300, w: 400, h: 90, note: 'one account, one role, many permissions' },
    Organisation: { x: 60, y: 460, w: 300, h: 90, note: 'host company and capacity' },
    Application: { x: 440, y: 460, w: 300, h: 90, note: 'ranked preference' },
    Student: { x: 820, y: 460, w: 400, h: 96, note: 'one internship record per intake' },
    DivaChat: { x: 1300, y: 460, w: 340, h: 90, note: 'assistant history per account' },
    Placement: { x: 620, y: 620, w: 400, h: 104, note: 'student placed with a mentor, supervisor and coordinator' },
    Conversation: { x: 1120, y: 620, w: 400, h: 104, note: 'quoted placement messages' },
    SiteVisit: { x: 60, y: 820, w: 300, h: 92, note: 'mentor visit and findings' },
    LogbookEntry: { x: 400, y: 820, w: 340, h: 92, note: 'weekly log across twelve weeks' },
    Evaluation: { x: 780, y: 820, w: 300, h: 92, note: 'Form D, mentor and self' },
    Report: { x: 1120, y: 820, w: 300, h: 92, note: 'internship report' },
    CareerFair: { x: 1460, y: 820, w: 300, h: 92, note: 'participation report' },
    Mark: { x: 780, y: 1000, w: 300, h: 92, note: 'out of 200, moderated, published' },
  };
  const edges = [
    { a: 'Role', b: 'User', p: [[1000, 230], [1000, 300]], card: '1 : M', verb: 'grants', l: [1012, 262], rot: false, anchor: 'start' },
    { a: 'User', b: 'Student', p: [[1020, 390], [1020, 460]], card: '1 : 0..1', verb: 'is a', l: [1032, 432], rot: false, anchor: 'start' },
    { a: 'Organisation', b: 'Application', p: [[400, 505], [440, 505]], card: '1 : M', verb: 'offers', l: [420, 574], rot: false, anchor: 'middle' },
    { a: 'Student', b: 'Application', p: [[740, 505], [820, 505]], card: '1 : M', verb: 'submits', l: [796, 574], rot: false, anchor: 'start' },
    { a: 'Organisation', b: 'Placement', p: [[210, 550], [210, 660], [620, 660]], card: '1 : M', verb: 'hosts', l: [224, 600], rot: false, anchor: 'start' },
    { a: 'User', b: 'Placement', p: [[820, 345], [780, 345], [780, 600], [820, 600], [820, 620]], card: '1 : M', verb: 'mentors / supervises', l: [766, 438], rot: true, anchor: 'middle' },
    { a: 'Student', b: 'Placement', p: [[960, 556], [960, 620]], card: '1 : 1', verb: 'holds', l: [974, 594], rot: false, anchor: 'start' },
    { a: 'User', b: 'DivaChat', p: [[1220, 345], [1244, 345], [1244, 505], [1300, 505]], card: '1 : M', verb: 'owns', l: [1240, 425], rot: true, anchor: 'middle' },
    { a: 'Placement', b: 'Conversation', p: [[1020, 670], [1120, 670]], card: '1 : 0..1', verb: 'has', l: [1070, 655], rot: false, anchor: 'middle' },
    { a: 'Placement', b: 'SiteVisit', p: [[660, 724], [660, 760], [210, 760], [210, 820]], card: '1 : M', verb: 'receives', l: [430, 748], rot: false, anchor: 'middle' },
    { a: 'Placement', b: 'LogbookEntry', p: [[700, 724], [700, 790], [570, 790], [570, 820]], card: '1 : M', verb: 'records', l: [640, 812], rot: false, anchor: 'middle' },
    { a: 'Placement', b: 'Evaluation', p: [[900, 724], [900, 820]], card: '1 : M', verb: 'scored by', l: [914, 752], rot: false, anchor: 'start' },
    { a: 'Placement', b: 'Report', p: [[980, 724], [980, 770], [1270, 770], [1270, 820]], card: '1 : 0..1', verb: 'submits', l: [1150, 758], rot: false, anchor: 'middle' },
    { a: 'Placement', b: 'CareerFair', p: [[1020, 640], [1060, 640], [1060, 590], [1700, 590], [1700, 820]], card: '1 : 0..1', verb: 'submits', l: [1686, 705], rot: true, anchor: 'middle' },
    { a: 'Evaluation', b: 'Mark', p: [[930, 912], [930, 1000]], card: '1 : 0..1', verb: 'produces', l: [944, 948], rot: false, anchor: 'start' },
  ];
  return { W, H, nodes, edges };
})();

function buildConceptual() {
  const { W, H, nodes, edges } = CONCEPT;
  let s = svgOpen(W, H) + `<rect width="${W}" height="${H}" fill="#FFFFFF"/>`;
  s += rect(W / 2 - 380, 34, 760, 56, GREEN, GREEN, 10, 0) +
    txt(W / 2, 70, 'Figure 1  Conceptual entity relationship diagram', 20, WHITE, 'bold', 'middle');

  const boxes = Object.entries(nodes).map(([k, n]) => ({ k, ...n }));
  const hit = (a, b) => a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h;
  const issues = [];
  const lbs = [];

  let lanes = '';
  edges.forEach((e, i) => {
    lanes += `<polyline points="${e.p.map(([x, y]) => x + ',' + y).join(' ')}" fill="none" stroke="${LINE}" stroke-width="1.8" stroke-linejoin="round"/>`;
    lanes += `<text x="${e.l[0]}" y="${e.l[1]}" font-size="12.5" text-anchor="${e.anchor}" ${e.rot ? `transform="rotate(-90 ${e.l[0]} ${e.l[1]})"` : ''}><tspan font-weight="bold" fill="${GREEN}">${esc(e.card)}</tspan><tspan fill="${MUTED}" dx="6">${esc(e.verb)}</tspan></text>`;

    const wpx = 6.4 * (e.card.length + e.verb.length + 2) + 6, hpx = 14;
    const lb = e.rot ? { x: e.l[0] - hpx, y: e.l[1] - wpx / 2, w: hpx, h: wpx }
      : { x: e.anchor === 'middle' ? e.l[0] - wpx / 2 : e.l[0], y: e.l[1] - hpx, w: wpx, h: hpx };
    lbs.push({ i, ...lb });
    boxes.forEach((b) => { if (hit(lb, b)) issues.push(`label ${i} over ${b.k}`); });
    for (let k = 0; k < e.p.length - 1; k++) {
      const [x1, y1] = e.p[k], [x2, y2] = e.p[k + 1];
      const dx = Math.sign(x2 - x1), dy = Math.sign(y2 - y1);
      const seg = { x: Math.min(x1, x2) + (dx > 0 ? 6 : 0), y: Math.min(y1, y2) + (dy > 0 ? 6 : 0) };
      seg.w = Math.abs(x2 - x1) - (dx ? 12 : 0) || 1;
      seg.h = Math.abs(y2 - y1) - (dy ? 12 : 0) || 1;
      boxes.forEach((b) => { if (hit(seg, b)) issues.push(`edge ${i} crosses ${b.k}`); });
    }
  });

  for (let a = 0; a < lbs.length; a++) {
    for (let b = a + 1; b < lbs.length; b++) {
      if (hit(lbs[a], lbs[b])) issues.push(`label ${lbs[a].i} over label ${lbs[b].i}`);
    }
  }

  let cards = '';
  boxes.forEach((n) => {
    cards += rect(n.x, n.y, n.w, n.h, CREAM, GREEN, 12, 1.8);
    cards += txt(n.x + n.w / 2, n.y + 32, n.k, 18, GREEN, 'bold', 'middle');
    const max = Math.floor((n.w - 24) / 6.05);
    let line = '', lines = [];
    for (const w of n.note.split(' ')) {
      if ((line ? line.length + 1 : 0) + w.length > max) { lines.push(line); line = w; } else line = line ? line + ' ' + w : w;
    }
    if (line) lines.push(line);
    lines.slice(0, 2).forEach((l, i) => { cards += txt(n.x + n.w / 2, n.y + 56 + i * 17, l, 12, MUTED, 'normal', 'middle'); });
  });

  s += lanes + cards;
  s += txt(40, H - 26, 'Cardinality is read from the first entity to the second. Keys and data types are omitted here and appear in Figure 2.', 12.5, MUTED);
  if (issues.length) console.warn('CONCEPTUAL LAYOUT:', issues.join('; '));
  return s + '</svg>';
}

/* ================= FIGURE 3 — architecture ================= */
function buildArchitecture() {
  const W = 2000, H = 1010;
  let s = svgOpen(W, H) + `<rect width="${W}" height="${H}" fill="#FFFFFF"/>`;
  s += rect(W / 2 - 330, 34, 660, 56, GREEN, GREEN, 10, 0) + txt(W / 2, 70, 'Figure 3  Three-tier system architecture', 20, WHITE, 'bold', 'middle');

  const box = (x, y, w, h, title, lines, accent = GREEN) => {
    let o = rect(x, y, w, h, WHITE, accent, 10, 1.7);
    o += rect(x, y, w, 34, accent, accent, 10, 0);
    o += `<path d="M${x} ${y + 26} h${w} v8 h-${w} z" fill="${accent}"/>`;
    o += txt(x + 12, y + 23, title, 15, WHITE, 'bold');
    lines.forEach((l, i) => { o += txt(x + 12, y + 56 + i * 19, l, 12.5, INK); });
    return o;
  };

  s += rect(40, 120, 600, 380, CREAM, '#E0D3B8', 14, 1.2);
  s += txt(60, 150, 'Presentation tier', 17, GREEN, 'bold');
  s += box(60, 168, 560, 96, 'Browser (HTML, CSS, app.js)', ['Server-rendered PHP views, one stylesheet,', 'one script; no inline script or style']);
  s += box(60, 280, 560, 96, 'Views (plain PHP templates)', ['Layout + role pages, escaped with e()', 'Stacked tables and charts under 560 px']);
  s += box(60, 392, 560, 88, 'Public assets', ['public/assets/css/app.css', 'public/assets/js/app.js (versioned)']);

  s += rect(700, 120, 600, 380, CREAM, '#E0D3B8', 14, 1.2);
  s += txt(720, 150, 'Application tier', 17, GREEN, 'bold');
  s += box(720, 168, 560, 78, 'Router and Guard', ['Route table + permission check before controller']);
  s += box(720, 262, 560, 78, 'Controllers', ['Student, Supervisor, Mentor, Coordinator,', 'Management, Admin, DIVA, Messaging']);
  s += box(720, 356, 560, 124, 'Core and services', ['Auth, Csrf, Database (PDO), Audit, Notify,', 'Upload, Settings, Messenger', 'DIVA: rules -> guidebook search -> optional model']);

  s += rect(1360, 120, 600, 380, CREAM, '#E0D3B8', 14, 1.2);
  s += txt(1380, 150, 'Data tier', 17, GREEN, 'bold');
  s += box(1380, 168, 560, 78, 'MariaDB 10.4 (utf8mb4)', ['30 tables, InnoDB, foreign keys and indexes']);
  s += box(1380, 262, 560, 78, 'File storage', ['storage/uploads, avatars, templates, knowledge']);
  s += box(1380, 356, 560, 124, 'Operational output', ['storage/logs: mail.log, php-error.log', 'email_queue processed by bin/send_mail_queue.php', 'weekly reminders by bin/remind_logbooks.php']);

  s += arrow(660, 300, 700, 300, GOLD, 2.4);
  s += arrow(1300, 300, 1360, 300, GOLD, 2.4);
  s += txt(680, 288, 'HTTP', 11.5, MUTED, 'normal', 'middle');
  s += txt(1330, 288, 'PDO', 11.5, MUTED, 'normal', 'middle');

  s += rect(40, 540, 1920, 200, CREAM, '#E0D3B8', 14, 1.2);
  s += txt(60, 570, 'Request lifecycle', 17, GREEN, 'bold');
  const steps = [
    ['index.php'], ['bootstrap', 'paths, autoload'], ['security headers', 'and session'], ['CSRF check', 'on POST'],
    ['Router', 'lookup'], ['Guard', 'permission'], ['Controller'], ['Service', 'call'], ['View + layout'],
  ];
  steps.forEach((st, i) => {
    const x = 60 + i * 208;
    s += rect(x, 590, 190, 54, WHITE, GREEN, 8, 1.4);
    const lines = st.length > 1 ? st : [st[0]];
    lines.forEach((l, k) => {
      s += txt(x + 95, 610 + k * 17 + (lines.length === 1 ? 6 : 0), l, 12.5, INK, 'normal', 'middle');
    });
    if (i < steps.length - 1) s += arrow(x + 190, 617, x + 208, 617, GOLD, 1.8);
  });
  s += txt(60, 676, 'A request that fails a permission check never constructs a controller; a failed CSRF check returns 419 before routing.', 12.5, MUTED);

  s += rect(40, 780, 1920, 190, '#F4F7F5', '#CFDCD5', 14, 1.2);
  s += txt(60, 810, 'Deployment and operations', 17, GREEN, 'bold');
  s += box(60, 828, 580, 118, 'Deployment options', ['Apache with mod_rewrite and .htaccess', 'or PHP built-in server with router.php']);
  s += box(680, 828, 580, 118, 'Offline by design', ['No external API or key; DIVA local model', 'optional on 127.0.0.1:11434, disabled']);
  s += box(1300, 828, 620, 118, 'Backup and audit', ['Append-only audit_log with before/after JSON', 'Seed import only into a disposable database']);
  return s + '</svg>';
}

/* ================= FIGURE 4 — security ================= */
function buildSecurity() {
  const W = 2000, H = 1320;
  let s = svgOpen(W, H) + `<rect width="${W}" height="${H}" fill="#FFFFFF"/>`;
  s += rect(W / 2 - 330, 34, 660, 56, GREEN, GREEN, 10, 0) + txt(W / 2, 70, 'Figure 4  Security design: threat to control', 20, WHITE, 'bold', 'middle');

  const layers = [
    { y: 130, title: 'Perimeter and transport', controls: ['Security headers (CSP, nosniff, DENY framing)', 'Session cookie: HttpOnly, SameSite=Lax, strict mode', 'storage/ and config/ denied over HTTP'] },
    { y: 360, title: 'Identity and session', controls: ['password_hash / password_verify', 'Five failures lock the account for 15 minutes', 'Idle 20 min and absolute 8 h timeouts', 'Forced password change on first sign-in'] },
    { y: 590, title: 'Authorisation', controls: ['Route permission checked by Guard', 'Role gates inside each controller', 'Object-level SQL scoping by student, mentor, supervisor', 'Permission-scoped avatar and download routes'] },
    { y: 820, title: 'Input and data integrity', controls: ['Prepared statements for every query', 'CSRF token on every POST (419 on failure)', 'Upload allow-list + finfo + getimagesize', 'Output escaped with e(); utf8mb4 storage'] },
    { y: 1050, title: 'Accountability', controls: ['Append-only audit_log with before/after JSON', 'mark.compute, publish and moderate audited', 'DIVA answers refuse to change records'] },
  ];
  const threats = [
    'Session hijack, clickjacking, MIME sniffing',
    'Credential stuffing and brute force',
    'Broken access control (IDOR)',
    'SQL injection, XSS, CSRF, malicious uploads',
    'Repudiation of grade changes',
  ];
  layers.forEach((l, i) => {
    s += rect(40, l.y, 420, 180, '#FBEFEF', '#E3C3C3', 12, 1.4);
    s += txt(60, l.y + 34, 'Threat', 14, '#8C2F2F', 'bold');
    const words = threats[i].split(' ');
    let line = '', lines = [];
    for (const w of words) { if ((line + ' ' + w).length > 44) { lines.push(line.trim()); line = w; } else line += ' ' + w; }
    if (line.trim()) lines.push(line.trim());
    lines.forEach((t, k) => { s += txt(60, l.y + 62 + k * 21, t, 13.5, INK); });
    s += arrow(462, l.y + 90, 500, l.y + 90, GOLD, 2.2);

    s += rect(500, l.y, 1460, 180, CREAM, '#E0D3B8', 12, 1.4);
    s += txt(520, l.y + 32, l.title, 15.5, GREEN, 'bold');
    l.controls.forEach((c, k) => {
      const col = k % 2, row = Math.floor(k / 2);
      const x = 520 + col * 710, y = l.y + 56 + row * 40;
      s += rect(x, y, 690, 32, WHITE, '#D9CDB4', 6, 1);
      s += txt(x + 12, y + 22, c, 13, INK);
    });
  });
  s += txt(40, H - 26, 'Every control listed is implemented in the submitted code; the evidence case for each is recorded in docs/test-log.md and docs/VERIFY.md.', 13, MUTED);
  return s + '</svg>';
}

/* ---------------- drawio emitter ---------------- */
const dCell = (id, value, x, y, cw, ch, style) =>
  `<mxCell id="${id}" value="${escAttr(value)}" style="${style}" vertex="1" parent="1"><mxGeometry x="${x}" y="${y}" width="${cw}" height="${ch}" as="geometry"/></mxCell>`;
const dLink = (id, from, to, label) =>
  `<mxCell id="${id}" value="${esc(label)}" style="edgeStyle=orthogonalEdgeStyle;rounded=0;html=1;endArrow=block;strokeColor=#7A93A0;" edge="1" parent="1" source="${from}" target="${to}"><mxGeometry relative="1" as="geometry"/></mxCell>`;
function drawio(cells, edges, w, h) {
  return `<mxfile host="app.diagrams.net" type="device"><diagram id="d1" name="Page-1"><mxGraphModel dx="1200" dy="800" grid="1" gridSize="10" page="1" pageWidth="${w}" pageHeight="${h}"><root><mxCell id="0"/><mxCell id="1" parent="0"/>${cells.join('')}${edges.join('')}</root></mxGraphModel></diagram></mxfile>`;
}

function logicalDrawio() {
  const bands = [
    { title: 'Identity and access control', tables: ['roles', 'permissions', 'role_permissions', 'users', 'access_token'] },
    { title: 'Student, organisation and placement', tables: ['organisation', 'student', 'application', 'placement'] },
    { title: 'Logbook, visits and evidence', tables: ['logbook_entry', 'logbook_comment', 'site_visit', 'document', 'internship_report'] },
    { title: 'Evaluation and final mark', tables: ['criterion', 'evaluation', 'evaluation_score', 'grade_scale', 'mark'] },
    { title: 'Placement communication', tables: ['conversation', 'conversation_member', 'message', 'message_reaction'] },
    { title: 'Platform services', tables: ['notification', 'email_queue', 'audit_log', 'setting', 'feedback', 'diva_chat', 'diva_turn'] },
  ];
  const cells = [], ids = {};
  let y = 140;
  bands.forEach((b) => {
    const hMax = Math.max(...b.tables.map((t) => 40 + tables[t].length * 20));
    b.tables.forEach((t, i) => {
      const x = 60 + i * 340, h = 40 + tables[t].length * 20;
      const label = `<b>${t}</b><br>` + tables[t].map((c) => (c.pk ? 'PK ' : c.fk ? 'FK ' : '') + c.name + '  ' + c.type).join('<br>');
      ids[t] = t;
      cells.push(`<mxCell id="${t}" value="${esc(label)}" style="rounded=1;whiteSpace=wrap;html=1;align=left;verticalAlign=top;spacingLeft=8;fontFamily=Tahoma;fontSize=11;fillColor=#FBF7EE;strokeColor=#0F3B2E;spacingTop=6;" vertex="1" parent="1"><mxGeometry x="${x}" y="${y}" width="300" height="${h}" as="geometry"/></mxCell>`);
    });
    cells.push(`<mxCell id="band${b.title.replace(/\W/g, '')}" value="${esc(b.title)}" style="text;html=1;align=left;verticalAlign=middle;fontStyle=1;fontSize=13;fontFamily=Tahoma;fontColor=#0F3B2E;" vertex="1" parent="1"><mxGeometry x="60" y="${y - 34}" width="500" height="28" as="geometry"/></mxCell>`);
    y += hMax + 120;
  });
  const edges = [];
  const seen = new Set();
  let n = 0;
  for (const [name, cols] of Object.entries(tables)) {
    for (const c of cols) {
      if (!c.fk || !ids[name] || !ids[c.fk]) continue;
      const k = c.fk + '>' + name;
      if (seen.has(k)) continue;
      seen.add(k);
      edges.push(dLink('e' + n++, c.fk, name, ''));
    }
  }
  return drawio(cells, edges, 2500, y + 120);
}

function conceptualDrawio() {
  const { W, H, nodes, edges } = CONCEPT;
  const cells = Object.entries(nodes).map(([id, n]) =>
    `<mxCell id="n_${id}" value="${esc('<b>' + id + '</b><br>' + n.note)}" style="rounded=1;whiteSpace=wrap;html=1;align=center;verticalAlign=top;spacingTop=6;fontFamily=Tahoma;fontSize=12;fillColor=#FBF7EE;strokeColor=#0F3B2E;" vertex="1" parent="1"><mxGeometry x="${n.x}" y="${n.y}" width="${n.w}" height="${n.h}" as="geometry"/></mxCell>`);
  const links = edges.map((e, i) =>
    `<mxCell id="r${i}" value="${esc(e.card + ' ' + e.verb)}" style="edgeStyle=orthogonalEdgeStyle;rounded=0;html=1;endArrow=block;strokeColor=#7A93A0;fontFamily=Tahoma;fontSize=11;" edge="1" parent="1" source="n_${e.a}" target="n_${e.b}"><mxGeometry relative="1" as="geometry"/></mxCell>`);
  return drawio(cells, links, W, H);
}

function boxedDrawio(items, edges) {
  const cells = items.map((it) =>
    `<mxCell id="${it.id}" value="${esc(it.label)}" style="rounded=1;whiteSpace=wrap;html=1;align=left;verticalAlign=top;spacingLeft=8;spacingTop=6;fontFamily=Tahoma;fontSize=11;fillColor=#FBF7EE;strokeColor=#0F3B2E;" vertex="1" parent="1"><mxGeometry x="${it.x}" y="${it.y}" width="${it.w}" height="${it.h}" as="geometry"/></mxCell>`);
  const links = edges.map(([a, b, l], i) => dLink('x' + i, a, b, l));
  return drawio(cells, links, 2100, 1400);
}

/* ---------------- write ---------------- */
const figs = {
  Figure_01_Conceptual_ERD: buildConceptual(),
  Figure_02_Logical_ERD: buildLogical(),
  Figure_03_Architecture: buildArchitecture(),
  Figure_04_Security_Design: buildSecurity(),
};
for (const [name, svg] of Object.entries(figs)) {
  fs.writeFileSync(path.join(OUT, name + '.svg'), svg);
}

const d1 = conceptualDrawio();
const d2 = logicalDrawio();
const d3 = boxedDrawio([
  { id: 'p1', label: '<b>Browser</b><br>HTML, CSS, app.js', x: 60, y: 170, w: 560, h: 90 },
  { id: 'p2', label: '<b>Views</b><br>Plain PHP templates', x: 60, y: 280, w: 560, h: 90 },
  { id: 'p3', label: '<b>Public assets</b><br>app.css, app.js', x: 60, y: 390, w: 560, h: 80 },
  { id: 'a1', label: '<b>Router + Guard</b>', x: 720, y: 170, w: 560, h: 70 },
  { id: 'a2', label: '<b>Controllers</b><br>Student, Supervisor, Mentor, Coordinator, Management, Admin, DIVA, Messaging', x: 720, y: 260, w: 560, h: 100 },
  { id: 'a3', label: '<b>Core and services</b><br>Auth, Csrf, Database, Audit, Notify, Upload, Settings, Messenger, DIVA tiers', x: 720, y: 380, w: 560, h: 110 },
  { id: 'd1', label: '<b>MariaDB 10.4</b><br>30 tables, InnoDB', x: 1380, y: 170, w: 560, h: 80 },
  { id: 'd2', label: '<b>File storage</b><br>uploads, avatars, templates, knowledge', x: 1380, y: 270, w: 560, h: 80 },
  { id: 'd3', label: '<b>Logs and jobs</b><br>mail.log, php-error.log, bin scripts', x: 1380, y: 370, w: 560, h: 80 },
], [['p2', 'a1', 'HTTP'], ['a3', 'd1', 'PDO'], ['a2', 'd2', 'files']]);

const d4 = boxedDrawio([
  { id: 'l1', label: '<b>Perimeter and transport</b><br>Headers, session cookie, denied storage', x: 60, y: 160, w: 1880, h: 90 },
  { id: 'l2', label: '<b>Identity and session</b><br>Hashing, lockout, timeouts, forced reset', x: 60, y: 290, w: 1880, h: 90 },
  { id: 'l3', label: '<b>Authorisation</b><br>Route permission, role gate, object scoping, avatar/download checks', x: 60, y: 420, w: 1880, h: 90 },
  { id: 'l4', label: '<b>Input and data integrity</b><br>Prepared statements, CSRF, upload allow-list, escaping, utf8mb4', x: 60, y: 550, w: 1880, h: 90 },
  { id: 'l5', label: '<b>Accountability</b><br>Append-only audit trail, grade actions, DIVA read-only', x: 60, y: 680, w: 1880, h: 90 },
], []);

const drawios = {
  Figure_01_Conceptual_ERD: d1,
  Figure_02_Logical_ERD: d2,
  Figure_03_Architecture: d3,
  Figure_04_Security_Design: d4,
};
for (const [n, x] of Object.entries(drawios)) fs.writeFileSync(path.join(OUT, n + '.drawio'), x);
console.log('wrote', Object.keys(figs).length, 'svg and', Object.keys(drawios).length, 'drawio');
console.log('tables parsed:', Object.keys(tables).length);
