-- Additive migration for an existing Digital LEAP database.
-- Creates the per-account settings table. It drops nothing and changes no row.
-- Apply with:  mysql -u root digital_leap < database/migrations/2026-11-20_user_preference.sql

CREATE TABLE IF NOT EXISTS user_preference (
  user_id    INT UNSIGNED NOT NULL,
  pref_key   VARCHAR(64)  NOT NULL,
  pref_value VARCHAR(64)  NOT NULL,
  updated_at DATETIME     NOT NULL,
  PRIMARY KEY (user_id, pref_key),
  CONSTRAINT fk_user_preference_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
