-- =====================================================================
-- Digital LEAP Internship Management System
-- COMP217 Assignment 3 - database schema and demonstration data
-- Engine: InnoDB / utf8mb4_unicode_ci / MariaDB 10.4+
-- Import once into phpMyAdmin or: mysql -u root < digital_leap.sql
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS digital_leap
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE digital_leap;

DROP TABLE IF EXISTS diva_turn;
DROP TABLE IF EXISTS diva_chat;
DROP TABLE IF EXISTS setting;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS message_reaction;
DROP TABLE IF EXISTS message;
DROP TABLE IF EXISTS conversation_member;
DROP TABLE IF EXISTS conversation;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS user_preference;
DROP TABLE IF EXISTS email_queue;
DROP TABLE IF EXISTS notification;
DROP TABLE IF EXISTS mark;
DROP TABLE IF EXISTS internship_report;
DROP TABLE IF EXISTS document;
DROP TABLE IF EXISTS site_visit;
DROP TABLE IF EXISTS grade_scale;
DROP TABLE IF EXISTS evaluation_score;
DROP TABLE IF EXISTS evaluation;
DROP TABLE IF EXISTS criterion;
DROP TABLE IF EXISTS logbook_day;
DROP TABLE IF EXISTS logbook_comment;
DROP TABLE IF EXISTS logbook_entry;
DROP TABLE IF EXISTS placement;
DROP TABLE IF EXISTS application;
DROP TABLE IF EXISTS student;
DROP TABLE IF EXISTS access_token;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS organisation;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;

-- ---------------------------------------------------------------------
-- D9  User accounts and access control
-- ---------------------------------------------------------------------

