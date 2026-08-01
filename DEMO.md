# RealVows — demo credentials

> ⚠️ **Local only.** Every account below shares one published password, and the
> login page carries one-click sign-in buttons. Neither may ever exist on a
> live server. The bypass requires `APP_ENV=local` **and**
> `REALVOWS_DEMO_LOGINS=true`; on anything else the route is not registered at
> all and the buttons do not render.

## Getting it running

```bash
php artisan realvows:demo     # builds all demo data (~1 minute — photos are
                              # processed through the real upload pipeline)
npm run build
php artisan serve
```

Then open `http://localhost:8000/login` and use the buttons under
**Demo · local only**.

## Accounts

Password for every account: **`realvows2026`**

| Account | Email | What they show you |
|---|---|---|
| Super admin | `admin@realvows.test` | Settings, verification queue, audit log |
| Reviewer | `reviewer@realvows.test` | Verification queue only — deliberately **no** settings access |
| Men | `boy1@realvows.test` … `boy15@realvows.test` | Member view |
| Women | `girl1@realvows.test` … `girl15@realvows.test` | Member view |

`boy1`–`boy12` and `girl1`–`girl12` are **verified**. `boy13` / `girl13` were
**sent back** with a reason. `boy14`, `boy15`, `girl14`, `girl15` are **waiting
in the queue** — sign in as the reviewer to approve or reject them and watch
the emails queue.

`girl1`, `girl2` and `girl3` have purchase history and credits, so the ledger
is not empty.

## What is in the database

| | |
|---|---|
| Members | 30 (15 men, 15 women), every profile 100% complete |
| Photos | 120 — four per member, **all 120 distinct files** |
| Verifications | 24 verified · 4 pending · 2 sent back |
| Matchable pool | 24 (verification gates it — the other 6 are correctly excluded) |
| Match rows | 20 (10 pairs, scored both directions) |
| Purchases / ledger | 3 each; balances derive from summing the ledger |

## Two things to know before you demo it

**Match rows exist but no screen shows them.** The matching engine and the
locked teaser cards are Phase 2. The rows are seeded so the schema, the score
breakdown and the `compatibility_rationale` wording are all inspectable now —
you can see them in the database, not on the dashboard.

**The people are not real.** Portraits come from a service that supplies demo
faces. As the addendum warns, identifiable photographs of real people cannot
ship as fake members on a live platform — these are replaced by genuine member
uploads before launch.

## Resetting

`php artisan realvows:demo` is safe to re-run. It clears everything on
`@realvows.test` first and rebuilds, so it never accumulates duplicates.

The source images live in `storage/app/seed/` and are git-ignored (7 MB). If
they are missing, the seeder skips photos rather than failing — re-download
them with the block at the top of `DemoSeeder`'s history, or ask and they can
be regenerated.
