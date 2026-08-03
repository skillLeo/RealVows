# Deploying RealVows

## ⚠️ Authorship — one name on every commit

This repository is authored by **skillLeo**. No co-author trailer, no
"generated with" line, no second name of any kind appears on a commit.

GitHub renders a `Co-Authored-By` trailer as "skillLeo and <name>" against
every commit in the list, and this repository is shown to the client. The work
is delivered under one name, and that is how the client understands who they
are paying.

`.githooks/commit-msg` enforces it and rejects the commit outright. Enable it
in a fresh clone with:

```bash
git config core.hooksPath .githooks
```


Target: **Hostinger shared hosting** · `realvows.skillleo.com`

---

## The server, as it actually is

Verified on the box rather than assumed:

| | |
|---|---|
| Default CLI PHP | **8.2.31** — too old, do not use |
| PHP 8.3 | `/opt/alt/php83/usr/bin/php` → 8.3.31 — also too old |
| PHP 8.4 | `/opt/alt/php84/usr/bin/php` → **8.4.21 ← use this** |
| Image extensions | **GD and Imagick both present** ✅ |
| Composer | 2.9.8 |
| Git | 2.52.0 |
| Node | **not installed** |
| SSH | port `65002` |

Two consequences that shape everything below:

**The default `php` is 8.2 but this application requires 8.4.** Laravel 13
pulls in Symfony 8, which needs PHP ≥ 8.4.1 — so 8.2 and 8.3 both fail at
`composer install` with a platform error. Every CLI
command — migrations, caches, the queue cron — must call
`/opt/alt/php84/usr/bin/php` explicitly. The *web* PHP version is separate and
is set per-domain in hPanel; it must also be 8.4 or the site will not boot.

**There is no Node on the server.** Assets are therefore built in CI and the
compiled output is shipped. Never try to run `npm` on the box.

---

## Directory layout

Laravel's `public/` is the only directory that may be web-reachable. Anything
else exposed would serve `.env` to the internet.

```
~/domains/realvows.skillleo.com/
└── public_html/          ← web root AND the application
    ├── .htaccess         ← the only thing protecting everything below
    ├── .env              ← db password + app key
    ├── app/ config/ storage/ vendor/ …
    └── public/           ← what .htaccess rewrites every request into
```

> **Why the whole application sits inside the web root, and what that costs.**
>
> Laravel's normal layout keeps the framework one directory *above* the served
> folder, so `.env` and `storage/` are physically unreachable. That layout was
> rejected for this project, so everything lives in `public_html` instead —
> which means the database password, the app key and every member photograph
> are inside the directory Apache serves.
>
> `public_html/.htaccess` is therefore load-bearing. It denies the sensitive
> paths twice, through two different Apache modules, so that disabling one
> does not open the doors. Verified after deploy: `.env`, `.git/config`,
> `storage/logs/`, `vendor/`, `app/`, `config/` and `composer.json` all
> return **403**.
>
> **If that file is ever deleted or overwritten, `.env` becomes downloadable
> by anyone.** Check it after any change to the web root, and re-run the
> verification block at the end of this document.
>
> ### ⚠️ THIS HAPPENED. 2 August 2026.
>
> The file was hand-written on the server during first-time setup and never
> committed. The deploy runs `rsync -az --delete`, which removes anything on
> the server that the repository does not have — so the first deploy after
> that gap removed it. The rewrite into `public/` went with it and the site
> returned **403 for every request**. `composer.json` was publicly readable
> and application PHP was being executed directly by the web server. `.env`
> survived only because LiteSpeed blocks dotfiles by default, which is luck,
> not design.
>
> **The fix was to commit it.** `.htaccess` is now tracked at the repository
> root, so `--delete` can only ever restore it. Do **not** add it to
> `.gitignore` and do **not** add it to the rsync excludes — an excluded file
> drifts silently, which is the situation that caused this.
>
> Sensitive paths now answer **404** rather than 403, because the rewrite
> reaches them before the `FilesMatch` rules do. That is a better answer: it
> does not confirm the file exists.