CREATE TABLE roles (
  role_id     TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_key    VARCHAR(30)  NOT NULL,
  role_name   VARCHAR(60)  NOT NULL,
  description VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id),
  UNIQUE KEY uq_roles_key (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  permission_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  perm_key      VARCHAR(60)  NOT NULL,
  description   VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (permission_id),
  UNIQUE KEY uq_permissions_key (perm_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role_id       TINYINT UNSIGNED  NOT NULL,
  permission_id SMALLINT UNSIGNED NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id, permission_id),
  KEY idx_rp_permission (permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (role_id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (permission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE organisation (
  org_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(120) NOT NULL,
  sector        VARCHAR(60)  NOT NULL,
  address       VARCHAR(180) NOT NULL,
  district      VARCHAR(60)  NOT NULL,
  contact_person VARCHAR(100) NOT NULL,
  contact_email VARCHAR(120) NOT NULL,
  contact_phone VARCHAR(25)  NULL,
  mou_status    ENUM('none','pending','signed','expired') NOT NULL DEFAULT 'none',
  mou_expiry    DATE NULL,
  capacity      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (org_id),
  UNIQUE KEY uq_org_name (name),
  KEY idx_org_district (district)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  user_id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id           TINYINT UNSIGNED NOT NULL,
  organisation_id   INT UNSIGNED NULL,
  first_name        VARCHAR(60)  NOT NULL,
  last_name         VARCHAR(60)  NOT NULL,
  email             VARCHAR(120) NOT NULL,
  phone             VARCHAR(25)  NULL,
  password_hash     VARCHAR(255) NULL,
  status            ENUM('invited','active','suspended') NOT NULL DEFAULT 'invited',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  failed_attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until      DATETIME NULL,
  last_login_at     DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role_id, status),
  KEY idx_users_org (organisation_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (role_id),
  CONSTRAINT fk_users_org FOREIGN KEY (organisation_id) REFERENCES organisation (org_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE access_token (
  token_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  purpose    ENUM('invite','password_reset','supervisor_access') NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (token_id),
  UNIQUE KEY uq_token_hash (token_hash),
  KEY idx_token_user (user_id, purpose),
  CONSTRAINT fk_token_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_token_creator FOREIGN KEY (created_by) REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- D1  Student records
-- ---------------------------------------------------------------------

CREATE TABLE student (
  student_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  reg_no        VARCHAR(20) NOT NULL,
  programme     VARCHAR(80) NOT NULL,
  level         VARCHAR(10) NOT NULL,
  semester      VARCHAR(10) NOT NULL,
  academic_year VARCHAR(9)  NOT NULL,
  cgpa          DECIMAL(3,2) NULL,
  status        ENUM('active','on_placement','completed','withdrawn') NOT NULL DEFAULT 'active',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (student_id),
  UNIQUE KEY uq_student_user (user_id),
  UNIQUE KEY uq_student_reg (reg_no),
  CONSTRAINT fk_student_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- D2  Application and placement records
-- ---------------------------------------------------------------------

CREATE TABLE application (
  application_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id      INT UNSIGNED NOT NULL,
  org_id          INT UNSIGNED NOT NULL,
  preference_rank TINYINT UNSIGNED NOT NULL,
  motivation      TEXT NULL,
  status          ENUM('draft','submitted','shortlisted','allocated','rejected','withdrawn') NOT NULL DEFAULT 'draft',
  academic_year   VARCHAR(9) NOT NULL,
  submitted_at    DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (application_id),
  UNIQUE KEY uq_app_rank (student_id, academic_year, preference_rank),
  UNIQUE KEY uq_app_org (student_id, org_id, academic_year),
  KEY idx_app_org (org_id, status),
  CONSTRAINT fk_app_student FOREIGN KEY (student_id) REFERENCES student (student_id) ON DELETE CASCADE,
  CONSTRAINT fk_app_org FOREIGN KEY (org_id) REFERENCES organisation (org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE placement (
  placement_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id     INT UNSIGNED NOT NULL,
  org_id         INT UNSIGNED NOT NULL,
  coordinator_id INT UNSIGNED NOT NULL,
  mentor_id      INT UNSIGNED NOT NULL,
  supervisor_id  INT UNSIGNED NULL,
  start_date     DATE NOT NULL,
  end_date       DATE NOT NULL,
  total_weeks    TINYINT UNSIGNED NOT NULL DEFAULT 12,
  internship_type ENUM('external','in-house') NOT NULL DEFAULT 'external',
  career_fair_score DECIMAL(5,2) NULL,
  academic_year  VARCHAR(9) NOT NULL,
  status         ENUM('proposed','approved','active','completed','terminated') NOT NULL DEFAULT 'proposed',
  approved_by    INT UNSIGNED NULL,
  approved_at    DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (placement_id),
  UNIQUE KEY uq_placement_student_year (student_id, academic_year),
  KEY idx_placement_org (org_id, status),
  KEY idx_placement_mentor (mentor_id),
  KEY idx_placement_supervisor (supervisor_id),
  CONSTRAINT fk_placement_student FOREIGN KEY (student_id) REFERENCES student (student_id),
  CONSTRAINT fk_placement_org FOREIGN KEY (org_id) REFERENCES organisation (org_id),
  CONSTRAINT fk_placement_coord FOREIGN KEY (coordinator_id) REFERENCES users (user_id),
  CONSTRAINT fk_placement_mentor FOREIGN KEY (mentor_id) REFERENCES users (user_id),
  CONSTRAINT fk_placement_super FOREIGN KEY (supervisor_id) REFERENCES users (user_id) ON DELETE SET NULL,
  CONSTRAINT fk_placement_approver FOREIGN KEY (approved_by) REFERENCES users (user_id) ON DELETE SET NULL,
  CONSTRAINT chk_placement_dates CHECK (end_date > start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- D4  Logbook entries and discussion
-- ---------------------------------------------------------------------

CREATE TABLE logbook_entry (
  entry_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  placement_id INT UNSIGNED NOT NULL,
  week_no      TINYINT UNSIGNED NOT NULL,
  date_from    DATE NOT NULL,
  date_to      DATE NOT NULL,
  activities   TEXT NOT NULL,
  technical_skills TEXT NULL,
  challenges   TEXT NULL,
  meeting_notes TEXT NULL,
  reflection   TEXT NULL,
  hours        DECIMAL(4,1) NULL,
  status       ENUM('draft','submitted','approved','returned','mentor_reviewed') NOT NULL DEFAULT 'draft',
  submitted_at DATETIME NULL,
  reviewed_by  INT UNSIGNED NULL,
  reviewed_at  DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (entry_id),
  UNIQUE KEY uq_entry_week (placement_id, week_no),
  KEY idx_entry_status (status, submitted_at),
  CONSTRAINT fk_entry_placement FOREIGN KEY (placement_id) REFERENCES placement (placement_id) ON DELETE CASCADE,
  CONSTRAINT fk_entry_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (user_id) ON DELETE SET NULL,
  CONSTRAINT chk_entry_dates CHECK (date_to >= date_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per day worked, mirroring the Day / Date / Tasks / Hours grid
-- in the official log book (guidebook pages 19-20).
CREATE TABLE logbook_day (
  day_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entry_id   INT UNSIGNED NOT NULL,
  work_date  DATE NOT NULL,
  tasks      VARCHAR(1000) NOT NULL,
  hours      DECIMAL(4,1) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (day_id),
  UNIQUE KEY uq_day_once (entry_id, work_date),
  KEY idx_day_date (work_date),
  CONSTRAINT fk_day_entry FOREIGN KEY (entry_id) REFERENCES logbook_entry (entry_id) ON DELETE CASCADE,
  CONSTRAINT chk_day_hours CHECK (hours IS NULL OR (hours >= 0 AND hours <= 24))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logbook_comment (
  comment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entry_id   INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  parent_id  INT UNSIGNED NULL,
  body       TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (comment_id),
  KEY idx_comment_entry (entry_id, created_at),
  KEY idx_comment_parent (parent_id),
  CONSTRAINT fk_comment_entry FOREIGN KEY (entry_id) REFERENCES logbook_entry (entry_id) ON DELETE CASCADE,
  CONSTRAINT fk_comment_user FOREIGN KEY (user_id) REFERENCES users (user_id),
  CONSTRAINT fk_comment_parent FOREIGN KEY (parent_id) REFERENCES logbook_comment (comment_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- D5  Evaluations
-- ---------------------------------------------------------------------

CREATE TABLE criterion (
  criterion_id    SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  evaluation_type ENUM('supervisor','mentor','self','report') NOT NULL,
  name            VARCHAR(80) NOT NULL,
  max_score       TINYINT UNSIGNED NOT NULL DEFAULT 5,
  weight          DECIMAL(5,2) NOT NULL,
  sort_order      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  active          TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (criterion_id),
  UNIQUE KEY uq_criterion_name (evaluation_type, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluation (
  evaluation_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  placement_id    INT UNSIGNED NOT NULL,
  evaluator_id    INT UNSIGNED NOT NULL,
  evaluation_type ENUM('supervisor','mentor','self') NOT NULL,
  status          ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
  total_score     DECIMAL(5,2) NULL,
  comments        TEXT NULL,
  submitted_at    DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (evaluation_id),
  UNIQUE KEY uq_eval_once (placement_id, evaluation_type, evaluator_id),
  KEY idx_eval_evaluator (evaluator_id, status),
  CONSTRAINT fk_eval_placement FOREIGN KEY (placement_id) REFERENCES placement (placement_id) ON DELETE CASCADE,
  CONSTRAINT fk_eval_evaluator FOREIGN KEY (evaluator_id) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluation_score (
  score_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  evaluation_id INT UNSIGNED NOT NULL,
  criterion_id  SMALLINT UNSIGNED NOT NULL,
  score         DECIMAL(4,2) NOT NULL,
  comment       VARCHAR(255) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (score_id),
  UNIQUE KEY uq_score_once (evaluation_id, criterion_id),
  KEY idx_score_criterion (criterion_id),
  CONSTRAINT fk_score_eval FOREIGN KEY (evaluation_id) REFERENCES evaluation (evaluation_id) ON DELETE CASCADE,
  CONSTRAINT fk_score_criterion FOREIGN KEY (criterion_id) REFERENCES criterion (criterion_id),
  CONSTRAINT chk_score_positive CHECK (score >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- D7  Site visits
-- ---------------------------------------------------------------------

CREATE TABLE site_visit (
  visit_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  placement_id    INT UNSIGNED NOT NULL,
  mentor_id       INT UNSIGNED NOT NULL,
  scheduled_date  DATE NOT NULL,
  mode            ENUM('physical','virtual','phone') NOT NULL DEFAULT 'physical',
  actual_date     DATE NULL,
  findings        TEXT NULL,
  recommendations TEXT NULL,
  status          ENUM('scheduled','completed','cancelled','missed') NOT NULL DEFAULT 'scheduled',
  completed_at    DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (visit_id),
  KEY idx_visit_placement (placement_id, status),
  KEY idx_visit_date (scheduled_date),
  CONSTRAINT fk_visit_placement FOREIGN KEY (placement_id) REFERENCES placement (placement_id) ON DELETE CASCADE,
  CONSTRAINT fk_visit_mentor FOREIGN KEY (mentor_id) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Shared documents
-- ---------------------------------------------------------------------

CREATE TABLE document (
  doc_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  doc_type      ENUM('cv','placement_letter','mou','logbook_attachment','visit_report','evaluation_report','internship_report','career_fair_report','message_attachment') NOT NULL,
  student_id    INT UNSIGNED NULL,
  placement_id  INT UNSIGNED NULL,
  entry_id      INT UNSIGNED NULL,
  visit_id      INT UNSIGNED NULL,
  original_name VARCHAR(180) NOT NULL,
  stored_name   VARCHAR(80) NOT NULL,
  mime_type     VARCHAR(80) NOT NULL,
  file_size     INT UNSIGNED NOT NULL,
  uploaded_by   INT UNSIGNED NULL,
  uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_by   INT UNSIGNED NULL,
  reviewed_at   DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (doc_id),
  UNIQUE KEY uq_doc_stored (stored_name),
  KEY idx_doc_student (student_id),
  KEY idx_doc_placement (placement_id),
  KEY idx_doc_entry (entry_id),
  KEY idx_doc_visit (visit_id),
  KEY idx_doc_reviewer (reviewed_by),
  CONSTRAINT fk_doc_student FOREIGN KEY (student_id) REFERENCES student (student_id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_placement FOREIGN KEY (placement_id) REFERENCES placement (placement_id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_entry FOREIGN KEY (entry_id) REFERENCES logbook_entry (entry_id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (user_id) ON DELETE SET NULL,
  CONSTRAINT fk_doc_visit FOREIGN KEY (visit_id) REFERENCES site_visit (visit_id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_uploader FOREIGN KEY (uploaded_by) REFERENCES users (user_id) ON DELETE SET NULL,
  CONSTRAINT chk_doc_one_owner CHECK (
    (student_id IS NOT NULL) + (placement_id IS NOT NULL) + (entry_id IS NOT NULL) + (visit_id IS NOT NULL) = 1
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE internship_report (
  report_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  placement_id INT UNSIGNED NOT NULL,
  document_id  INT UNSIGNED NOT NULL,
  submitted_at DATETIME NULL,
  status       ENUM('pending','submitted','assessed') NOT NULL DEFAULT 'pending',
  assessed_by  INT UNSIGNED NULL,
  assessed_at  DATETIME NULL,
  score        DECIMAL(5,2) NULL,
  comments     TEXT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (report_id),
  UNIQUE KEY uq_report_placement (placement_id),
  KEY idx_report_document (document_id),
  CONSTRAINT fk_report_placement FOREIGN KEY (placement_id) REFERENCES placement (placement_id) ON DELETE CASCADE,
  CONSTRAINT fk_report_document FOREIGN KEY (document_id) REFERENCES document (doc_id),
  CONSTRAINT fk_report_assessor FOREIGN KEY (assessed_by) REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- D6  Marks and grades
-- ---------------------------------------------------------------------

CREATE TABLE grade_scale (
  grade_id       TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  grade_letter   VARCHAR(3) NOT NULL,
  min_score      DECIMAL(5,2) NOT NULL,
  max_score      DECIMAL(5,2) NOT NULL,
  points         DECIMAL(3,2) NOT NULL,
  classification VARCHAR(30) NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (grade_id),
  UNIQUE KEY uq_grade_letter (grade_letter),
  CONSTRAINT chk_grade_range CHECK (min_score < max_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mark (
  mark_id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  placement_id      INT UNSIGNED NOT NULL,
  logbook_pct       DECIMAL(5,2) NULL,
  supervisor_pct    DECIMAL(5,2) NULL,
  self_pct          DECIMAL(5,2) NULL,
  mentor_pct        DECIMAL(5,2) NULL,
  practical_pct     DECIMAL(5,2) NULL,
  report_pct        DECIMAL(5,2) NULL,
  career_fair_pct   DECIMAL(5,2) NULL,
  weighted_total    DECIMAL(5,2) NULL,
  grade_id          TINYINT UNSIGNED NULL,
  status            ENUM('draft','moderated','published') NOT NULL DEFAULT 'draft',
  computed_by       INT UNSIGNED NULL,
  computed_at       DATETIME NULL,
  moderated_by      INT UNSIGNED NULL,
  moderated_at      DATETIME NULL,
  moderation_reason VARCHAR(255) NULL,
  published_at      DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (mark_id),
  UNIQUE KEY uq_mark_placement (placement_id),
  KEY idx_mark_grade (grade_id),
  CONSTRAINT fk_mark_placement FOREIGN KEY (placement_id) REFERENCES placement (placement_id) ON DELETE CASCADE,
  CONSTRAINT fk_mark_grade FOREIGN KEY (grade_id) REFERENCES grade_scale (grade_id) ON DELETE SET NULL,
  CONSTRAINT fk_mark_computer FOREIGN KEY (computed_by) REFERENCES users (user_id) ON DELETE SET NULL,
  CONSTRAINT fk_mark_moderator FOREIGN KEY (moderated_by) REFERENCES users (user_id) ON DELETE SET NULL,
  CONSTRAINT chk_mark_logbook CHECK (logbook_pct BETWEEN 0 AND 100),
  CONSTRAINT chk_mark_supervisor CHECK (supervisor_pct BETWEEN 0 AND 100),
  CONSTRAINT chk_mark_self CHECK (self_pct BETWEEN 0 AND 100),
  CONSTRAINT chk_mark_mentor CHECK (mentor_pct BETWEEN 0 AND 100),
  CONSTRAINT chk_mark_practical CHECK (practical_pct BETWEEN 0 AND 100),
  CONSTRAINT chk_mark_report CHECK (report_pct BETWEEN 0 AND 100),
  CONSTRAINT chk_mark_career_fair CHECK (career_fair_pct BETWEEN 0 AND 100),
  CONSTRAINT chk_mark_total CHECK (weighted_total BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6.0  Communication layer
-- ---------------------------------------------------------------------

CREATE TABLE notification (
  notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED NOT NULL,
  type            VARCHAR(40) NOT NULL,
  title           VARCHAR(120) NOT NULL,
  body            VARCHAR(500) NULL,
  link_url        VARCHAR(200) NULL,
  is_read         TINYINT(1) NOT NULL DEFAULT 0,
  read_at         DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (notification_id),
  KEY idx_notification_user (user_id, is_read, created_at),
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_queue (
  queue_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED NULL,
  notification_id BIGINT UNSIGNED NULL,
  to_email        VARCHAR(120) NOT NULL,
  subject         VARCHAR(180) NOT NULL,
  body            TEXT NOT NULL,
  status          ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error      VARCHAR(255) NULL,
  scheduled_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at         DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (queue_id),
  KEY idx_queue_status (status, scheduled_at),
  KEY idx_queue_user (user_id),
  KEY idx_queue_notification (notification_id),
  CONSTRAINT fk_queue_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL,
  CONSTRAINT fk_queue_notification FOREIGN KEY (notification_id) REFERENCES notification (notification_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- D8  Audit log (append-only at database privilege level)
-- ---------------------------------------------------------------------

CREATE TABLE audit_log (
  audit_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(60) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id   INT UNSIGNED NULL,
  before_json JSON NULL,
  after_json  JSON NULL,
  ip_address  VARCHAR(45) NOT NULL,
  user_agent  VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (audit_id),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_user (user_id, created_at),
  KEY idx_audit_action (action, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- System settings
-- ---------------------------------------------------------------------

CREATE TABLE setting (
  setting_key   VARCHAR(60) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  data_type     ENUM('string','int','decimal','bool','json') NOT NULL DEFAULT 'string',
  description   VARCHAR(200) NULL,
  updated_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  CONSTRAINT fk_setting_user FOREIGN KEY (updated_by) REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- User feedback collected inside the workspace
-- ---------------------------------------------------------------------

CREATE TABLE feedback (
  feedback_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NULL,
  rating      TINYINT UNSIGNED NOT NULL,
  thoughts    TEXT NULL,
  suggestions TEXT NULL,
  page        VARCHAR(120) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (feedback_id),
  KEY idx_feedback_created (created_at),
  KEY idx_feedback_rating (rating),
  CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL,
  CONSTRAINT chk_feedback_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6.1  Placement messaging (threads are anchored to a placement)
-- ---------------------------------------------------------------------

CREATE TABLE conversation (
  conversation_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  placement_id    INT UNSIGNED NOT NULL,
  subject         VARCHAR(120) NOT NULL,
  topic           ENUM('general','logbook','report','visit','career_fair') NOT NULL DEFAULT 'general',
  created_by      INT UNSIGNED NULL,
  last_message_at DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (conversation_id),
  KEY idx_conversation_placement (placement_id),
  KEY idx_conversation_last (last_message_at),
  CONSTRAINT fk_conversation_placement FOREIGN KEY (placement_id) REFERENCES placement (placement_id) ON DELETE CASCADE,
  CONSTRAINT fk_conversation_creator FOREIGN KEY (created_by) REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conversation_member (
  conversation_id      INT UNSIGNED NOT NULL,
  user_id              INT UNSIGNED NOT NULL,
  member_role          ENUM('owner','member') NOT NULL DEFAULT 'member',
  last_read_message_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  muted                TINYINT(1) NOT NULL DEFAULT 0,
  joined_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (conversation_id, user_id),
  KEY idx_member_user (user_id),
  CONSTRAINT fk_member_conversation FOREIGN KEY (conversation_id) REFERENCES conversation (conversation_id) ON DELETE CASCADE,
  CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE message (
  message_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id   INT UNSIGNED NOT NULL,
  sender_id         INT UNSIGNED NULL,
  parent_message_id BIGINT UNSIGNED NULL,
  document_id       INT UNSIGNED NULL,
  kind              ENUM('text','system','document') NOT NULL DEFAULT 'text',
  body              TEXT NULL,
  client_token      CHAR(36) NULL,
  edited_at         DATETIME NULL,
  deleted_at        DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id),
  UNIQUE KEY uq_message_token (conversation_id, client_token),
  KEY idx_message_thread (conversation_id, message_id),
  KEY idx_message_parent (parent_message_id),
  KEY idx_message_document (document_id),
  CONSTRAINT fk_message_conversation FOREIGN KEY (conversation_id) REFERENCES conversation (conversation_id) ON DELETE CASCADE,
  CONSTRAINT fk_message_sender FOREIGN KEY (sender_id) REFERENCES users (user_id) ON DELETE SET NULL,
  CONSTRAINT fk_message_parent FOREIGN KEY (parent_message_id) REFERENCES message (message_id) ON DELETE SET NULL,
  CONSTRAINT fk_message_document FOREIGN KEY (document_id) REFERENCES document (doc_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE message_reaction (
  message_id BIGINT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  emoji      VARCHAR(16) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id, user_id, emoji),
  CONSTRAINT fk_reaction_message FOREIGN KEY (message_id) REFERENCES message (message_id) ON DELETE CASCADE,
  CONSTRAINT fk_reaction_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- DIVA conversation history (private to the account that asked)
-- ---------------------------------------------------------------------

CREATE TABLE diva_chat (
  chat_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  title       VARCHAR(120) NOT NULL,
  from_route  VARCHAR(80) NULL,
  turn_count  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  archived_at DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (chat_id),
  KEY idx_diva_chat_user (user_id, archived_at, updated_at),
  CONSTRAINT fk_diva_chat_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE diva_turn (
  turn_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  chat_id    INT UNSIGNED NOT NULL,
  question   VARCHAR(500) NOT NULL,
  answer     TEXT NOT NULL,
  intent     VARCHAR(40) NOT NULL,
  tier       ENUM('rules','retrieval','local') NOT NULL DEFAULT 'rules',
  confidence ENUM('high','medium','low') NOT NULL DEFAULT 'high',
  meta_json  LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (turn_id),
  KEY idx_diva_turn_chat (chat_id, turn_id),
  CONSTRAINT fk_diva_turn_chat FOREIGN KEY (chat_id) REFERENCES diva_chat (chat_id) ON DELETE CASCADE,
  CONSTRAINT chk_diva_turn_meta CHECK (meta_json IS NULL OR JSON_VALID(meta_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Offline outbox idempotency
-- One row per operation the offline shell sent, so a retried request is
-- applied at most once even when two tabs or devices race.
-- ---------------------------------------------------------------------

CREATE TABLE sync_op (
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

-- ---------------------------------------------------------------------
-- Per-account settings
-- One row per preference key: which notification families still earn an
-- email copy, and how the interface should read. Absent rows mean the
-- shipped defaults, so no seed data is required.
-- ---------------------------------------------------------------------

CREATE TABLE user_preference (
  user_id    INT UNSIGNED NOT NULL,
  pref_key   VARCHAR(64)  NOT NULL,
  pref_value VARCHAR(64)  NOT NULL,
  updated_at DATETIME     NOT NULL,
  PRIMARY KEY (user_id, pref_key),
  CONSTRAINT fk_user_preference_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Demonstration data
-- =====================================================================

INSERT INTO roles (role_id, role_key, role_name, description) VALUES
  (1,'admin','System Administrator','Manages accounts, settings and the audit trail'),
  (2,'coordinator','LEAP / Internship Coordinator','Allocates placements and publishes grades'),
  (3,'mentor','Academic Mentor','Supervises students academically and conducts site visits'),
  (4,'supervisor','Host Supervisor','Approves logbooks and evaluates students in the workplace'),
  (5,'student','Student','Submits logbooks and the internship report'),
  (6,'management','Management / AQA','Monitors performance and quality assurance indicators');

INSERT INTO permissions (permission_id, perm_key, description) VALUES
  (1,'dashboard.view','Open the role dashboard'),
  (2,'profile.view','View own profile'),
  (3,'profile.edit','Edit own profile'),
  (4,'preference.view','View placement preferences'),
  (5,'preference.submit','Submit placement preferences'),
  (6,'placement.view.own','View placements the user is assigned to'),
  (7,'placement.view.all','View every placement'),
  (8,'placement.allocate','Create and approve placements'),
  (9,'placement.approve','Approve a proposed placement'),
  (10,'logbook.view.own','View own logbook entries'),
  (11,'logbook.view.all','View all logbook entries'),
  (12,'logbook.submit','Create and submit logbook entries'),
  (13,'logbook.review','Approve or return logbook entries'),
  (14,'logbook.comment','Comment on logbook entries'),
  (15,'evaluation.submit.supervisor','Complete a supervisor evaluation'),
  (16,'evaluation.submit.mentor','Complete a mentor evaluation'),
  (17,'evaluation.view','View evaluations'),
  (18,'visit.view','View site visits'),
  (19,'visit.manage','Schedule and complete site visits'),
  (20,'report.submit','Submit the internship report'),
  (21,'report.assess','Assess the internship report'),
  (22,'mark.view.own','View own published mark'),
  (23,'mark.view.all','View all marks'),
  (24,'mark.compute','Compute a grade sheet'),
  (25,'mark.moderate','Moderate a grade'),
  (26,'mark.publish','Publish grades'),
  (27,'kpi.view','View management indicators'),
  (28,'export.data','Export data to CSV'),
  (29,'org.view','View the host organisation register'),
  (30,'org.manage','Create and edit host organisations'),
  (31,'user.manage','Create, edit, suspend and unlock accounts'),
  (32,'role.view','View the role and permission matrix'),
  (33,'setting.manage','Change system settings'),
  (34,'audit.view','Read the audit log'),
  (35,'mail.manage','Inspect and resend queued mail'),
  (36,'notification.view','Use the notification centre'),
  (37,'diva.use','Ask DIVA and run guided document checks'),
  (38,'message.use','Send messages within a placement thread'),
  (39,'evaluation.submit.self','Complete the student self evaluation');

INSERT INTO role_permissions (role_id, permission_id) VALUES
  (1,1),(1,2),(1,3),(1,31),(1,32),(1,33),(1,34),(1,35),(1,29),(1,36),
  (2,1),(2,2),(2,3),(2,7),(2,8),(2,9),(2,11),(2,13),(2,14),(2,17),(2,18),(2,21),(2,23),(2,24),(2,25),(2,26),(2,27),(2,28),(2,29),(2,30),(2,36),
  (3,1),(3,2),(3,3),(3,6),(3,13),(3,14),(3,16),(3,17),(3,18),(3,19),(3,21),(3,36),
  (4,1),(4,2),(4,3),(4,6),(4,13),(4,14),(4,15),(4,17),(4,36),
  (5,1),(5,2),(5,3),(5,4),(5,5),(5,6),(5,10),(5,12),(5,14),(5,20),(5,22),(5,36),(5,39),
  (6,1),(6,2),(6,23),(6,27),(6,28),(6,36);

-- DIVA is available to every signed-in role; messaging is limited to the
-- academic triangle (student, mentor, coordinator).
INSERT INTO role_permissions (role_id, permission_id) VALUES
  (1,37),(2,37),(3,37),(4,37),(5,37),(6,37),
  (2,38),(3,38),(5,38);

INSERT INTO organisation (org_id, name, sector, address, district, contact_person, contact_email, contact_phone, mou_status, mou_expiry, capacity) VALUES
  (1,'Bright Future Mining Ltd','Mining and Engineering','12 Kissy Road, Bo','Bo','Mrs. Aminata Jalloh','partnerships@brightfuture.sl','+232 76 221 440','signed','2027-06-30',6),
  (2,'Sierra Telecoms','Telecommunications','5 Wilberforce Street, Freetown','Western Area Urban','Mr. Sorie Bangura','internships@sierratelecom.sl','+232 77 310 225','signed','2027-01-31',8),
  (3,'Rokel Commercial Bank','Banking and Finance','2 Siaka Stevens Street, Freetown','Western Area Urban','Ms. Hawa Sesay','hr@rokelbank.sl','+232 78 440 118','signed','2026-12-31',5),
  (4,'Leone District Health Office','Public Health','7 Hangha Road, Kenema','Kenema','Dr. Peter Vandi','admin@leonedho.sl','+232 76 505 902','pending',NULL,4),
  (5,'Freetown City Council','Public Administration','17 Wallace Johnson Street, Freetown','Western Area Urban','Mr. Alusine Turay','ict@fcc.gov.sl','+232 79 662 001','signed','2027-03-31',6),
  (6,'Koidu Holdings','Mining','Koidu Town, Kono','Kono','Ms. Fatmata Kallon','training@koiduholdings.sl','+232 76 780 344','expired','2025-08-31',3),
  (7,'Orange Sierra Leone','Telecommunications','Hill Station, Freetown','Western Area Urban','Mr. Ibrahim Mansaray','careers@orange.sl','+232 77 909 512','signed','2027-09-30',10),
  (8,'Njala University ICT Directorate','Education and Research','Njala Campus, Bo','Bo','Dr. Samuel Kposowa','ict@njala.edu.sl','+232 78 112 760','signed','2028-01-31',5),
  (9,'LEAP Acceleration Programme (Limkokwing)','University Enterprise','Limkokwing University, Freetown Campus','Western Area Urban','LEAP Internship Office','leap@limkokwing.edu.sl','+232 76 000 000','signed','2028-12-31',20);

-- Demo passwords (bcrypt, cost 10):
--   sesayshadrachsaidu@gmail.com  Shadrach@2026   (System Administrator)
--   bayohmusa001@gmail.com         Musa@2026       (LEAP Coordinator)
--   fatmatabfc@gmail.com           Fatmata@2026    (Management / AQA)
--   deborahjulietmoiforay@gmail.com  Mentor@1234    (Academic Mentor)
--   mentor2@leap.edu.sl              Mentor@1234
--   supervisor@brightfuture.sl       Super@1234
--   supervisor@sierratelecom.sl      Super@1234
--   mohamedalie247@gmail.com and the four other students  Student@1234

INSERT INTO users (user_id, role_id, organisation_id, first_name, last_name, email, phone, password_hash, status, must_change_password) VALUES
  (1,1,NULL,'Shadrach','Sesay','sesayshadrachsaidu@gmail.com','+232 76 100 001','$2y$10$VY86Y0xVjVE4wfp19W7w/eLlv6rm9TCdlKdgBpaUOxJdjIvhTCm6m','active',0),
  (2,2,NULL,'Musa','Bayoh','bayohmusa001@gmail.com','+232 76 100 002','$2y$10$mg5a07JRT08scDKHCiomd.tdRthiLfPyxlwDnCXrzF5Ba1bC6HPlC','active',0),
  (3,3,NULL,'Deborah','Moiforay','deborahjulietmoiforay@gmail.com','+232 76 100 003','$2y$10$N9tghSuDgH53KTbrPZ/lyuQMeqacNQzCXSKQVe4Rt7sHECl3k.Q3S','active',0),
  (4,6,NULL,'Fatmata','Conteh','fatmatabfc@gmail.com','+232 76 100 004','$2y$10$jo6JniY2SNg/LhQHX2b8juD1r/jZdCw1cmwvcvAf25uNIUF.9js9K','active',0),
  (5,4,1,'Joseph','Kargbo','supervisor@brightfuture.sl','+232 76 100 005','$2y$10$NWnGQhwhxbmkGfro2hkaN.00a.i.puQ7xo1HnWOr8asaQelN66FWG','active',0),
  (6,5,NULL,'Mohamed Alie','Kamara','mohamedalie247@gmail.com','+232 76 100 006','$2y$10$Wb9GBG1ZdsK5bUWEMwVbv.wxKBsViqQk7C3U16mMCr8bB0KDu2j3u','active',0),
  (7,5,NULL,'Aisha','Koroma','aisha.koroma@leap.edu.sl','+232 76 100 007','$2y$10$Wb9GBG1ZdsK5bUWEMwVbv.wxKBsViqQk7C3U16mMCr8bB0KDu2j3u','active',0),
  (8,5,NULL,'Ibrahim','Sesay','ibrahim.sesay@leap.edu.sl','+232 76 100 008','$2y$10$Wb9GBG1ZdsK5bUWEMwVbv.wxKBsViqQk7C3U16mMCr8bB0KDu2j3u','active',0),
  (9,5,NULL,'Maria','Kamara','maria.kamara@leap.edu.sl','+232 76 100 009','$2y$10$Wb9GBG1ZdsK5bUWEMwVbv.wxKBsViqQk7C3U16mMCr8bB0KDu2j3u','active',0),
  (10,5,NULL,'Mariama','Turay','mariama.turay@leap.edu.sl','+232 76 100 010','$2y$10$Wb9GBG1ZdsK5bUWEMwVbv.wxKBsViqQk7C3U16mMCr8bB0KDu2j3u','active',0),
  (11,3,NULL,'Samuel','Kposowa','mentor2@leap.edu.sl','+232 76 100 011','$2y$10$N9tghSuDgH53KTbrPZ/lyuQMeqacNQzCXSKQVe4Rt7sHECl3k.Q3S','active',0),
  (12,4,2,'Hawa','Sesay','supervisor@sierratelecom.sl','+232 76 100 012','$2y$10$NWnGQhwhxbmkGfro2hkaN.00a.i.puQ7xo1HnWOr8asaQelN66FWG','active',0);

INSERT INTO student (student_id, user_id, reg_no, programme, level, semester, academic_year, cgpa, status) VALUES
  (1,6,'905005581','BSc (Hons) Software Engineering with Multimedia','Year 3','Semester 5','2026/2027',3.42,'on_placement'),
  (2,7,'905005612','BSc (Hons) Business Information Technology','Year 3','Semester 5','2026/2027',3.68,'on_placement'),
  (3,8,'905005618','BSc (Hons) Software Engineering with Multimedia','Year 3','Semester 5','2026/2027',3.15,'active'),
  (4,9,'905005624','BSc (Hons) Business Information Technology','Year 3','Semester 5','2026/2027',3.51,'active'),
  (5,10,'905005630','BSc (Hons) Software Engineering with Multimedia','Year 3','Semester 5','2026/2027',2.94,'active');

INSERT INTO application (student_id, org_id, preference_rank, motivation, status, academic_year, submitted_at) VALUES
  (1,1,1,'I want to apply my software engineering skills to the digital transformation work at Bright Future Mining.','allocated','2026/2027','2026-08-17 09:15:00'),
  (1,2,2,'Sierra Telecoms offers the largest network engineering team in the country.','rejected','2026/2027','2026-08-17 09:15:00'),
  (1,7,3,'Orange Sierra Leone has a strong graduate development programme.','rejected','2026/2027','2026-08-17 09:15:00'),
  (2,2,1,'I am interested in network operations and customer systems.','allocated','2026/2027','2026-08-18 10:20:00'),
  (2,7,2,'Orange has a large technology division in Freetown.','rejected','2026/2027','2026-08-18 10:20:00'),
  (3,3,1,'Banking systems and payment security are my main interests.','allocated','2026/2027','2026-08-19 11:05:00'),
  (3,5,2,'I would like to contribute to public service digitalisation.','rejected','2026/2027','2026-08-19 11:05:00'),
  (4,4,1,'Health information systems are an area I want to specialise in.','submitted','2026/2027','2026-08-20 08:40:00'),
  (5,5,1,'Local government digital records management interests me.','allocated','2026/2027','2026-08-21 14:10:00');

INSERT INTO placement (placement_id, student_id, org_id, coordinator_id, mentor_id, supervisor_id, start_date, end_date, total_weeks, academic_year, status, approved_by, approved_at, career_fair_score) VALUES
  (1,1,1,2,3,5,'2026-08-31','2026-11-20',12,'2026/2027','active',2,'2026-08-24 11:00:00',82.00),
  (2,2,2,2,11,12,'2026-08-31','2026-11-20',12,'2026/2027','active',2,'2026-08-24 11:10:00',76.00),
  (3,3,3,2,3,NULL,'2027-01-11','2027-04-02',12,'2026/2027','proposed',NULL,NULL,NULL),
  (4,4,4,2,11,5,'2025-09-08','2025-11-28',12,'2025/2026','completed',2,'2025-09-01 09:30:00',85.00),
  (5,5,5,2,3,NULL,'2027-01-11','2027-04-02',12,'2026/2027','proposed',NULL,NULL,NULL);

-- Twelve weeks of logbook history for placement 1 (mid-semester).
INSERT INTO logbook_entry (entry_id, placement_id, week_no, date_from, date_to, activities, hours, status, submitted_at, reviewed_by, reviewed_at) VALUES
  (1,1,1,'2026-08-31','2026-09-04','Induction and orientation. Met the IT team, received my workstation and reviewed the internship handbook.','37.5','approved','2026-09-04 16:30:00',5,'2026-09-07 09:10:00'),
  (2,1,2,'2026-09-07','2026-09-11','Installed the development environment and shadowed the network administrator during daily checks.','40.0','approved','2026-09-11 17:00:00',5,'2026-09-14 08:45:00'),
  (3,1,3,'2026-09-14','2026-09-18','Documented the existing asset register and identified missing serial numbers for twelve machines.','38.0','approved','2026-09-18 16:45:00',5,'2026-09-21 09:05:00'),
  (4,1,4,'2026-09-21','2026-09-25','Built a small inventory tracking sheet and tested it with the stores officer.','40.0','approved','2026-09-25 17:10:00',5,'2026-09-28 08:55:00'),
  (5,1,5,'2026-09-28','2026-10-02','Assisted with user support tickets and resolved nine password reset requests under supervision.','39.0','approved','2026-10-02 16:20:00',5,'2026-10-05 09:00:00'),
  (6,1,6,'2026-10-05','2026-10-09','Migrated the inventory sheet into a small web form and presented it to the IT manager.','40.0','approved','2026-10-09 17:05:00',5,'2026-10-12 08:40:00'),
  (7,1,7,'2026-10-12','2026-10-16','Added validation to the web form and wrote a short user guide for the stores team.','38.5','approved','2026-10-16 16:35:00',5,'2026-10-19 09:15:00'),
  (8,1,8,'2026-10-19','2026-10-23','Tested the form with three users and recorded their feedback for the next iteration.','40.0','approved','2026-10-23 17:00:00',5,'2026-10-26 08:50:00'),
  (9,1,9,'2026-10-26','2026-10-30','Started the reporting module. The week was cut short by a public holiday, affecting planned output.','32.0','returned','2026-10-30 16:40:00',5,'2026-11-02 09:20:00'),
  (10,1,10,'2026-11-02','2026-11-06','Completed the reporting module draft and began integration testing with sample data.','39.5','returned','2026-11-06 16:55:00',5,'2026-11-09 09:05:00'),
  (11,1,11,'2026-11-09','2026-11-13','Fixed the integration issues, added export to CSV and demonstrated it to the supervisor.','40.0','submitted','2026-11-13 12:20:00',NULL,NULL),
  (12,1,12,'2026-11-16','2026-11-20','Preparing the final handover documentation and closing tasks.',NULL,'draft',NULL,NULL,NULL);

-- Weekly sections from the log book form (guidebook pages 19-20).
UPDATE logbook_entry SET
  technical_skills = 'Basic network health checks and how to read a server room checklist.',
  challenges = 'Understanding the asset register structure without prior exposure to inventory records.',
  meeting_notes = 'Mon 31 Aug: induction with the IT manager; agreed the workstation and reporting line.',
  reflection = 'A calm first week. I need to ask more specific questions rather than waiting to be told.'
WHERE entry_id = 1;

UPDATE logbook_entry SET
  technical_skills = 'Installing a local PHP and MySQL stack; reading backup logs.',
  challenges = 'The backup routine differs from the handbook, so I confirmed the real schedule with the administrator.',
  meeting_notes = 'Fri 11 Sep: short review with the network administrator on what to practise next.',
  reflection = 'Shadowing showed how much of the role is routine checks rather than repairs.'
WHERE entry_id = 2;

UPDATE logbook_entry SET
  technical_skills = 'Reconciling two data sources and spotting duplicate or missing records.',
  challenges = 'Twelve machines had no serial number and the invoices were filed by supplier rather than by date.',
  meeting_notes = 'Fri 18 Sep: agreed the correction process with the stores officer.',
  reflection = 'Accuracy matters more than speed; I slowed down and the errors fell.'
WHERE entry_id = 3;

UPDATE logbook_entry SET
  technical_skills = 'Spreadsheet validation lists, lookup formulas and stock movement logic.',
  challenges = 'Designing a sheet that non-technical staff could use without training.',
  meeting_notes = 'Thu 24 Sep: walkthrough with the stores officer and feedback on the column names.',
  reflection = 'A simpler design beat a complete one; I removed two features after the walkthrough.'
WHERE entry_id = 4;

UPDATE logbook_entry SET
  technical_skills = 'Helpdesk triage, identity verification before password resets, ticket logging.',
  challenges = 'Handling several requests at once while keeping the identity checks strict.',
  meeting_notes = 'Fri 2 Oct: ticket review with the supervisor; printer faults recurred three times.',
  reflection = 'Most tickets were the same three problems, which points to a training need.'
WHERE entry_id = 5;

UPDATE logbook_entry SET
  technical_skills = 'PHP forms, PDO inserts, search pages and basic server configuration.',
  challenges = 'Moving from a spreadsheet to a database meant deciding what a record actually is.',
  meeting_notes = 'Thu 8 Oct: presented the prototype to the IT manager and agreed the next requirements.',
  reflection = 'Presenting early was uncomfortable, but it changed the design for the better.'
WHERE entry_id = 6;

UPDATE logbook_entry SET
  technical_skills = 'Server-side validation, input sanitising and writing end-user documentation.',
  challenges = 'Testing my own work fairly; I kept choosing inputs that I knew would pass.',
  meeting_notes = 'Fri 16 Oct: handed the user guide draft to the IT manager for review.',
  reflection = 'Writing the guide exposed gaps in the form that I had not noticed.'
WHERE entry_id = 7;

UPDATE logbook_entry SET
  technical_skills = 'Structured usability feedback, prioritising fixes and printable views.',
  challenges = 'Two users entered dates in different formats and the form accepted both.',
  meeting_notes = 'Fri 23 Oct: feedback review with the stores team and agreed order of fixes.',
  reflection = 'Real users find problems that my own testing never would.'
WHERE entry_id = 8;

UPDATE logbook_entry SET
  technical_skills = 'Report queries with grouping and reconciliation of totals.',
  challenges = 'The public holiday cost a day and two planned tasks had to move to the next week.',
  meeting_notes = 'Fri 30 Oct: the supervisor asked for the dropped tasks to be listed explicitly.',
  reflection = 'The entry was returned because it did not say what was dropped; I now state that.'
WHERE entry_id = 9;

UPDATE logbook_entry SET
  technical_skills = 'Integration testing, reading failure logs and correcting query errors.',
  challenges = 'Two errors only appeared on records with no movement in the report period.',
  meeting_notes = 'Fri 6 Nov: testing review with the mentor, who asked for the test cases in the entry.',
  reflection = 'The mentor wants evidence, not a summary; I will attach the cases next time.'
WHERE entry_id = 10;

UPDATE logbook_entry SET
  technical_skills = 'CSV export, edge-case validation and demonstrating to a non-technical audience.',
  challenges = 'Exporting correct totals when a report period contained no stock movements.',
  meeting_notes = 'Thu 12 Nov: demonstrated the finished module to the host supervisor.',
  reflection = 'The work is ready for handover; the documentation still needs tightening.'
WHERE entry_id = 11;

-- Daily records: one row per working day, matching the paper log book grid.
INSERT INTO logbook_day (entry_id, work_date, tasks, hours) VALUES
  (1,'2026-08-31','Induction briefing with HR; issued staff ID and workstation.',7.5),
  (1,'2026-09-01','Met the IT team; reviewed the internship handbook and site rules.',7.5),
  (1,'2026-09-02','Observed the morning network and server health checks.',7.5),
  (1,'2026-09-03','Set up email and shared drive access with the systems administrator.',7.5),
  (1,'2026-09-04','Read the asset register structure and noted questions for the stores officer.',7.5),
  (2,'2026-09-07','Installed the development environment (VS Code, XAMPP) on the assigned laptop.',8.0),
  (2,'2026-09-08','Shadowed the network administrator during the daily backup check.',8.0),
  (2,'2026-09-09','Practised SQL queries against a test copy of the inventory database.',8.0),
  (2,'2026-09-10','Attended the weekly IT stand-up and recorded the action points.',8.0),
  (2,'2026-09-11','Wrote notes on the network layout for my own reference.',8.0),
  (3,'2026-09-14','Began auditing the asset register against the stores records.',7.5),
  (3,'2026-09-15','Checked forty machines and logged twelve missing serial numbers.',7.5),
  (3,'2026-09-16','Cross-checked procurement invoices to recover the missing details.',7.5),
  (3,'2026-09-17','Prepared a list of discrepancies for the stores officer.',7.5),
  (3,'2026-09-18','Submitted the discrepancy list and agreed the correction process.',8.0),
  (4,'2026-09-21','Drafted the inventory tracking sheet with validation lists.',8.0),
  (4,'2026-09-22','Added formulas for stock movement and remaining quantities.',8.0),
  (4,'2026-09-23','Tested the sheet with five sample stock movements.',8.0),
  (4,'2026-09-24','Walked the stores officer through the sheet and took feedback.',8.0),
  (4,'2026-09-25','Applied the feedback: renamed columns and added a summary tab.',8.0),
  (5,'2026-09-28','Joined the helpdesk queue under supervision and handled three tickets.',8.0),
  (5,'2026-09-29','Resolved four password reset requests following the identity check procedure.',8.0),
  (5,'2026-09-30','Assisted a user with a printer mapping problem.',8.0),
  (5,'2026-10-01','Logged a hardware fault and escalated it with the serial number recorded.',8.0),
  (5,'2026-10-02','Reviewed the closed tickets with the supervisor and noted recurring issues.',7.0),
  (6,'2026-10-05','Recreated the inventory sheet as a small PHP form on the test server.',8.0),
  (6,'2026-10-06','Connected the form to a MySQL table and tested inserts.',8.0),
  (6,'2026-10-07','Added a search page for the stores team.',8.0),
  (6,'2026-10-08','Presented the working prototype to the IT manager.',8.0),
  (6,'2026-10-09','Documented the remaining requirements from the presentation.',8.0),
  (7,'2026-10-12','Added server-side validation to every field on the form.',7.5),
  (7,'2026-10-13','Wrote the first draft of a short user guide for the stores team.',7.5),
  (7,'2026-10-14','Tested validation with empty and invalid submissions.',8.0),
  (7,'2026-10-15','Corrected two bugs found in testing with the stores officer.',7.5),
  (7,'2026-10-16','Handed the user guide draft to the IT manager for review.',8.0),
  (8,'2026-10-19','Ran the form with three users and recorded their comments.',8.0),
  (8,'2026-10-20','Grouped the feedback into usability and data-quality issues.',8.0),
  (8,'2026-10-21','Fixed the date entry problem reported by two users.',8.0),
  (8,'2026-10-22','Added a printable stock list page.',8.0),
  (8,'2026-10-23','Presented the revised form and the feedback log.',8.0),
  (9,'2026-10-26','Started the reporting module: daily stock movement report.',8.0),
  (9,'2026-10-27','Built the report query and checked the totals against the spreadsheet.',8.0),
  (9,'2026-10-29','Returned after the public holiday; adjusted the report layout.',8.0),
  (9,'2026-10-30','Showed the draft to the supervisor; two planned tasks could not be completed.',8.0),
  (10,'2026-11-02','Completed the reporting module draft with date filters.',8.0),
  (10,'2026-11-03','Prepared sample data for integration testing.',8.0),
  (10,'2026-11-04','Ran the first integration tests and logged the failures.',8.0),
  (10,'2026-11-05','Fixed two query errors found in testing.',8.0),
  (10,'2026-11-06','Re-ran the tests and recorded the results in the test log.',7.5),
  (11,'2026-11-09','Fixed the remaining integration issues.',8.0),
  (11,'2026-11-10','Added export to CSV on both list pages.',8.0),
  (11,'2026-11-11','Tested the CSV export with the stores officer.',8.0),
  (11,'2026-11-12','Demonstrated the finished module to the supervisor.',8.0),
  (11,'2026-11-13','Updated the user guide and closed the open issues.',8.0);

INSERT INTO logbook_comment (comment_id, entry_id, user_id, parent_id, body, created_at) VALUES
  (1,9,5,NULL,'The entry does not explain which planned tasks were dropped. Please add that and resubmit.','2026-11-02 09:20:00'),
  (2,9,6,1,'Understood. I have listed the two dropped tasks and the reason in the revised entry.','2026-11-03 10:05:00'),
  (3,10,5,NULL,'Good progress, but the testing evidence is thin. Add the test cases you ran.','2026-11-09 09:05:00'),
  (4,11,3,NULL,'Your logbook is well written. Remember to reference these weeks in the final report.','2026-11-13 13:10:00');

INSERT INTO criterion (criterion_id, evaluation_type, name, max_score, weight, sort_order) VALUES
  (1,'supervisor','Punctuality',5,15.00,1),
  (2,'supervisor','Technical competence',5,25.00,2),
  (3,'supervisor','Communication',5,20.00,3),
  (4,'supervisor','Teamwork',5,15.00,4),
  (5,'supervisor','Initiative',5,10.00,5),
  (6,'supervisor','Professionalism',5,15.00,6),
  (7,'mentor','Academic application',10,40.00,1),
  (8,'mentor','Report quality',10,35.00,2),
  (9,'mentor','Reflective learning',10,25.00,3),
  (10,'report','Structure',10,25.00,1),
  (11,'report','Analysis',10,35.00,2),
  (12,'report','Evidence',10,25.00,3),
  (13,'report','Presentation',10,15.00,4),
  (14,'self','Learning gained',5,35.00,1),
  (15,'self','Skills developed',5,30.00,2),
  (16,'self','Professional conduct',5,20.00,3),
  (17,'self','Reflection on goals',5,15.00,4);

INSERT INTO evaluation (evaluation_id, placement_id, evaluator_id, evaluation_type, status, total_score, comments) VALUES
  (1,1,5,'supervisor','draft',NULL,'Consistent performer, strong on documentation.'),
  (2,2,12,'supervisor','submitted',72.50,'Reliable and quick to learn the network tools.'),
  (3,2,11,'mentor','submitted',76.50,'The report shows sound academic grounding and regular reflection.'),
  (4,1,3,'mentor','submitted',76.50,'Steady academic progress; the report integrates the logbook well.'),
  (5,2,7,'self','submitted',80.00,'I grew most in troubleshooting and explaining my work to colleagues.'),
  (6,4,9,'self','submitted',80.00,'The placement confirmed the career direction I wanted and improved my confidence.'),
  (7,1,6,'self','submitted',80.00,'I learnt to work to a schedule and to ask for help early.');

INSERT INTO evaluation_score (evaluation_id, criterion_id, score, comment) VALUES
  (1,1,4,'Always on time'),
  (1,2,4,'Good grasp of the ticketing system'),
  (1,3,3,'Needs to be more confident when explaining work'),
  (1,4,5,'Works well with the stores and support teams'),
  (1,5,4,'Took the initiative on the inventory form'),
  (2,1,4,'Punctual throughout'),
  (2,2,4,'Strong troubleshooting'),
  (2,3,3,'Written updates could be clearer'),
  (2,4,4,'Good team player'),
  (2,5,3,'Steady but rarely proposes improvements'),
  (2,6,4,'Professional with clients'),
  (3,7,8,'Applies theory to the placement tasks'),
  (3,8,7,'Clear structure, some analysis could go deeper'),
  (3,9,8,'Reflects honestly on mistakes and learning'),
  (4,7,8,'Consistently connects coursework to practice'),
  (4,8,7,'Report is well organised and evidenced'),
  (4,9,8,'Strong reflective entries in the later weeks'),
  (5,14,4,'Learnt how a real IT support desk runs'),
  (5,15,4,'Troubleshooting and documentation both improved'),
  (5,16,4,'Kept to the same professional standards as staff'),
  (5,17,4,'Clear next steps for my final year'),
  (6,14,4,'Connected the placement to my programme'),
  (6,15,4,'Confident presenting to a client'),
  (6,16,4,'Reliable attendance and conduct'),
  (6,17,4,'Chose a specialism for my career'),
  (7,14,4,'Linked the tasks to my database course'),
  (7,15,4,'Better at documenting what I build'),
  (7,16,4,'Punctual and professional with staff'),
  (7,17,4,'Clearer about the career I want');

INSERT INTO document (doc_id, doc_type, student_id, placement_id, original_name, stored_name, mime_type, file_size, uploaded_by, uploaded_at) VALUES
  (1,'cv',1,NULL,'Mohamed_Alie_Kamara_CV.pdf','demo_cv_mohamed.pdf','application/pdf',1402,6,'2026-08-16 14:00:00'),
  (2,'internship_report',NULL,2,'Ibrahim_Sesay_Internship_Report.pdf','demo_report_ibrahim.pdf','application/pdf',1662,7,'2026-11-10 09:15:00'),
  (3,'internship_report',NULL,1,'Mohamed_Alie_Kamara_Internship_Report.pdf','demo_report_mohamed.pdf','application/pdf',1652,6,'2026-11-12 08:40:00'),
  (4,'career_fair_report',NULL,1,'Mohamed_Alie_Kamara_Career_Fair_2026_Report.pdf','b0555b3d7ba39307c44d5158e0f1f5f2.pdf','application/pdf',2916,6,'2026-11-10 15:30:00');

INSERT INTO internship_report (report_id, placement_id, document_id, submitted_at, status, assessed_by, assessed_at, score, comments) VALUES
  (1,2,2,'2026-11-10 09:15:00','assessed',11,'2026-11-11 10:00:00',78.00,'Well structured report. The analysis chapter is strong; add more primary evidence from the logbook.'),
  (2,1,3,'2026-11-12 08:40:00','assessed',3,'2026-11-12 16:20:00',76.00,'Clear account of the placement. Improve the evaluation chapter by comparing planned and actual outcomes.');

INSERT INTO grade_scale (grade_id, grade_letter, min_score, max_score, points, classification) VALUES
  (1,'A+',90.00,100.00,4.00,'Distinction'),
  (2,'A',80.00,89.99,3.75,'Excellent'),
  (3,'B+',75.00,79.99,3.50,'Very good'),
  (4,'B',70.00,74.99,3.25,'Good'),
  (5,'C+',65.00,69.99,3.00,'Credit'),
  (6,'C',60.00,64.99,2.75,'Pass'),
  (7,'D',50.00,59.99,2.00,'Marginal pass'),
  (8,'F',0.00,49.99,0.00,'Fail');

-- Only the completed 2025/2026 placement carries a mark. The two 2026/2027
-- placements are mid-semester, so no mark row exists for them until the
-- coordinator computes one from the completed components.
INSERT INTO mark (mark_id, placement_id, logbook_pct, supervisor_pct, self_pct, mentor_pct, practical_pct, report_pct, career_fair_pct, weighted_total, grade_id, status, computed_by, computed_at, moderated_by, moderated_at, moderation_reason, published_at) VALUES
  (3,4,74.00,68.00,80.00,NULL,72.20,72.00,85.00,72.98,4,'published',2,'2025-12-10 10:00:00',2,'2025-12-12 09:00:00','Moderated after external examiner review','2025-12-15 10:00:00');

INSERT INTO site_visit (visit_id, placement_id, mentor_id, scheduled_date, mode, actual_date, findings, recommendations, status, completed_at) VALUES
  (1,1,3,'2026-10-12','physical','2026-10-12','Student has settled well. Supervisor reports good progress on the inventory tool.','Continue weekly logbook reviews and start the report outline early.','completed','2026-10-12 15:30:00'),
  (2,1,3,'2026-11-18','virtual',NULL,NULL,NULL,'scheduled',NULL),
  (3,2,11,'2026-11-19','physical',NULL,NULL,NULL,'scheduled',NULL);

INSERT INTO notification (notification_id, user_id, type, title, body, link_url, is_read, created_at) VALUES
  (1,6,'placement.approved','Your placement has been approved','Bright Future Mining Ltd, 31 August to 20 November 2026.','index.php?r=student/placement',1,'2026-08-24 11:05:00'),
  (2,6,'logbook.returned','Week 9 logbook returned','Please add the tasks that were dropped and resubmit.','index.php?r=student/logbook/view&id=9',1,'2026-11-02 09:20:00'),
  (3,6,'visit.scheduled','Site visit scheduled','A virtual site visit is scheduled for 18 November 2026.','index.php?r=student/placement',0,'2026-11-09 09:00:00'),
  (4,5,'logbook.submitted','Week 11 logbook awaiting review','Mohamed Alie Kamara submitted week 11.','index.php?r=supervisor/entry&id=11',0,'2026-11-13 12:20:00'),
  (5,3,'visit.due','Site visit this week','Virtual visit for Mohamed Alie Kamara on 18 November.','index.php?r=mentor/visits',0,'2026-11-13 08:00:00');

INSERT INTO email_queue (queue_id, user_id, notification_id, to_email, subject, body, status, attempts, scheduled_at) VALUES
  (1,5,'4','supervisor@brightfuture.sl','Week 11 logbook awaiting your review','Mohamed Alie Kamara submitted week 11 of the logbook.','queued',0,'2026-11-13 12:20:00');

INSERT INTO audit_log (audit_id, user_id, action, entity_type, entity_id, before_json, after_json, ip_address, user_agent, created_at) VALUES
  (1,2,'placement.approve','placement',1,NULL,'{"status":"approved"}','127.0.0.1','Seed','2026-08-24 11:00:00'),
  (2,5,'logbook.approve','logbook_entry',1,NULL,'{"status":"approved"}','127.0.0.1','Seed','2026-09-07 09:10:00'),
  (3,5,'logbook.return','logbook_entry',9,'{"status":"submitted"}','{"status":"returned"}','127.0.0.1','Seed','2026-11-02 09:20:00'),
  (4,3,'visit.complete','site_visit',1,NULL,'{"status":"completed"}','127.0.0.1','Seed','2026-10-12 15:30:00'),
  (5,2,'mark.publish','mark',3,'{"status":"draft"}','{"status":"published"}','127.0.0.1','Seed','2025-12-15 10:00:00');

INSERT INTO setting (setting_key, setting_value, data_type, description) VALUES
  ('app_name','Digital LEAP Internship Management System','string','Name shown in the header and emails'),
  ('institution','Limkokwing University, Sierra Leone','string','Institution name used on letters and reports'),
  ('logbook_due_day','Friday','string','Day of the week by which a student should submit'),
  ('reminder_lead_days','2','int','Days before the due day to send a reminder'),
  ('weight_logbook','50','decimal','Weight of the logbook inside Practical Internship'),
  ('weight_supervisor','40','decimal','Weight of the Form D supervisor evaluation inside Practical Internship'),
  ('weight_self','10','decimal','Weight of the student self evaluation inside Practical Internship'),
  ('weight_mentor','0','decimal','Advisory only: the mentor evaluation is not part of the final mark'),
  ('weight_report','75','decimal','Credit weight of the Internship Report module out of 200'),
  ('weight_career_fair','25','decimal','Credit weight of the Career Fair module out of 200'),
  ('session_idle_minutes','20','int','Idle minutes before a session expires'),
  ('max_login_attempts','5','int','Failed logins before the account locks'),
  ('lockout_minutes','15','int','Minutes an account stays locked'),
  ('mail_mode','log','string','log writes mail to storage/logs/mail.log; smtp sends it');

INSERT INTO feedback (feedback_id, user_id, rating, thoughts, suggestions, page, created_at) VALUES
  (1,6,5,'The logbook flow is clear and the dashboard tells me exactly what to do next.','Add a reminder on my phone when a week is due.','dashboard','2026-11-10 14:20:00'),
  (2,7,4,'Reviewing entries is quick and the comments keep everyone aligned.','Let me filter the queue by week number.','supervisor/queue','2026-11-11 09:05:00'),
  (3,5,5,'Approving placements and seeing the audit trail saves a lot of time.','A printable weekly summary for the office notice board would help.','coordinator/placements','2026-11-12 16:40:00'),
  (4,8,3,'The system works well on my laptop but the tables feel cramped on my phone.','Make the tables easier to read on small screens.','student/logbook','2026-11-12 18:15:00');

-- Placement thread between the seeded student and the academic mentor.
INSERT INTO conversation (conversation_id, placement_id, subject, topic, created_by, last_message_at) VALUES
  (1,1,'Placement supervision','general',6,'2026-11-14 09:40:00');

INSERT INTO conversation_member (conversation_id, user_id, member_role, last_read_message_id) VALUES
  (1,6,'owner',4),
  (1,3,'member',5);

INSERT INTO message (message_id, conversation_id, sender_id, parent_message_id, kind, body, created_at) VALUES
  (1,1,NULL,NULL,'system','Conversation opened. Career Fair 2026 report deadline: Wednesday 11 November 2026, 12:00.','2026-11-03 09:00:00'),
  (2,1,6,NULL,'text','Good morning Madam Moiforay. I have submitted week 11 of my logbook and I am starting the final report.','2026-11-13 12:30:00'),
  (3,1,3,2,'text','I have seen week 11. Keep the evidence for the reporting module; you will need it in the report.','2026-11-13 14:05:00'),
  (4,1,6,NULL,'text','Noted. Could you confirm the report deadline?','2026-11-14 08:10:00'),
  (5,1,3,4,'text','The report is due at the end of the placement, 20 November. Upload it through the Internship Report page.','2026-11-14 09:40:00');

INSERT INTO message_reaction (message_id, user_id, emoji) VALUES
  (5,6,'👍');

-- Contact addresses for two group members. Idempotent: the update only runs
-- when the stored address differs, so re-running this file on an existing
-- database is safe and never overwrites a member's own later change.
UPDATE users SET email = 'deborahjulietmoiforay@gmail.com' WHERE user_id = 3 AND email <> 'deborahjulietmoiforay@gmail.com';
UPDATE users SET email = 'mohamedalie247@gmail.com' WHERE user_id = 6 AND email <> 'mohamedalie247@gmail.com';
