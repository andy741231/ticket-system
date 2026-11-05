# UHPH Hub

Internal helpdesk and communications hub for University of Houston Population Health. Built with Laravel 12, Inertia.js, and Vue 3.

## Features

- **Ticketing system** — tickets with assignments, comments, file attachments, due dates, and status tracking
- **RBAC** — role-based access control via Spatie Laravel Permission, including per-user permission overrides
- **Newsletter campaigns** — template editor (TipTap rich text), subscriber/group management, scheduled sends, and click tracking
- **Annotations** — document annotation system with image/PDF support (see `docs/ANNOTATION_SYSTEM.md`)
- **Image processing** — Intervention Image + ImageMagick
- **Document conversion** — DOCX→PDF via LibreOffice (`app/Services/DocxToPdfConverter.php`)
- **Screenshots** — optional Puppeteer/Chrome integration (see `docs/PUPPETEER_SETUP.md`)

## Requirements

- PHP ^8.2 with Composer
- Node.js + npm
- MySQL
- ImageMagick (`convert`/`magick` binary) for image features
- LibreOffice (`soffice`) for DOCX→PDF conversion
- Optional: Redis/Memcached (defaults to file/database drivers — not required locally)

## Getting Started

```bash
# Install dependencies
composer install
npm install

# Configure environment
cp example.env .env
php artisan key:generate

# Update DB_* credentials in .env, then:
php artisan migrate --seed

# Run the app (server + queue + logs + vite)
composer run dev
```

The app is served at `http://localhost:8000`. See `AGENTS.md` for detailed agent/developer guidance.

### Environment Notes

- `example.env` is the committed template — never commit a real `.env`
- **Mail**: `campus_smtp` (`post-office.uh.edu`) only works inside the UH network. Off-campus developers should set `MAIL_MAILER=log` or configure an external SMTP relay
- **`MAGICK_PATH`**: directory containing the ImageMagick binary, if not on the default PATH
- **RBAC seeding**: `RBAC_USE_PRODUCTION_SEEDER=true` + `RBAC_SEED_CREATE_ADMIN=true` creates roles/permissions and an admin user on `migrate --seed`

## Development

```bash
composer test                    # PHPUnit tests
vendor/bin/pint                  # PHP code style
npm run build                    # production frontend build
npm run test:newsletter-editor   # newsletter editor regression script
```

## Documentation

- [RBAC Guide](docs/RBAC.md)
- [Annotation System](docs/ANNOTATION_SYSTEM.md)
- [Puppeteer Setup](docs/PUPPETEER_SETUP.md)
- [External User Authentication](docs/EXTERNAL_USER_AUTHENTICATION.md)
- Additional feature notes in `docs/`

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
