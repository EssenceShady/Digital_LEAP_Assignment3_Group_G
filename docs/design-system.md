# Digital LEAP — Design System

The visual identity comes from the internship guide book: charcoal, ivory and institutional gold, Segoe UI for interface text and Georgia for display. This document records the tokens, the component inventory and the rules the code follows. The implementation is one stylesheet, `digital-leap/public/assets/css/app.css`, organised into cascade layers.

## 1. Layers

`app.css` declares its order once, at the top:

```css
@layer tokens, primitives, components, layout, sections, responsive, print;
```

Rules inside a later layer win over a rule in an earlier layer even when the earlier selector is more specific. That is deliberate: the responsive and print layers must be able to correct any component without `!important` and without selector gymnastics. Within one layer the normal cascade applies.

| Layer | Holds |
| --- | --- |
| `tokens` | Custom properties only: colour, spacing, type, radii, shadow, motion, structure. No selectors that paint. |
| `primitives` | Reset, base typography, link and focus behaviour, selection colour, skip link, `.sr-only`, `.lead`, tabular numerals. |
| `components` | Card, table, badge, button, field, alert, chip, tabs, empty state, pagination, stat list, label/value, filter bar, detail grid, documents list, settings group, install note. |
| `layout` | Top bar, sidebar, shell grid, page head, page grids, footer. |
| `sections` | Page composition: auth, landing, apply, profile, settings, tour, printable documents, feedback, charts, tracker, comments, messages, DIVA. |
| `responsive` | The four breakpoints plus the micro-screen exception and coarse-pointer touch targets, and the `data-text-size` / `data-reduce-motion` comfort hooks. |
| `print` | `@page` geometry and document output. |

## 2. Tokens

### Colour

| Token | Value | Use |
| --- | --- | --- |
| `--ink` | `#17181a` | Body text, headings |
| `--ink-soft` | `#3b3b3e` | Secondary prose |
| `--muted` | `#625e55` | Captions, labels, hints — 6.1:1 on ivory |
| `--line` / `--line-strong` | `#e7e1d5` / `#d1c6b2` | Hairlines, input borders |
| `--bg` | `#f6f3ec` | Page (ivory) |
| `--card` | `#ffffff` | Card surface |
| `--inset` | `#faf7f0` | Inset surface for grouped figures |
| `--gold` / `--gold-2` | `#b8912f` / `#d8b75c` | Institutional gold, rules, focus |
| `--gold-ink` | `#7a5a12` | Gold text that must meet contrast |
| `--green` / `--green-bg` | `#145c46` / `#e6f1ec` | Approved, complete |
| `--red` / `--red-bg` | `#9c2626` / `#fbeaea` | Returned, refused |
| `--amber` / `--amber-bg` | `#7f5300` / `#fcf1da` | Pending, needs review |
| `--blue` / `--blue-bg` | `#245a8d` / `#e8f0f8` | Informational |

Three surface levels only: page ivory (`--surface-page`), card white (`--surface-card`), inset tint (`--surface-inset`). A screen never introduces a fourth background.

Status is never colour alone. Every badge carries a word, and the word survives greyscale printing.

### Spacing — one 8-pt scale

`--sp-1` 4 px (half step), `--sp-2` 8, `--sp-3` 12, `--sp-4` 16, `--sp-5` 24, `--sp-6` 32, `--sp-7` 48, `--sp-8` 64. Component padding may use `clamp()` between two adjacent steps; it may not invent a new value.

### Type

Segoe UI Variable Text / Segoe UI for interface, Segoe UI Variable Display / Segoe UI Semibold for display, Georgia for the guide book identity in print and letter headings. No external font is fetched.

| Token | Approximate size | Use |
| --- | --- | --- |
| `--step--1` | 12.5–13.4 px | Captions, table heads, hints |
| `--step-0` | 15.2–16 px | Body, page intro |
| `--step-1` | 17–18.9 px | Card titles, sub-sections |
| `--step-2` | 20.5–24 px | Section headings (rare) |
| `--step-3` | 25.6–32.8 px | Page title |
| `--step-4` | 31.2–44 px | Landing hero only |

Rules:

- One `h1` per screen. Card titles are `--step-1`, visibly below the page title.
- `.lead` or the page-head paragraph introduces the screen in one or two sentences.
- `.num`, table cells, marks, percentages, hours and dates use `font-variant-numeric: tabular-nums`.
- Interface text never goes below 12 px; body text never below 15 px.

### Wrapping

`overflow-wrap` is a layout decision, not a decoration, and the two values are
not interchangeable. `break-word` breaks only when a single word is already
wider than its line. `anywhere` additionally lets that break count in intrinsic
min-content sizing, so a browser may shave every word down to one character per
line before it widens a column — which is what happened to a five-column table
inside a half-width card.

