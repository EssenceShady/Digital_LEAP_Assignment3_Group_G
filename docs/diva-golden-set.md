# DIVA golden set

Sixty questions, ten for each role, fixed as the regression suite for the DIVA
answer contract. `bin/diva-eval.php --golden` reads this table, runs every
question as the first active account of that role inside a read-only
transaction, and writes `docs/diva-eval-results.txt`.

The table is the fixture, so keep the column order. Each row states what the
pipeline should produce:

- **Route** — the routed intent. `any` means the row tests retrieval only.
- **Category** — the `slots.category` the contract should carry.
- **Evidence** — the strongest evidence the row must show:
  - `record` — at least one scoped record or figure, and no refusal;
  - `citation` — at least one cited guidebook page, and no refusal;
  - `quote` — at least one guidebook quotation that must verify word for word;
  - `refusal` — the answer must refuse, not guess;
  - `any` — shape checked, evidence not prescribed.

The questions are grouped by role: students ask mostly about their own
records, staff about queues and the marking model, and every role gets at
least one out-of-scope probe to prove the refusal path still fires.

## Questions

| # | Role | Question | Route | Category | Evidence |
|---|------|----------|-------|----------|----------|
| 1 | student | When is my next logbook entry due? | deadline.logbook | records | record |
| 2 | student | What does Form E mark for the internship report? | weighting | assessment | citation |
| 3 | student | How far through my placement am I? | progress | records | record |
| 4 | student | Can I see my results? | results | records | record |
| 5 | student | Where is my placement? | placement | records | record |
| 6 | student | How do I apply for an internship? | apply | records | any |
| 7 | student | Who chooses the host organisation? | allocation | records | any |
| 8 | student | What are the two internship types? | internship.type | assessment | citation |
| 9 | student | What are the objectives of LEAP? | none | guidebook | quote |
| 10 | student | What is the capital of France? | none | out_of_scope | refusal |
| 11 | supervisor | What is waiting for me to review? | queue | records | record |
| 12 | supervisor | How is the logbook weighted? | weighting.logbook | assessment | citation |
| 13 | supervisor | What should I check before approving a logbook entry? | logbook.status | records | quote |
| 14 | supervisor | What is the report assessment weight? | weighting | assessment | citation |
| 15 | supervisor | What are the report criteria? | weighting | assessment | citation |
| 16 | supervisor | What does Form D cover? | evaluation | assessment | citation |
| 17 | supervisor | How long is the internship? | internship.duration | assessment | citation |
| 18 | supervisor | What is DIVA? | capabilities | self | any |
| 19 | supervisor | What are the assessment weights? | weighting | assessment | citation |
| 20 | supervisor | What are the two internship types? | internship.type | assessment | citation |
| 21 | mentor | What is waiting for me to review? | queue | records | record |
| 22 | mentor | What are the report criteria? | weighting | assessment | citation |
| 23 | mentor | How do I record a site visit? | none | guidebook | quote |
| 24 | mentor | Who chooses the host organisation? | allocation | records | any |
| 25 | mentor | What does Form D cover? | evaluation | assessment | citation |
| 26 | mentor | How is the logbook weighted? | weighting.logbook | assessment | citation |
| 27 | mentor | What is the internship duration? | internship.duration | assessment | citation |
| 28 | mentor | What are the two internship types? | internship.type | assessment | citation |
| 29 | mentor | What is Form E? | weighting | assessment | citation |
| 30 | mentor | What is my next step? | next | self | any |
| 31 | coordinator | How many placements are active? | overview | records | record |
| 32 | coordinator | What is pending for me? | queue | records | record |
| 33 | coordinator | What are the assessment weights? | weighting | assessment | citation |
| 34 | coordinator | Show me the placement overview | overview | records | record |
| 35 | coordinator | Who chooses the host organisation? | allocation | records | any |
| 36 | coordinator | How many marks are published? | overview | records | record |
| 37 | coordinator | What are the two internship types? | internship.type | assessment | citation |
| 38 | coordinator | What does Form E mark? | weighting | assessment | citation |
| 39 | coordinator | What is the logbook weighting? | weighting.logbook | assessment | citation |
| 40 | coordinator | What does the guidebook say about report layout? | none | guidebook | quote |
| 41 | management | How many placements are active? | overview | records | record |
| 42 | management | How many marks are published? | overview | records | record |
| 43 | management | What are the assessment weights? | weighting | assessment | citation |
| 44 | management | What is the internship duration? | internship.duration | assessment | citation |
| 45 | management | What are the two internship types? | internship.type | assessment | citation |
| 46 | management | How many results are published? | overview | records | record |
| 47 | management | What does the guidebook say about the objectives of the internship? | none | guidebook | quote |
| 48 | management | What is DIVA? | capabilities | self | any |
| 49 | management | What is the logbook weighting? | weighting.logbook | assessment | citation |
| 50 | management | Who won the 2018 World Cup? | none | out_of_scope | refusal |
| 51 | admin | How many accounts are there? | accounts | records | record |
| 52 | admin | What can you do? | capabilities | self | any |
| 53 | admin | How many users are there? | accounts | records | record |
| 54 | admin | Where do I find the audit log? | accounts | records | record |
| 55 | admin | How many login attempts failed today? | accounts | records | record |
| 56 | admin | What are the two internship types? | internship.type | assessment | citation |
| 57 | admin | How is the logbook weighted? | weighting.logbook | assessment | citation |
| 58 | admin | What are the assessment weights? | weighting | assessment | citation |
| 59 | admin | What does the guidebook say about the internship report? | none | guidebook | quote |
| 60 | admin | What is the weather in Freetown? | none | out_of_scope | refusal |

## Metrics

The harness reports three accuracies:

- **Route accuracy** — intent and category both match the row.
- **Citation accuracy** — rows that promise `quote` or `citation` carry the
  promised evidence, and every quotation verifies as a substring of its page.
- **Refusal accuracy** — rows marked `refusal` refuse, and rows not marked
  `refusal` do not.

Any failed row is printed with the reason and makes the script exit non-zero.
