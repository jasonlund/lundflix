# Replace fanart.tv with TMDB Images + Add TV Show Metadata

## Context

The app currently uses fanart.tv for all artwork (posters, backgrounds, logos) via a lazy-fetch pattern — images are fetched on-demand when a user hits the ArtController, then cached in the `media` table. This is being replaced with TMDB as the single source of truth for images, synced eagerly during the existing TMDB pipeline. Additionally, TV shows currently only have TVMaze metadata — TMDB will provide richer metadata (overview, tagline, content ratings, etc.).

## Scope

**In scope:**
- Evolve `media` table schema from fanart.tv to TMDB
- Store TMDB images (posters, backdrops, logos) for both movies and shows
- Add TMDB metadata columns to `shows` table
- New TMDBService methods for TV shows
- New `SyncTMDBShows` command (mirrors `SyncTMDBMovies`)
- Expand movie sync to include images
- Rewrite ArtController to serve TMDB images (no more API calls at request time)
- Remove all fanart infrastructure (service, jobs, actions, sync command, enums, config)

**Out of scope:** Season images, episode images, cast/crew, networks, episode runtimes

---

## Phase 1: Evolve Media Table

**Migration:** Restructure `media` table from fanart.tv to TMDB schema.

| Change | Details |
|---|---|
| Rename `fanart_id` → `file_path` | Stores TMDB file path (e.g., `/abc123.jpg`) |
| Replace `likes` (int) → `vote_average` (float) | TMDB's quality score |
| Add `vote_count` (int unsigned) | Number of votes |
| Add `width` (int unsigned) | Image width in pixels |
| Add `height` (int unsigned) | Image height in pixels |
| Drop `disc`, `disc_type` | Fanart-specific, not used by TMDB |
| Drop `url` | Will be constructed from `file_path` + base URL + size |
| Keep `path` | For future local file caching |
| Keep `season`, `is_active`, `lang` | Same semantics |

Update unique index: `(mediable_type, mediable_id, file_path)` replaces `(mediable_type, mediable_id, fanart_id)`.

**`type` column values change** from fanart keys (`hdmovielogo`, `movieposter`, etc.) to TMDB types (`poster`, `backdrop`, `logo`). Unified across movies and shows.

**New enum:** `App\Enums\ArtworkType` with cases `Poster = 'poster'`, `Backdrop = 'backdrop'`, `Logo = 'logo'`. Replaces `MovieArtwork`, `TvArtwork`, `TvArtworkLevel`.

**Update `Media` model:**
- Add `url()` method to construct full TMDB CDN URL: `https://image.tmdb.org/t/p/{size}/{file_path}`
- Accept size parameter with sensible defaults per type (poster → `w780`, backdrop → `w1280`, logo → `w500`)
- Add `originalUrl()` for full-resolution
- Update casts: `vote_average` → float, `type` → `ArtworkType` enum

**Files:**
- `database/migrations/xxxx_restructure_media_table_for_tmdb.php` (new)
- `app/Models/Media.php`
- `app/Enums/ArtworkType.php` (new)

---

## Phase 2: TMDB Image Storage Action

**New action:** `App\Actions\TMDB\UpsertTMDBImages`

Handles storing TMDB images for any model (Movie or Show). Mirrors `UpsertFanart` pattern:

1. Receive images response (`posters[]`, `backdrops[]`, `logos[]`)
2. Build Media records from each image (file_path, type, lang, vote_average, vote_count, width, height)
3. Select best image per type: prefer English/null language, sort by `vote_average` desc (tiebreak by `vote_count`)
4. Mark best as `is_active = true`
5. Upsert all records keyed by `(mediable_type, mediable_id, file_path)`

**Files:**
- `app/Actions/TMDB/UpsertTMDBImages.php` (new)

---

## Phase 3: Expand Movie TMDB Sync with Images

**Modify `TMDBService`:** Add `images` to `append_to_response` on movie detail calls (currently `release_dates,alternative_titles` → `release_dates,alternative_titles,images`). Add `include_image_language=en,null` query parameter.

**Modify `SyncTMDBMovies`:** After upserting metadata, also upsert images via `UpsertTMDBImages` for each batch.

**Files:**
- `app/Services/ThirdParty/TMDBService.php`
- `app/Console/Commands/Scheduled/SyncTMDBMovies.php`

---

## Phase 4: Add TMDB Show Integration

### 4a: Shows Table Migration

Add columns to `shows` table:

| Column | Type | Notes |
|---|---|---|
| `tmdb_id` | int unsigned, nullable, indexed | TMDB show ID |
| `tmdb_synced_at` | timestamp, nullable | Last sync time |
| `overview` | text, nullable | Show synopsis (new data) |
| `tagline` | text, nullable | Marketing tagline (new data) |
| `original_name` | varchar, nullable | Original language title |
| `original_language` | varchar, nullable | ISO 639-1 code (cast: `LanguageFromCode`) |
| `spoken_languages` | json, nullable | Array of language objects |
| `production_companies` | json, nullable | Array of company objects |
| `origin_country` | json, nullable | Array of ISO 3166-1 codes |
| `content_ratings` | json, nullable | Per-country TV ratings (e.g., TV-MA) |
| `alternative_titles` | json, nullable | Alternate titles by country |
| `homepage` | varchar, nullable | Official website URL |
| `in_production` | boolean, nullable | Currently producing episodes |

### 4b: TMDBService Show Methods

