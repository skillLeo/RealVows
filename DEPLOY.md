# Deploying RealVows

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
├── DO_NOT_UPLOAD_HERE   ← Hostinger's empty marker. See the note below.
├── app/                 ← the application. NOT web-reachable.
│   ├── .env             ← lives only here, never in git
│   ├── storage/         ← member photos. NEVER overwritten by a deploy.
│   └── ...
└── public_html/         ← the web root; contents of Laravel's public/
    └── index.php        ← points back into ../app
```

> **About `DO_NOT_UPLOAD_HERE`.** Hostinger drops this empty, zero-byte file
> into every domain folder. It means "your *website* files go in
> `public_html`, not at this level" — guidance aimed at someone about to
> upload `index.html` one directory too high. It enforces nothing.
>
> For a Laravel application the app deliberately sits at this level, because
> only `public_html` is served over the web. If the framework lived inside
> `public_html`, then `.env` — the database password, the app key — and
> `storage/` — every member's photographs — would be fetchable by anyone who
> guessed the URL. Keeping the app one level up is what makes that
> impossible, and it is the standard layout for Laravel on shared hosting.

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

### 5. First administrator

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
cd ~/domains/realvows.skillleo.com/app
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
