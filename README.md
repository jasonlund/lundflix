<h1 align="center">lundflix</h1>

<p align="center">
  <strong>A Plex request portal.</strong><br>
  Friends and family ask for movies and shows; admins fulfill them; everyone sees what's available.
</p>

<p align="center">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white">
  <img alt="Laravel" src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white">
  <img alt="Livewire" src="https://img.shields.io/badge/Livewire-4-4E56A6?logo=livewire&logoColor=white">
  <img alt="Tailwind" src="https://img.shields.io/badge/Tailwind-4-38BDF8?logo=tailwindcss&logoColor=white">
  <img alt="Tests" src="https://github.com/jasonlund/lundflix/actions/workflows/tests.yml/badge.svg">
  <img alt="Lint" src="https://github.com/jasonlund/lundflix/actions/workflows/lint.yml/badge.svg">
</p>

## About

lundflix is a self-hosted request portal that sits in front of a Plex server. Friends and family sign in with their Plex account, search for movies and shows, and submit requests for anything missing from the library. Admins triage requests, mark them fulfilled (or not found), and the dashboard reflects current availability in real time.

## Features

- **Plex OAuth login** — Users sign in with their Plex account; access is gated to members of the configured server.
- **Movie & TV search** — Typesense-powered full-text search across the entire catalog via Laravel Scout.
- **Request cart** — Add movies or individual episodes, submit a bulk request, track its status end-to-end.
- **Personal dashboard** — Filter requests by status (pending, fulfilled, not found) and manage subscriptions.
- **Admin panel** — Filament 5 back office for triaging requests, managing the library, Plex servers, and users.
- **Live availability tracking** — Scheduled jobs sync the Plex library so the dashboard always reflects what's actually on disk.
- **Slack notifications** — Admins get pinged in Slack on new requests and library updates.
- **Queued background work** — Horizon-managed jobs handle fulfillment, availability scans, and library sync.

## Tech Stack

**Backend**
- PHP 8.4 · Laravel 12 · Livewire 4
- Fortify (Plex OAuth) · Horizon (queues) · Scout + Typesense (search) · Pennant (feature flags)

**Frontend**
- Flux UI Pro 2 · Tailwind CSS v4 · Alpine.js · Vite 7

**Admin**
- Filament 5

**Testing & Quality**
- Pest 4 · PHPStan / Larastan · Laravel Pint · Rector · Prettier

**Observability**
- Laravel Nightwatch

## Local Setup

The app is served by [Laravel Herd](https://herd.laravel.com/) at `https://lundflix.test`.

```bash
git clone https://github.com/jasonlund/lundflix.git
cd lundflix
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
```

A seeded admin is available at `admin@lundflix.com` / `password`.

### App-specific environment variables

Standard Laravel vars (database, mail, cache, session) live in `.env.example`. The keys below are specific to lundflix:

| Group | Keys |
|---|---|
| Plex | `PLEX_CLIENT_IDENTIFIER`, `PLEX_PRODUCT_NAME`, `PLEX_SERVER_IDENTIFIER`, `SEED_PLEX_TOKEN`, `PLEX_WEBHOOK_SECRET` |
| Metadata | `TMDB_API_KEY` |
| Typesense | `TYPESENSE_API_KEY`, `TYPESENSE_HOST`, `TYPESENSE_PORT`, `TYPESENSE_PROTOCOL` |
| Source search | `SEARCH_BASE_URL`, `SEARCH_UID`, `SEARCH_PASS` |
| FTP | `FTP_HOST`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_ROOT` |
| URL hashing | `SQIDS_ALPHABET`, `SQIDS_MIN_LENGTH` |
| Slack | `SLACK_ENABLED`, `SLACK_BOT_USER_OAUTH_TOKEN`, `SLACK_BOT_USER_DEFAULT_CHANNEL`, `SLACK_LIBRARY_CHANNEL` |

### External services

- A reachable Plex Media Server (for OAuth login and library sync).
- A Typesense instance (for catalog search).
- A Redis instance (for queues and cache).

### Background processes

```bash
php artisan horizon          # queue worker
php artisan schedule:work    # cron loop for availability sync
```

## Testing & Quality

Tests are written with [Pest 4](https://pestphp.com/) and live in `tests/Feature` and `tests/Unit`.

```bash
php artisan test --compact                          # run the full suite
php artisan test --compact tests/Feature/Foo.php    # run one file
php artisan test --compact --filter=name            # filter by test name
```

### Static analysis & formatting

```bash
vendor/bin/pint              # PHP formatting (Laravel Pint)
composer phpstan             # static analysis (Larastan)
npm run lint                 # JS / Blade linting
npm run format:check         # Prettier check
```

CI runs the same checks on every push via the `tests` and `lint` GitHub Actions workflows.

## License

[MIT](LICENSE) © Jason Lund