---

## First-time setup

### 1. Database

Create it in hPanel → Databases. Note the name, user and password: on
Hostinger these are prefixed, e.g. `u290685119_realvows`.

### 2. PHP version for the web

hPanel → Advanced → PHP Configuration → select **8.4**. Without this the site
returns a composer platform error on the first request.

### 3. The `.env`

Never in git. Create it once on the server:

```
APP_NAME=RealVows
APP_ENV=production
APP_DEBUG=false
APP_URL=https://realvows.skillleo.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=u290685119_realvows
DB_USERNAME=u290685119_realvows
DB_PASSWORD=...

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
FILESYSTEM_DISK=local

# MUST stay false in production. It is an authentication bypass.
REALVOWS_DEMO_LOGINS=false
```

Then `php artisan key:generate`.

> ⚠️ `APP_ENV=production` is doing real work. The one-click demo login route
> requires `local`, so on this server it is not registered at all. Setting
> `APP_ENV=local` here would hand every account away.

### 4. The queue cron — required, not optional

Shared hosting has no Supervisor and no long-running worker. A one-minute cron
drains the queue and exits. Without it, **no email ever sends.**

hPanel → Advanced → Cron Jobs, every minute:

```
cd ~/domains/realvows.skillleo.com/app && /opt/alt/php84/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

> Some cheap Hostinger tiers restrict cron to every 15 minutes. That is
> tolerable for email but slow for anything a member is waiting on. Confirm
> the plan supports one-minute cron.

### 5. Seed images — the repository is NOT sufficient on its own

⚠️ **A clean checkout plus these steps must produce a working environment.
Without this step it does not.**

`storage/app/seed/` holds the 70 demonstration photographs and is gitignored
(6.9 MB of licensed stock, and `storage/` is excluded wholesale). `DemoSeeder`
guards each file with `is_file()`, so on a fresh server it silently produced
thirty members with zero photographs and reported success.

That guard is now loud and the seeder aborts — but the sync is still a step
somebody has to run:

```bash
rsync -az -e "ssh -p 65002 -i ~/.ssh/skillleo_deploy" \
  storage/app/seed/ \
  u290685119@46.202.183.38:domains/realvows.skillleo.com/public_html/storage/app/seed/
```

Verify before seeding — 30 portraits and 40 scenes:

```bash
ls storage/app/seed/portraits | wc -l   # 30
ls storage/app/seed/scenes | wc -l      # 40
```

### 6. First administrator

```bash
cd ~/domains/realvows.skillleo.com/app
/opt/alt/php84/usr/bin/php artisan realvows:create-admin \
  --name="Umar" --email="umar@realvows.com" --role=super_admin
```

Created from the console only — there is deliberately no public route that can
mint an administrator.

---

## GitHub Actions

### Required secrets

Settings → Secrets and variables → Actions:

| Secret | Value |
|---|---|
| `SSH_HOST` | `46.202.183.38` |
| `SSH_PORT` | `65002` |
| `SSH_USER` | `u290685119` |
| `SSH_PRIVATE_KEY` | the private half of the key below |
| `DEPLOY_PATH` | `~/domains/realvows.skillleo.com/app` |

### Use a key, not the password

```bash
ssh-keygen -t ed25519 -C "github-actions-realvows" -f ~/.ssh/realvows_deploy -N ""
ssh-copy-id -i ~/.ssh/realvows_deploy.pub -p 65002 u290685119@46.202.183.38
```

Paste `~/.ssh/realvows_deploy` (the private one) into `SSH_PRIVATE_KEY`.

> A password typed into a chat window, a ticket or a terminal history should
> be considered public. Rotate the account password in hPanel once key auth
> works.

### Running a deploy

Actions → **Deploy** → Run workflow → type `DEPLOY`.

Manual on purpose. The client signs off each phase on a staging build before
it goes live, so a commit landing on `main` is not the same event as a
release.

The workflow puts the site into maintenance, rsyncs (never touching `.env`,
`storage/` or `_design/`), migrates, re-caches config and routes, brings it
back up, and fails loudly if the home page does not answer 200.

### CI on every push

`ci.yml` runs three jobs, and all three can fail a build:

1. **Tests** — the full suite on PHP 8.4 with GD, matching production
2. **Content rules** — the contractual ones. No astrology language anywhere in
   `resources/`, four photos never six, and no UI copy promising "instantly"
   when the architecture batches work by cron. These are enforced by the
   pipeline so they survive a well-meaning change months from now.
3. **Build & budget** — fails if home JS exceeds 30 KB gzipped or app JS
   exceeds 150 KB

---

## Routine commands

Always with the 8.4 binary:

```bash
cd ~/domains/realvows.skillleo.com/public_html
P=/opt/alt/php84/usr/bin/php

