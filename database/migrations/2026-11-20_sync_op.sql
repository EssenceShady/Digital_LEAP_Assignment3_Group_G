-- =====================================================================
-- Migration: offline outbox idempotency (20 November 2026)
--
-- Additive only. Creates one new table; no existing table, column or row
-- is touched. Safe to run more than once.
--
--   mysql -u root digital_leap < database/migrations/2026-11-20_sync_op.sql
-- =====================================================================

CREATE TABLE IF NOT EXISTS sync_op (
  op_id           CHAR(36) NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  op_kind         VARCHAR(40) NOT NULL,
  payload_hash    CHAR(64) NOT NULL,
  result_entry_id INT UNSIGNED NULL,
  result_status   VARCHAR(20) NULL,
  status          ENUM('processing','applied') NOT NULL DEFAULT 'processing',
  created_at      DATETIME NOT NULL,
  PRIMARY KEY (op_id),
  KEY idx_sync_op_user (user_id, created_at),
  CONSTRAINT fk_sync_op_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
