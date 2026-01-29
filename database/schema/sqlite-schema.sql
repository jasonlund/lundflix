CREATE TABLE IF NOT EXISTS "cache" (
    "key" varchar(255) NOT NULL PRIMARY KEY,
    "value" text NOT NULL,
    "expiration" integer NOT NULL
);

CREATE TABLE IF NOT EXISTS "cache_locks" (
    "key" varchar(255) NOT NULL PRIMARY KEY,
    "owner" varchar(255) NOT NULL,
    "expiration" integer NOT NULL
);

CREATE TABLE IF NOT EXISTS "users" (
    "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
    "plex_id" varchar(255),
    "plex_token" text,
    "plex_username" varchar(255),
    "plex_thumb" varchar(255),
    "name" varchar(255) NOT NULL,
    "email" varchar(255) NOT NULL,
    "email_verified_at" datetime,
    "password" varchar(255),
    "two_factor_secret" text,
    "two_factor_recovery_codes" text,
    "two_factor_confirmed_at" datetime,
    "remember_token" varchar(100),
    "created_at" datetime,
    "updated_at" datetime
);
CREATE UNIQUE INDEX "users_email_unique" ON "users" ("email");
CREATE UNIQUE INDEX "users_plex_id_unique" ON "users" ("plex_id");

CREATE TABLE IF NOT EXISTS "password_reset_tokens" (
    "email" varchar(255) NOT NULL PRIMARY KEY,
    "token" varchar(255) NOT NULL,
    "created_at" datetime
);

CREATE TABLE IF NOT EXISTS "sessions" (
    "id" varchar(255) NOT NULL PRIMARY KEY,
    "user_id" integer,
    "ip_address" varchar(45),
    "user_agent" text,
    "payload" text NOT NULL,
    "last_activity" integer NOT NULL
);
CREATE INDEX "sessions_user_id_index" ON "sessions" ("user_id");
CREATE INDEX "sessions_last_activity_index" ON "sessions" ("last_activity");

CREATE TABLE IF NOT EXISTS "jobs" (
    "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
    "queue" varchar(255) NOT NULL,
    "payload" text NOT NULL,
    "attempts" integer NOT NULL,
    "reserved_at" integer,
    "available_at" integer NOT NULL,
    "created_at" integer NOT NULL
);
CREATE INDEX "jobs_queue_index" ON "jobs" ("queue");

CREATE TABLE IF NOT EXISTS "job_batches" (
    "id" varchar(255) NOT NULL PRIMARY KEY,
    "name" varchar(255) NOT NULL,
    "total_jobs" integer NOT NULL,
    "pending_jobs" integer NOT NULL,
    "failed_jobs" integer NOT NULL,
    "failed_job_ids" text NOT NULL,
    "options" text,
    "cancelled_at" integer,
    "created_at" integer NOT NULL,
    "finished_at" integer
);

