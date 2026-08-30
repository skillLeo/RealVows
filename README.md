# RealVows

A marriage-focused matchmaking platform. Members build a verified profile and
receive a small number of curated introductions; there is no browsing, no
searching and no member directory.

Built by **SkillLeo SMC Pvt Ltd** under agreement SKL-2026-RV-001.

## Stack

| | |
|---|---|
| Framework | Laravel 12 · PHP 8.4 |
| Front end | Blade for public pages, Vue 3 + Inertia for the member area |
| Build | Vite |
| Database | MySQL |
| Queue | Database driver, drained by cron |

PHP **8.4 or newer is required** — a dependency pulls in Symfony 8, which will
not install on 8.3.

## Running locally

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev
```

## Configuration

Operational settings are **not** in `.env`. They live in the `settings` table
and are edited from `/admin/settings`, so they can be changed without a deploy:

- **Payments** — gateway, environment, credentials
- **WhatsApp** — delivery toggle, Meta credentials, template name
- **AI** — provider key, model, feature toggles, monthly spend cap
- **Matching** — score threshold, matches per batch

Only `APP_KEY`, `APP_ENV`, `APP_DEBUG`, `APP_URL` and the mail block stay in
`.env`.

## Matching

Five weighted components, defined once in `config/matching.php` and summed to
100. They are read by both the engine and the public page that describes them —
a test fails the build if the two ever disagree.

An introduction is delivered only above the configured score threshold.

## Deploying

Deployment runs from GitHub Actions: **Actions → Deploy → Run workflow**, then
type `DEPLOY`. It is manual on purpose, so a commit landing on `main` is not the
same event as a release.

The workflow refuses to deploy a commit that has not passed CI. It puts the site
into maintenance, syncs, migrates, re-caches and brings it back, and fails if
the home page does not answer 200. `.env` and `storage/` are never touched.

**Cron is required.** Without it the queue never drains and no email is ever
sent:

```
* * * * * cd /var/www/realvows && php artisan schedule:run >> /dev/null 2>&1
```

## Tests

```bash
php artisan test
```

The suite includes guards that hold the product to its own promises: no member
may contact another directly, no page may promise a channel that is switched
off, published percentages must equal the engine's configuration, and the
public stylesheet is locked to its design tokens.