$P artisan migrate --force
$P artisan config:cache && $P artisan route:cache && $P artisan view:cache
$P artisan queue:failed          # anything that could not send
$P artisan down --retry=30       # maintenance
$P artisan up
```

After changing anything in `.env`, run `config:cache` again — a cached config
does not re-read the file.

---

## If something breaks

| Symptom | Cause |
|---|---|
| Composer platform error on first load | Web PHP is not 8.4 (hPanel) |
| 500 with a blank page | `storage/` not writable, or `APP_KEY` missing |
| No email ever arrives | The cron is missing, or mail settings are unset in `/admin/settings` |
| Photos 404 | `storage/app` permissions — they are served through PHP, never as static files |
| Settings changes have no effect | `config:cache` after editing `.env` |

---

## Restoring a backup

⚠️ **Strip the first line before restoring with anything but a modern client.**

MariaDB 11.8's `mysqldump` — which is what the live server runs — prepends:

```
/*M!999999\- enable the sandbox mode */
```

Clients older than 10.11 cannot parse that directive and fail at line 1 with
`Unknown command '\-'`. The dump itself is fine; only the restore path is
affected, and it fails in exactly the situation where somebody is under
pressure and reaching for whatever `mysql` is on their laptop.

```bash
# Take one
mysqldump -h127.0.0.1 -u USER -pPASS DBNAME > backup.sql

