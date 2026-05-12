-- SQL-Skript zur Einrichtung der Datenbank für die Ausleihverwaltung

-- Tabelle für Benutzerprofile
CREATE TABLE IF NOT EXISTS benutzer (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nachname    VARCHAR(100) NOT NULL,
  vorname     VARCHAR(100) NOT NULL,
  rolle       ENUM('Admin', 'Ausleihe') NOT NULL DEFAULT 'Ausleihe',
  email       VARCHAR(255) NOT NULL UNIQUE,
  passwort    VARCHAR(255) NOT NULL,  -- bcrypt-Hash
  ort         VARCHAR(150) NOT NULL,
  erstellt_am DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