CREATE TABLE IF NOT EXISTS "failed_jobs" (
    "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
    "uuid" varchar(255) NOT NULL,
    "connection" text NOT NULL,
    "queue" text NOT NULL,
    "payload" text NOT NULL,
    "exception" text NOT NULL,
    "failed_at" datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" ON "failed_jobs" ("uuid");

CREATE TABLE IF NOT EXISTS "shows" (
    "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
    "tvmaze_id" integer NOT NULL,
    "imdb_id" varchar(255),
    "thetvdb_id" integer,
    "name" varchar(255) NOT NULL,
    "type" varchar(255),
    "language" varchar(255),
    "genres" text,
    "status" varchar(255),
    "runtime" integer,
    "premiered" date,
    "ended" date,
    "schedule" text,
    "num_votes" integer,
    "network" text,
    "web_channel" text,
    "created_at" datetime,
    "updated_at" datetime
);
CREATE UNIQUE INDEX "shows_tvmaze_id_unique" ON "shows" ("tvmaze_id");
CREATE INDEX "shows_name_index" ON "shows" ("name");
CREATE INDEX "shows_status_index" ON "shows" ("status");
CREATE INDEX "shows_imdb_id_index" ON "shows" ("imdb_id");
CREATE INDEX "shows_num_votes_index" ON "shows" ("num_votes");
CREATE INDEX "shows_thetvdb_id_index" ON "shows" ("thetvdb_id");

CREATE TABLE IF NOT EXISTS "movies" (
    "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
    "imdb_id" varchar(255) NOT NULL,
    "title" varchar(255) NOT NULL,
    "year" integer,
    "runtime" integer,
    "genres" text,
    "num_votes" integer,
    "created_at" datetime,
    "updated_at" datetime
);
CREATE UNIQUE INDEX "movies_imdb_id_unique" ON "movies" ("imdb_id");
CREATE INDEX "movies_title_index" ON "movies" ("title");
CREATE INDEX "movies_year_index" ON "movies" ("year");
CREATE INDEX "movies_num_votes_index" ON "movies" ("num_votes");

CREATE TABLE IF NOT EXISTS "episodes" (
    "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
    "show_id" integer NOT NULL,
    "tvmaze_id" integer NOT NULL,
    "season" integer NOT NULL,
    "number" integer NOT NULL,
    "name" varchar(255) NOT NULL,
    "type" varchar(255) NOT NULL DEFAULT 'regular',
    "airdate" date,
    "airtime" varchar(255),
    "runtime" integer,
    "rating" text,
    "image" text,
    "summary" text,
    "created_at" datetime,
    "updated_at" datetime,
    FOREIGN KEY ("show_id") REFERENCES "shows" ("id") ON DELETE CASCADE
);
CREATE UNIQUE INDEX "episodes_tvmaze_id_unique" ON "episodes" ("tvmaze_id");
CREATE UNIQUE INDEX "episodes_show_id_season_type_number_unique" ON "episodes" ("show_id", "season", "type", "number");
CREATE INDEX "episodes_show_id_season_number_index" ON "episodes" ("show_id", "season", "number");

CREATE TABLE IF NOT EXISTS "requests" (
    "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
    "user_id" integer NOT NULL,
    "status" varchar(255) NOT NULL DEFAULT 'pending',
    "notes" text,
    "created_at" datetime,
    "updated_at" datetime,
    FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE
);
CREATE INDEX "requests_user_id_status_index" ON "requests" ("user_id", "status");

CREATE TABLE IF NOT EXISTS "request_items" (
    "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
    "request_id" integer NOT NULL,
    "requestable_type" varchar(255) NOT NULL,
    "requestable_id" integer NOT NULL,
    "created_at" datetime,
    "updated_at" datetime,
    FOREIGN KEY ("request_id") REFERENCES "requests" ("id") ON DELETE CASCADE
);
CREATE UNIQUE INDEX "request_items_request_id_requestable_type_requestable_id_unique" ON "request_items" ("request_id", "requestable_type", "requestable_id");
CREATE INDEX "request_items_requestable_type_requestable_id_index" ON "request_items" ("requestable_type", "requestable_id");

CREATE TABLE IF NOT EXISTS "features" (
    "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
    "name" varchar(255) NOT NULL,
    "scope" varchar(255) NOT NULL,
    "value" text NOT NULL,
    "created_at" datetime,
    "updated_at" datetime
);
CREATE UNIQUE INDEX "features_name_scope_unique" ON "features" ("name", "scope");

CREATE TABLE IF NOT EXISTS "media" (
    "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
    "mediable_type" varchar(255) NOT NULL,
    "mediable_id" integer NOT NULL,
    "fanart_id" varchar(255) NOT NULL,
    "type" varchar(255) NOT NULL,
    "url" varchar(255) NOT NULL,
    "path" varchar(255),
    "lang" varchar(255),
    "likes" integer NOT NULL DEFAULT 0,
    "season" integer,
    "disc" varchar(255),
    "disc_type" varchar(255),
    "created_at" datetime,
    "updated_at" datetime
);
CREATE UNIQUE INDEX "media_mediable_type_mediable_id_fanart_id_unique" ON "media" ("mediable_type", "mediable_id", "fanart_id");
CREATE INDEX "media_mediable_type_mediable_id_index" ON "media" ("mediable_type", "mediable_id");
CREATE INDEX "media_mediable_type_mediable_id_type_index" ON "media" ("mediable_type", "mediable_id", "type");

CREATE TABLE IF NOT EXISTS "migrations" (
    "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
    "migration" varchar(255) NOT NULL,
    "batch" integer NOT NULL
);

INSERT INTO "migrations" ("migration", "batch") VALUES ('0001_01_01_000000_create_users_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('0001_01_01_000001_create_cache_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('0001_01_01_000002_create_jobs_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2025_09_02_075243_add_two_factor_columns_to_users_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_12_031514_add_plex_fields_to_users_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_12_031855_make_password_nullable_on_users_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_13_050454_create_shows_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_13_054842_create_movies_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_14_050753_change_movies_year_to_integer', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_14_051527_add_imdb_id_to_shows_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_14_054502_add_num_votes_to_movies_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_14_181709_add_num_votes_to_shows_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_15_020357_create_episodes_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_15_060810_create_requests_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_15_060811_create_request_items_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_17_023051_add_unique_constraint_to_episodes_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_20_050507_create_features_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_22_051358_create_media_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_23_035327_add_thetvdb_id_to_shows_table', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_29_050704_convert_movie_genres_to_json', 1);
INSERT INTO "migrations" ("migration", "batch") VALUES ('2026_01_29_050704_remove_unused_show_columns', 1);
