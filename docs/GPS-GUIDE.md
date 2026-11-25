# GPS check-ins: how presence is recorded and what proves it

This guide covers the three places a location is used: a student checking in
and out of their placement, a mentor checking in for a site visit, and a
coordinator checking in on any placement. It is written for the people who
will demonstrate and mark the system, so it also says plainly what the
evidence can and cannot show.

## The one-minute summary

1. The coordinator puts a **site pin** (latitude and longitude) on each
   organisation once.
2. A student presses **Check in** or **Check out** on *My placement*. The
   browser reads the phone's location **only at that moment** and sends it
   with the press.
3. A mentor or coordinator presses **Check in at site** while standing on
   site. This is refused if they are more than **500 m** from the pin.
4. The server writes one **audit event** per press: who, which placement,
   what the device reported (six-decimal coordinates and accuracy in metres,
   or the word "attested" when it reported nothing), the distance from the
   pin, the IP address, the browser and the server's own timestamp. Nothing
   can edit that row afterwards.
5. That event is what you show as proof: the student's *Attendance* card,
   the mentor or supervisor's *Attendance check-ins* panel on a logbook
   entry, and the coordinator's *Placement* page under **Attendance** and
   **Staff site visits**.

There is **no background tracking**. The location is read when a button is
pressed, sent with that request, and never polled again.

## Where the site pin comes from

| Step | Screen | What to do |
| --- | --- | --- |
| 1 | Coordinator → **Organisations** | Open the host organisation (for example *Bright Future Mining Ltd*) and choose **Edit**. |
| 2 | **Site pin latitude** / **Site pin longitude** | Enter the site's coordinates, for example `8.484000` and `-13.234000`. Both fields must be filled to save a pin; leaving both blank keeps the old one. |
| 3 | **Remove site pin** | Tick this and save to delete the pin. Distances then show *No distance* until a new pin is recorded. |

Every placement at that organisation inherits the pin. The seeded database
ships **without** pins, so distances read "No distance" until the coordinator
sets one. Set the pin before you demonstrate, or the 500 m check has nothing
to measure against.

## Student check-in and check-out

**Where:** Student → **My placement** → **Attendance** card.

What the card shows before anything is pressed:

- **Today** — "No check-in recorded yet", or the time and distance of the
  last event, or "Attendance opens when the placement is active."
- **Site pin** — "Set" or "Not set by the coordinator".
- Two buttons. **Check in** is enabled when there is no open check-in;
  **Check out** is enabled only after a check-in. The server enforces the
  same order, so a double press or a stale page cannot create two
  check-ins in a row.

What happens when a button is pressed:

1. The page says "Reading your location…". The browser asks for permission
   the first time. The request uses `enableHighAccuracy: true`, a 10-second
   timeout and accepts a reading up to 60 seconds old.
2. The read coordinates (six decimals) and the reported accuracy (whole
   metres) are placed in hidden fields and the form is submitted.
3. The server writes `attendance.check_in` or `attendance.check_out` against
   the placement, then reloads the card:
   - "Checked in at 09:14." or "Checked out at 17:02."
   - "· 212 m from site pin" when a pin exists.
   - "· location not shared" when the browser gave no reading and the event
     was recorded as attested.
   - Beyond 500 m the check-in is **still recorded**, with "You are 1.4 km
     from the site pin — your supervisor may ask about this." Student
     attendance is a claim to be reviewed, not a gate.
4. If the browser cannot or will not share a location, the check-in is
   recorded as **attested** — no coordinates, `source: "attested"` — and the
   supervisor's panel says so. This is deliberate: a student on a phone
   without location service can still be marked present, but nobody can
   mistake it for a verified reading.

Check-ins need a connection. Offline, the page says "You are offline.
Reconnect to record attendance." and sends nothing; the offline shell is for
logbook drafts, not for writes that must be timestamped by the server.

## Staff site visits (mentor and coordinator)

**Mentor:** **Site visits** → the scheduled **physical** visit → **Check in at
site**. The visit cannot be completed until this check-in exists; the
**Complete visit** button stays disabled with the note "Check in at the site
before completing a physical visit."

A check-in is tied to one visit and one staff member:

- A second check-in for the same visit by the same person is refused:
  "You have already checked in for this visit."
- The completion step re-checks the distance. A physical visit more than
  500 m from the pin cannot be completed.
- Virtual and phone visits have no geo requirement, but their mode is
  recorded on the visit so nobody reads them as physical.

**Coordinator:** **Placements** → open a placement → **Staff site visits** →
**Check in at this site**, with an optional purpose such as "Routine
monitoring visit".

Staff check-ins are **strict**. If location is unavailable or refused, the
visit is not recorded at all: "Location was not shared, so the visit was not
recorded. Allow location and try again." There is no attested fallback here,
because a site visit is the evidence that a person was physically present.

## What the record contains

Each press is one row in `audit_log`. For a coordinator check-in the stored
`after_json` looks like this:

```json
{
  "type": "site_visit",
  "visit_id": null,
  "role": "coordinator",
  "purpose": "Routine monitoring visit",
  "lat": 8.484123,
  "lng": -13.234567,
  "accuracy_m": 12,
  "distance_m": 84,
  "org_lat": 8.484,
  "org_lng": -13.234,
  "source": "device"
}
```

| Field | Meaning |
| --- | --- |
| `type` | `site_visit`, `check_in` or `check_out` |
| `visit_id` | The scheduled visit this proves, or `null` for a student check-in or an unscheduled staff visit |
| `role` | `mentor` or `coordinator`; student check-ins carry the user instead |
| `lat`, `lng` | What the device reported, rounded to six decimals (about 0.1 m) |
| `accuracy_m` | The device's own confidence radius in metres. Small is better; anything over 10,000 m is discarded as nonsense |
| `distance_m` | Great-circle distance from the pin, computed on the server (haversine) |
| `org_lat`, `org_lng` | The pin in force at the moment of the check-in, so a later pin change cannot rewrite history |
| `source` | `device` or `attested` |

Alongside the JSON the row stores `user_id`, `entity_type = placement`,
`entity_id`, `ip_address`, `user_agent` and `created_at`. The time is the
**server's** clock in the configured timezone (`Africa/Freetown`), not the
phone's, so changing the phone's clock does not move a check-in.

The audit table has no edit or delete route. The application can add events
and read them; nothing in the interface can alter or remove one. That is the
immutability the word "audit" is doing here.

## What a marker or supervisor sees

| Role | Screen | Evidence shown |
| --- | --- | --- |
| Student | My placement → Attendance | Their own events, the time, the distance and whether the location was shared |
| Mentor | Logbook entry → **Attendance check-ins** | The student's events for that placement, with pin and distance |
| Supervisor | Logbook entry → **Attendance check-ins** | The same panel, read-only |
| Mentor | Site visits | "Checked in at the site on 25 Nov 2026, 10:41 (68 m from the pin)" |
| Coordinator | Placement → **Attendance** | Every student event with name, time, distance and source |
| Coordinator | Placement → **Staff site visits** | Every staff check-in, with the purpose and who recorded it |
| Administrator | Audit log | The raw rows, including the fields above |

## What this proves — and what it does not

It proves that an authenticated account pressed a particular button, from a
particular browser and IP address, at a particular server time, and that the
device that account was using claimed to be at these coordinates, this many
metres from the pin, with this stated accuracy.

It does **not** prove the device itself was physically at that spot. Browser
location can be spoofed with developer tools, a mock-location app or a rooted
phone. The accuracy figure is the device's own claim. The IP address and
browser string are corroboration, not proof. The system is honest about this
by recording the claim, the accuracy and the distance rather than presenting
a green "verified" badge.

For coursework and for a university's internal supervision this is the right
trade-off: the record is complete, timestamped and immutable, and a distance
that looks wrong is visible to the coordinator instead of being hidden. It is
not a forensic attendance system, and it does not pretend to be.

## Demonstrating it

**On the laptop (simplest).** Chrome treats `http://localhost` as secure, so
location works. Open the coordinator account, set a pin of
`8.484000, -13.234000` on an organisation, then use
**⋮ → More tools → Developer tools → ⋯ → More tools → Sensors → Location →
Custom location** and enter the same pair. Sign in as the student in another
tab, open **My placement**, press **Check in** — the record shows a distance
of a few metres.

**Proving the refusal.** Change the sensor to `8.484000, -13.300000` (about
7 km away), press **Check out** as the student: it is recorded and flagged.
As the mentor, **Check in at site** is refused with the distance and the
500 m rule.

**On a phone.** Geolocation needs a secure context. `http://localhost` on the
host machine is secure; the same app opened over the LAN at
`http://192.168.x.x` is **not**, and Chrome will refuse the permission. If
the demonstration must be on a phone, serve it over HTTPS (for example the
Codespaces/hosted path) or add the LAN origin to
`chrome://flags/#unsafely-treat-insecure-origin-as-secure` on that phone for
the session. Otherwise use the laptop with a sensor; the screens and the
evidence are identical.

**Resetting between demonstrations.** Check-ins are audit rows, so the clean
way to start over is to re-import `database/digital_leap.sql`, which rebuilds
every table and the demo data.

## If something does not work

| Symptom | Cause | Fix |
| --- | --- | --- |
| "location not shared" on a student check-in | Permission denied, no GPS, or an insecure origin | Allow location for the site; use `localhost` or HTTPS; the event is still recorded as attested |
| Staff check-in refused though you are on site | No pin on the organisation, or the pin is wrong | Set or correct the pin as the coordinator; 500 m is the limit |
| "No distance" everywhere | The organisation has no pin | Set one; distances are computed at check-in time and are not retroactive |
| Location never arrives on a phone | Plain HTTP over the LAN is not a secure context | Use HTTPS, USB port forwarding to `localhost`, or the laptop with a sensor |
| The card says attendance opens when the placement is active | Placement status is not active | Activate the placement as the coordinator |