- `body`, `.table td`, `.stat-list .row > strong`, `.label-value dd` and
  `.doc-name` use `break-word`. A normal screen must never break a word.
- `anywhere` is reserved for places holding a genuinely unbreakable token that
  has to fit: the chat body and quoted message copy, chart labels, profile
  chips, e-mail addresses in the demo-role cards and menu, and long route
  fragments.
- A control label is one unit and never wraps: `.table td .btn`,
  `.table td .badge` and `.doc-action` carry `white-space: nowrap`. A table
  whose action column can be squeezed is the wrong component — use `.doc-list`.

### Reader comfort hooks

Two choices are saved per member on **My settings** and painted onto the
`<html>` element by `app/Views/layout.php`, so they apply before first paint and
survive a reload:

| Attribute | Effect |
| --- | --- |
| `data-text-size="normal"` / `"large"` | `large` sets `html { font-size: 18px }`. Every `--step-*` token is in `rem`, so the whole type scale follows; the 8-pt spacing tokens stay in px, so cards, gutters and the shell keep their shape. |
| `data-reduce-motion="on"` | The same suppression that `prefers-reduced-motion: reduce` applies — transitions, animations and smooth scrolling drop to `0.001ms` / `auto` — chosen by the member instead of by the operating system. |

Both preview the instant they are picked, before the form is saved, and are
undone by reloading without saving. They come from `Leap\Core\Preference`, which
reads `user_preference` once per request.

### Shape, depth, motion

Radii: `--radius-sm` 9 px, `--radius` 15 px, `--radius-lg` 20 px. Two shadows only (`--shadow`, `--shadow-lg`). Transitions use `--fast` (160 ms) or `--slow` (260 ms) with `--ease`. Motion is suppressed under `prefers-reduced-motion: reduce` and under `html[data-reduce-motion="on"]`, which is the member's own choice on the settings page.

### Structure

`--tap` 44 px minimum control size, `--shell-max` 1440 px, `--sidebar-w` 264 px, and z-index tokens `--z-topbar` 50, `--z-sidebar` 45, `--z-overlay` 90.

## 3. Breakpoints

| Width | Name | What changes |
| --- | --- | --- |
| 1200 px | desktop | Four-column stat rows become two; DIVA aside narrows. |
| 992 px | tablet | Sidebar becomes a drawer behind the menu button; auth splits to one column; DIVA stacks; three-column grids become two. |
| 768 px | small tablet | Two-column grids and forms become one; tables marked `.table--stack` stack with `data-label` captions; dropdowns become sheets. |
| 560 px | phone | Type drops a step, top bar compresses, filters go full width, rating words hide. |
| 400 px | micro-screen (documented exception) | Brand text hides; chat bubbles and avatars shrink. This is the only width outside the four-step set, and it exists for 320–400 px phones. |

No page may scroll horizontally between 320 and 1600 px. Wide tables scroll inside `.table-wrap`; they never widen the document.

## 4. Component inventory

| Component | Class | Notes |
| --- | --- | --- |
| Card | `.card`, `.card-head`, `.card--inset` | A container query is declared, so children can respond to the card's width. |
| Page head | `.page-head`, `.page-actions` | Title, lead, actions. |
| Lead | `.lead` | Page intro, max 68 characters per line. |
| Button | `.btn`, `.btn-sm`, `.btn-outline`, `.btn-quiet`, `.btn-gold`, `.btn-danger`, `.btn-ghost` | 44 px high; 44 px wide on coarse pointers. |
| Field | `.field`, `.field-label`, `.hint`, `.form-grid` | Label always visible; a placeholder never replaces a label. |
| Table | `.table`, `.table-wrap`, `.table--dense`, `.table--comfortable`, `.table--stack`, `.table--fit`, `.table--scroll` | Dense for grades and audit, comfortable for queues. Cells wrap with `break-word`, and in-table buttons and badges never wrap; a row that still cannot fit is the wrong component — use `.doc-list`. |
| Badge | `.badge`, `.badge-ok`, `.badge-warn`, `.badge-bad`, `.badge-info`, `.badge-muted` | Always carries a word. |
| Chip | `.chip`, `.chip-row`, `.chip-active` | Filters, pagination, follow-up prompts. |
| Alert | `.alert`, `.alert-info`, `.alert-success`, `.alert-warn`, `.alert-danger` | `role="status"`. |
| Empty state | `.empty` | One sentence of explanation and one next action. |
| Stat list / label-value | `.stat-list .row`, `.label-value` | Label and value; the value hangs under the label below 300 px of container width. |
| Detail grid | `.detail-grid`, `.detail-label`, `.detail-full` | Auto-fit at 160 px minimum. |
| Filter bar | `.filter-bar` | Full-width fields below 560 px. |
| Documents list | `.doc-list`, `.doc-row`, `.doc-main`, `.doc-name`, `.doc-meta`, `.doc-action` | One file per row: the name owns its line, size/type/date hang under it in `.doc-meta`, and the action stays whole in `.doc-action`. This replaced a five-column table inside a half-width card. |
| Settings group | `.settings-group`, `.settings-check`, `.settings-device` | `.settings-group` is the `display: grid; gap: 8px` stack inside a settings card (cards here are `.card-head` plus direct children — there is no `.card-body`). `.settings-device` is the This-device panel with its stat list and clear buttons. |
| Install note | `.install-help` with `[data-install-trigger]` / `[data-install-help]` | Hidden until asked for. Each trigger reveals only the note inside its own block, so the account menu and the settings card do not light up together. |
| Sidebar settings row | `.sidebar-settings` | The permanent **My settings** entry at the foot of the sidebar: inline gear SVG, label and a one-line summary, gold outline when active. |
| Timeline / comments | `.timeline`, `.comment`, `.comment.reply` | Threads indent once. |
| Progress | `.progress`, `.tracker-step` | Text state beside every bar. |
| Print document | `.print-body`, `.print-sheet`, `.print-head`, `.print-table`, `.print-sign`, `.print-foot` | A4, 14 mm margin, repeated table heads. |

