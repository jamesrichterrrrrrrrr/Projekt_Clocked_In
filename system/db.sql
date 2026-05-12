-- Siehe projektroot setup.sql (identisches Schema für Deployment-Referenz)

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(255) NOT NULL,
  password      VARCHAR(255) NOT NULL,
  firstname     VARCHAR(100) NULL,
  lastname      VARCHAR(100) NULL,
  app_role      VARCHAR(32)  NULL DEFAULT 'user',
  job_title     VARCHAR(100) NULL,
  location_id   INT UNSIGNED NULL,
  card_id       VARCHAR(64)  NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_users_email (email),
  UNIQUE KEY uk_users_card_id (card_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
