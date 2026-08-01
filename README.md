# RealVows

A premium, marriage-focused matchmaking platform. Not casual dating: every
member is here to find the person they are going to marry.

Built by **SkillLeo SMC Pvt Ltd** for RealVows under agreement SKL-2026-RV-001.

---

## How it works for a member

1. Signs up **free** — building a full profile never costs anything
2. Completes a **seven-step guided profile** that saves as they type
3. Uploads **exactly four photos** plus one verification photo of themselves
   holding a handwritten code
4. A **real person** reviews and approves or rejects that verification
5. Once verified, the engine finds the people they are most compatible with
6. Those matches appear **locked** — score visible, faces hidden *(Phase 2)*
7. A **one-time package** unlocks them *(Phase 2)*
8. Matches arrive by **email and WhatsApp** with a secure link *(Phase 3)*

**There is no browsing.** Members never search, filter or scroll a directory.
The engine chooses and delivers. No search, no filters, no member grid.

**No subscriptions.** Every payment is one-time: US$20 / 1 match ·
US$50 / 3 · US$100 / 8.

---

## Delivery

| Phase | Scope | Status |
|---|---|---|
| **1 — Foundation & Profiles** | Auth, seven-step builder, photo pipeline, manual verification, settings, admin | ✅ **Complete** |
| **2 — Engine & Money** | Matching engine, locked teaser matches, PowerTranz (sandbox), credit ledger | Not started |
| **3 — Delivery & Launch** | Email + WhatsApp delivery, match inbox, hardening, handover | Not started |

Phase 2 begins only on written sign-off of the Phase 1 staging build.

---

## Stack

Laravel 13 · Inertia · Vue 3 · MySQL · Vite

Deployed to **Hostinger shared hosting**, which shapes the architecture: no
Redis, no Supervisor, no persistent processes. Queues, cache and sessions all
run on the database driver, and a one-minute cron drains the queue. Background
work is therefore **batched, never instant** — UI copy says "shortly" or
"usually within a day", never "instantly".

**Rendering is deliberately split.** Public pages (home, legal) are plain
Blade with almost no JavaScript, so they are fast on a mid-range phone and
crawlable. The authenticated app is Inertia + Vue, where reactivity earns its
weight.

### Performance budget — enforced in CI

| | Budget | Actual |
|---|---|---|
| Home page JS | 30 KB gzipped | **0.6 KB** |
| App JS | 150 KB gzipped | **~93 KB** |

---

## Running it locally

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate

# create the database, then:
php artisan migrate --seed

npm run build
php artisan serve
```

For demonstration data — 30 members, real photos, matches, and one-click
sign-in buttons — see **[DEMO.md](DEMO.md)**.

---

## Deployment

Manual, from the Actions tab: **Deploy → Run workflow → type `DEPLOY`**.
Deliberately not automatic on push, because a commit landing on `main` is not
the same event as a release — the client signs off each phase first.

Full server setup, the required GitHub secrets and the cron entry are in
**[DEPLOY.md](DEPLOY.md)**.

---

## The rules this codebase enforces

Some of these are contractual. CI fails the build if any is broken, so they
survive changes made months from now by someone who was not in the room.

- **The matching mechanism is never named to a member.** It is the client's
  commercial secret and a deliberate market decision. It exists server-side
  only — see `app/Services/Sign/SignCalculator.php`. Members see a
  compatibility score and a rationale written from their own answers.
- **Exactly four photos**, in fixed named slots, plus one separate
  verification photo that is never shown on a profile or to another member.
- **Verification gates everything.** An unverified profile can never be
  matched, matched to, or shown. Enforced at query level, not in the UI.
- **Photos are private.** Stored off the public path, served only through a
  short-lived signed URL behind both an auth check and an ownership check.
  EXIF, including GPS, is stripped on upload.
- **Credit balances are derived**, never stored. Summing an append-only ledger
  with a unique constraint on the gateway transaction id makes a
  double-credit structurally impossible rather than merely unlikely.
- **The matching weights (40/20/15/10/10/5) are constants in code**, not
  settings — they are contractually fixed.
- **Every third-party credential lives in the database**, editable in the
  browser at `/admin/settings`, because the client will never edit a file on
  a server. Secrets are encrypted at rest and only ever leave masked.

---

## Testing

```bash
./vendor/bin/phpunit
```

46 feature tests, 207 assertions. They cover the things that would be silent
if they broke: a member reaching `/admin` (must be 404, never 403), one member
reading another's photo, a masked secret surviving a settings save, the
server-side age check, and a profile draft surviving a refresh.