## 5. Do and don't

**Do**

- Use a token. If a value is missing, add it to `tokens` and document it here.
- Put a word beside a colour. `badge-ok` says "Approved".
- Let long values wrap under their label rather than forcing a full-width right-aligned line.
- Use `.table--dense` for figures and `.table--stack` only where the row reads better as a card.
- Give every screen one lead element: a lead paragraph, a KPI row, or a primary action.
- Keep display type to the page title, letter titles and the landing hero. Georgia appears in documents, not in dashboards.
- Break a long value, never a word: `break-word` on prose, cells and stat values, `anywhere` only where an unbreakable token has to fit, `nowrap` on every control label.
- Honour the two comfort hooks. `layout.php` paints `data-text-size` and `data-reduce-motion` once, from `Preference`; no component reads a preference of its own.
- Give anything a member may want later a permanent entry point. Install, the guided tour and settings each have a standing control, not only a first-visit prompt.

**Don't**

- Don't add a new breakpoint. Add a container query or use the 400 px exception with a note here.
- Don't set a fixed `min-width` on a table unless it is `.table--scroll` or the log book day table, both of which scroll inside `.table-wrap`.
- Don't use `overflow-wrap: anywhere` on a box that takes part in table or grid min-content sizing — that is how "Download" ends up as four lines of two letters.
- Don't resize the layout with the text preference: type is in `rem`, spacing stays in px, so a member who picks Large gets bigger words in the same familiar grid.
- Don't use inline `style=` attributes; the Content-Security-Policy blocks them.
- Don't fetch fonts, icons or images from a CDN. Icons are inline SVG; the only raster assets are the university logo and the DIVA mark.
- Don't colour-only a state, hide a label behind a placeholder, or remove a focus ring.
- Don't add a fourth surface colour or a third shadow.

## 6. DIVA answer states

DIVA's answer is one contract, so its chrome follows one set of rules. The meta line names the engine in words — **Rules & records**, **Guidebook search** or **Local model** — then either "saved to your account" (online) or "on this device" (offline). An offline answer replayed from a saved copy adds "· saved answer". A low-confidence answer carries "Match confidence: low" as text, never colour alone. Quotes sit behind a `<details>` labelled "N guidebook sources"; each source links to its page and shows the snippet the verifier checked. The next action renders as one outline button. The offline note and the "I stopped short of answering:" refusal use body type and a text label, so a refusal reads as a changed answer rather than an error state.

## 7. Verification

`docs/test-log.md` records the design pass: the route sweep at 360, 768, 1024 and 1440 px, the absence of horizontal page scroll, the contrast of muted text, and the touch-target sizes. The layer refactor itself was checked by loading every page and confirming the computed styles resolve (the stylesheet is still a single HTTP 200 with `?v=` cache-busting).

Wrapping is measured, not eyeballed. A `TreeWalker` collects every text node under `#main` and each word of five characters or more is measured with `Range.getClientRects()` on the word's own range: more than one rectangle means the word was split mid-word. The same probe reports clipped cells by comparing `scrollWidth` with `clientWidth`. Because the automation browser surface could not be resized for the 20 November pass, the desktop shape was measured by forcing the Documents card to 760 px inside the narrow viewport (TC-194 to TC-196). The comfort hooks are checked from computed style: the root font-size moves 16 px → 18 px with **Large**, and `.card`'s `transition-duration` falls to `1e-06s` with **Reduce motion** (TC-205).