Add to `TMDBService`:
- `findShowByExternalId(string $externalId, string $source = 'imdb_id')` — single lookup via `/find/{id}`, reads `tv_results`
- `findManyShowsByExternalId(array $ids, string $source = 'imdb_id')` — concurrent pool (same pattern as `findManyByImdbId`)
- `showDetails(int $tmdbId)` — `/tv/{id}?append_to_response=images,external_ids,content_ratings,alternative_titles&include_image_language=en,null`
- `showDetailsMany(array $tmdbIds)` — concurrent pool
- `changedShowIds(?string $startDate, ?string $endDate)` — `/tv/changes` endpoint

### 4c: UpsertTMDBShowData Action

New action `App\Actions\TMDB\UpsertTMDBShowData`:
- `mapFromApi(array $details)` — maps TMDB response to show columns
- `upsert(array $shows)` — batch upsert keyed by `tvmaze_id`
- Backfill `thetvdb_id` from TMDB's `external_ids.tvdb_id` when missing locally

### 4d: SyncTMDBShows Command

New command `tmdb:sync-shows` — mirrors `SyncTMDBMovies`:
- Phase 1: Sync unsynced shows (has `imdb_id` but no `tmdb_synced_at`)
  - Find via `/find/{imdb_id}` → `tv_results`
  - Fallback: try `/find/{thetvdb_id}` with `tvdb_id` source for shows missing IMDb ID
  - Fetch details + images
- Phase 2: Sync recently changed shows via `/tv/changes`
- Options: `--fresh`, `--limit=N`
- Stores metadata via `UpsertTMDBShowData` and images via `UpsertTMDBImages`

### 4e: Update Show Model

- Add casts for new columns (dates, arrays, enums, LanguageFromCode)
- Update `artworkExternalIdValue()` to return `tmdb_id` instead of `thetvdb_id`

**Files:**
- `database/migrations/xxxx_add_tmdb_columns_to_shows_table.php` (new)
- `app/Services/ThirdParty/TMDBService.php`
- `app/Actions/TMDB/UpsertTMDBShowData.php` (new)
- `app/Console/Commands/Scheduled/SyncTMDBShows.php` (new)
- `app/Models/Show.php`

---

## Phase 5: Rewrite ArtController

**Simplify ArtController** — no more API calls at request time:
1. Decode sqid, load model (Movie or Show)
2. Query `media` for active record matching type
3. If found, redirect to constructed TMDB CDN URL (via `Media::url()`)
4. If not found, 404

**Remove:** FanartTVService dependency, lazy-fetch logic, StoreFanart dispatch, fanart URL manipulation, preview URL logic, all caching logic (missing cache, error cache).

**Update HasArtwork trait:**
- `canHaveArt()` checks `tmdb_id` instead of `thetvdb_id`/`imdb_id` — unified for both models
- `artworkExternalIdValue()` returns `tmdb_id` on both Movie and Show
- Remove `canFetchArt()` and cache key methods (no more lazy-fetch caching)
- Simplify `artUrl()` — just check `canHaveArt()` and generate route

**TYPE_MAP simplification:** Direct 1:1 mapping:
- `logo` → `ArtworkType::Logo`
- `poster` → `ArtworkType::Poster`
- `background` → `ArtworkType::Backdrop`

**Files:**
- `app/Http/Controllers/ArtController.php`
- `app/Models/Concerns/HasArtwork.php`
- `app/Models/Movie.php` (update `artworkExternalIdValue`)

---

## Phase 6: Remove Fanart Infrastructure

**Delete:**
- `app/Services/ThirdParty/FanartTVService.php`
- `app/Jobs/StoreFanart.php`
- `app/Actions/Fanart/UpsertFanart.php`
- `app/Console/Commands/Scheduled/SyncFanartUpdates.php`
- `app/Enums/MovieArtwork.php`
- `app/Enums/TvArtwork.php`
- `app/Enums/TvArtworkLevel.php`

**Remove fanart references from:**
- Scheduled task registration (`routes/console.php` or equivalent)
- `config/services.php` — remove `fanart` key and `FANART_API_KEY`
- `.env` / `.env.example` — remove `FANART_API_KEY`
- Any service providers or other files referencing fanart

**Files:**
- Delete all files listed above
- Update config, env, schedule, and any remaining references

---

## Phase 7: Tests

**Update existing tests:**
- `tests/Feature/ArtControllerTest.php` — rewrite for TMDB URL construction, no more API mocking
- `tests/Feature/StoreFanartTest.php` → delete or replace with `UpsertTMDBImagesTest.php`
- `tests/Feature/SyncTMDBMoviesTest.php` — update for images in response

**New tests:**
- `tests/Feature/TMDB/UpsertTMDBImagesTest.php` — image storage, is_active selection, language preference, upsert idempotency
- `tests/Feature/TMDB/SyncTMDBShowsTest.php` — sync phases, external ID lookup, metadata storage, image storage
- `tests/Feature/TMDB/UpsertTMDBShowDataTest.php` — field mapping, thetvdb_id backfill
- `tests/Unit/Models/MediaTest.php` — URL construction, size defaults
- `tests/Unit/Enums/ArtworkTypeTest.php` — snapshot test for labels/colors
- Update `tests/Unit/Models/ShowTest.php` — art URL with tmdb_id

---

## Verification

1. Run `php artisan tmdb:sync-movies --limit=5` — verify images stored in media table
2. Run `php artisan tmdb:sync-shows --limit=5` — verify metadata + images stored
3. Hit `/art/movie/{sqid}/poster` — verify redirect to `image.tmdb.org` URL
4. Hit `/art/show/{sqid}/logo` — verify redirect to TMDB logo URL
5. Check media table: multiple images per model+type, one `is_active` per type
6. Run `php artisan test --compact` — full suite passes
7. Run `composer pint`, `npm run format`, `composer phpstan` — all pass
