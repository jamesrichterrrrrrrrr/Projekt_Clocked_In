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

CREATE TABLE IF NOT EXISTS arbeitszeiten (
  id              INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id         INT(10) UNSIGNED DEFAULT NULL,
  zeitstempel     TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  mitarbeiter     VARCHAR(50) DEFAULT NULL,
  aktion          VARCHAR(20) DEFAULT NULL,
  dauer_sekunden  INT(11) DEFAULT NULL,
  uid             VARCHAR(24) NOT NULL,
  KEY fk_arbeitszeiten_user (user_id),
  CONSTRAINT fk_arbeitszeiten_user FOREIGN KEY (user_id) REFERENCES users (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
