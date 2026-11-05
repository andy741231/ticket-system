# AGENTS.md

Guidance for AI agents and developers working in this repository.

## Project Overview

"UHPH Hub" — a Laravel 12 + Inertia.js + Vue 3 ticket/helpdesk application for University of Houston Population Health. Uses Vite + Tailwind for the frontend, Spatie Permission for RBAC, and a custom `campus_smtp` mailer.

## Tech Stack

- PHP ^8.2, Laravel ^12, Inertia.js ^2, Vue 3
- MySQL (primary DB); cache via `file`, sessions and queue via `database` by default
- Spatie Laravel Permission (RBAC), Laravel Sanctum, Tighten Ziggy
- intervention/image + `MAGICK_PATH` (ImageMagick) for image processing
- PHPOffice PHPWord + smalot/pdfparser for document conversion (uses LibreOffice `soffice` for DOCX→PDF — see `app/Services/DocxToPdfConverter.php`)
- Puppeteer/Chrome for screenshots — optional, see `docs/PUPPETEER_SETUP.md`

## Setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Configure environment
cp example.env .env
php artisan key:generate

# 3. Configure DB_* in .env (see "Environment Notes" below)

# 4. Database
php artisan migrate --seed

# 5. Link public storage (required for file uploads / avatars / ticket images)
php artisan storage:link    # or `php artisan storage:setup` (custom wrapper)

# 6. Run the app (serves on http://localhost:8000)
composer run dev    # runs serve + queue + pail logs + vite concurrently
# or individually:
php artisan serve
npm run dev         # vite dev server
php artisan queue:listen --tries=1   # needed for queued mail/newsletters
```

Assets: `npm run build` for production build; `npm run test:newsletter-editor` for the newsletter editor regression script.

Scheduled tasks (recurring campaigns, scheduled sends, log/temp cleanup — see `bootstrap/app.php`) need the scheduler running locally: `php artisan schedule:work`. Without it, scheduled newsletter sends won't fire in dev.

### Useful custom commands

```bash
php artisan mail:test-smtp              # verify SMTP connectivity (useful for checking UH network reach)
php artisan rbac:sync-permissions       # sync permissions/roles; supports --dry-run
php artisan rbac:prune-overrides        # clean expired permission overrides; supports --dry-run
php artisan campaigns:process-scheduled-sends   # manually run scheduled campaign sends
```

## Testing & Quality

- Tests: `composer test` (runs `php artisan test`, PHPUnit 11)
- Lint/format PHP: `vendor/bin/pint`
- Frontend has no lint script configured

## Environment Notes

- `example.env` is the committed template — copy it to `.env` and fill in secrets. `.env`, `.env.backup`, `.env.production`, and `.env.example` are all gitignored.
- `APP_KEY` must be generated per environment (`php artisan key:generate`).
- **Mail**: `MAIL_MAILER=campus_smtp` uses `post-office.uh.edu`, which only works inside the UH network. Off-campus devs should use `MAIL_MAILER=log` or an external SMTP relay.
- **`MAGICK_PATH`**: a PATH-style list of directories (e.g. `/opt/homebrew/bin:/usr/local/bin`) that `app/Services/ImageProcessingService.php` searches to locate the ImageMagick binary. Set it when `convert`/`magick` isn't on the web server's PATH.
- **RBAC seeding**: `RBAC_USE_PRODUCTION_SEEDER=true` makes `DatabaseSeeder` run `ProductionRbacSeeder`, which creates roles/permissions and (when `RBAC_SEED_CREATE_ADMIN=true`) an admin user from `RBAC_SEED_ADMIN_*`. Set `RBAC_USE_PRODUCTION_SEEDER=false` for the standard seeder.
- Sessions, cache, and queue default to `database`/`file` drivers — no Redis/Memcached required locally.

## Conventions

- Backend/frontend/UX/mailer rules for agents live in `.windsurf/rules/` (`global.md`, `backend.md`, `frontend.md`, `mailer.md`, `pm.md`, `ux.md`) — read the relevant one before editing that area.
- Feature docs are in `docs/` (RBAC, annotations, newsletter system, Puppeteer setup).
- Frontend routes/pages use Inertia; route model binding and Ziggy (`@routes`) are in use.
- Do not commit `.env` or real credentials — `example.env` contains placeholders only.