# Restore anywhere, including older clients
tail -n +2 backup.sql | mysql -h127.0.0.1 -u USER -pPASS TARGET_DB
```

Verified on 2026-08-01: the 172 KB backup restores cleanly with the first
line stripped, and contains the full pre-migration state.

**A backup nobody has restored is a hope, not a backup.** Restore into a
scratch database after any change to the backup routine.

---

## Standing notes from things that went wrong

### Closing the chrome is not closing the pages

A reviewer was shown a Settings tab that 404'd. The nav was fixed, the shared
`is_admin` prop was deleted, and the fix was declared complete.

It was not. `Account.vue` carried its own Administration card gated on a
**different** flag (`account.isAdmin`, sent by its controller) with a
hardcoded link. Deleting the shared prop never touched it.

**The lesson: chrome and pages are two separate surfaces, and fixing one says
nothing about the other.** A defect class is only closed when the invariant is
enforced somewhere both surfaces have to pass through — here, the server-built
`nav` array plus `NavigationCrawlTest`, which crawls every link a page and its
child components can render and opens each one as each role.

### Every negative assertion needs a positive control

A guard searched a payload for `/admin/settings` and passed. It passed because
`json_encode` escapes slashes to `\/admin\/settings`, so the search could
never have found it — present or absent.

**A negative assertion whose search method cannot find the thing is invisible
and it accumulates.** From here: assert the thing is absent, then assert in the
same test that the same method finds it when it IS present. See
`ForbiddenLanguageTest::test_the_cleanliness_check_actually_catches_forbidden_terms`
and `TeaserPhotoLeakTest::test_the_search_method_can_actually_find_a_photo_path`.

### CSS fails silently — assume it and guard it

CSS has no collision warning, no unused-symbol warning and no error state.
Every one of these was accepted happily by the browser while doing the wrong
thing, and all of them shipped:

- **A duplicate `@keyframes` name.** `rv-photo-settle` already animated the
  hero zoom; a second definition for the hero arrival silently replaced it.
  The later definition simply wins.
- **A scale on a full-width element.** The arrival scaled `.rv-hero__art`,
  a full-width grid track, so 375px became 390px and the page scrolled
  sideways by 8px.
- **A scaled pseudo-element in an unclipped parent.** `scale(1.03)` behind
  the auth band pushed every public page 1.5% wider than the viewport.
- **A crop that cuts faces.** Two photographs of different proportions in
  short wide frames, cropped by `object-fit: cover` with the default centre,
  which has no idea where the people are. Nothing overflowed, nothing
  errored — it was simply wrong at a size a real device shows.

`StylesheetIntegrityTest`, `HorizontalOverflowTest` and
`CouplesPhotographyTest` enforce what can be enforced. **The last one on that
list cannot be automated at all** — a crop that touches a face is only
visible to eyes, at real widths, on the actual photograph.

⚠️ **Headless Chrome clamps its viewport to 500px minimum.** A screenshot at
`--window-size=375` is 375 physical pixels of a 500px layout and lies
convincingly — it showed clipped text on every section and nearly sent me
fixing a layout that was fine. **An iframe has no such floor:** an iframe
375px wide IS a 375px viewport, media queries and all. Measure and screenshot
through an iframe, never through the window.

### An assertion that cannot fail is not a test

The same root failure has now shipped **four times in four costumes**, and
every one of them was invisible because the suite was green:

1. `json_encode` escapes slashes, so searching a payload for
   `/admin/settings` found nothing whether or not it was there.
2. Callback tests **built** the signed URL themselves, so the URL the gateway
   actually produced was never exercised — checkout was dead on the live site.
3. `MatchPresenter` emitted photo URLs the photo route refused, and no test
   followed an emitted URL — members paid and got four broken images.
4. `Mail::assertNothingSent()` on a `ShouldQueue` mailable passes **even when
   an email has gone out**, because queued mail is never "sent" in a test.

In each case the passing condition did not depend on the thing being claimed.

**The rule: any assertion about a queued or deferred side effect must either
run the queue synchronously in the test, or assert on the queue itself —
never on the channel the work has not reached yet.** And every negative
assertion gets a positive control proving the search method works.

`AssertionsCanFailTest` enforces the mechanical half: it fails the build if
any test asserts a queued mailable was *sent*, or uses `assertNothingSent()`
without pairing it with `assertNothingQueued()`. It has its own positive
control, since it would otherwise be exactly the kind of test it bans.

### Test the URL the code builds, not the route it targets

Checkout was dead on the live site while the suite was green. Every callback
test posted to the signed route *directly*; none followed the URL the gateway
actually constructed. The gateway was appending `?SpiToken=` to a signed URL,
and the signature covers the whole query string, so the route answered 403.

**If code builds a URL, a test must follow that URL.**

### Seed assets are not in git

`storage/app/seed/` is gitignored, and `DemoSeeder` guards its photo step with
`is_file()` — so on a fresh server it silently produced 30 members with zero
photographs. Sync it explicitly:

```bash
rsync -az -e "ssh -p 65002 -i ~/.ssh/skillleo_deploy" \
  storage/app/seed/ USER@HOST:domains/realvows.skillleo.com/public_html/storage/app/seed/
```

---

## Verify the web root is safe

Run this after any deploy or any change to `public_html`. Every line must show
`403` or `404`. A `200` means that file is downloadable by anyone on the
internet.

```bash
for p in /.env /.env.example /storage/logs/laravel.log /composer.json \
         /artisan /.git/config /vendor/autoload.php /app/Models/User.php \
         /config/database.php; do
  printf '%-34s %s\n' "$p" \
    "$(curl -s -o /dev/null -w '%{http_code}' https://realvows.skillleo.com$p)"
done
```
