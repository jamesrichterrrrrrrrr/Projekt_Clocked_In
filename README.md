# Clocked-In

## Kurzbeschreibung des Projekts

* **Modul:** Interaktive Medien IV an der Fachhochschule Graubünden (FS26)  
* **Themenfeld:** IoT-Applikation zum Thema Eltern mit kleinen Kindern  
* **Name des Projekts:** Clocked-In
* **Team Physical Computing:** Len Wilpert & Nils Kindlimann
* **Team WebApp:** Jule Buchmann & James Richter

Clocked-In ist eine webbasierte Zeiterfassungsanwendung für die Technikauslei- ähhh Kita. Mitarbeitende können ihre Arbeitszeiten digital erfassen, laufende Tätigkeiten dokumentieren und ihre geleisteten Stunden einsehen. Zusätzlich unterstützt das System die Anmeldung per NFC-Karte über einen ESP32-Mikrocontroller.

Das Projekt wurde im Rahmen des Moduls Interaktive Medien 4 entwickelt und verbindet Webentwicklung, Datenbankintegration, Benutzerverwaltung und Physical Computing.
 
* **WebApp:** https://im4.nkstudios.ch/
* **Video-Dokumentation:** https://youtu.be/9lD8mhhMwU8

## UX & Konzeption

*In diesem Teil werden die gemeinsamen Schritte aus der UX-Abgabe dokumentiert, damit sich hier alles vollständig an einem Ort befindet (betrifft WebApp und Physical Computing)*

### Figma
[Link zum Figma](https://www.figma.com/design/cZT0irT6QBE3z2AkaFgU50/IM-4-%E2%80%93-App-Konzeption-Nils-Len-James-Jule?node-id=78-325&t=YV1OdTmVDKRQVCaU-1)

### User Flow \+ Screen Flow
  <img width="1000" height="954" alt="Screenshot 2026-06-12 at 11 28 22" src="https://github.com/user-attachments/assets/f7007292-49ca-49af-83c4-5ad53be62e9f" />

### Mock-Up
<img width="1000" height="740" alt="Preview Mockup" src="https://github.com/user-attachments/assets/d8c6c373-c48a-4200-bcd4-1114597648c7" />

#### Geplante Kernfeatures (Von Beginn an angedacht):

*Mitarbeitende*
* Registrierung und Login
* Zeiterfassung per Clock In / Clock Out
* Auswahl verschiedener Tätigkeiten
* ⁠Anzeige der aktuell geleisteten Arbeitszeit
* Übersicht über erfasste Arbeitsstunden
* ⁠Persönliche Einstellungen
* Direkt gebrauchsfertige PDF-Exports.

*Administration*
* Benutzerverwaltung
* ⁠Erstellung neuer Benutzer*innen
* Rollenverwaltung
* Standortverwaltung

#### Spontane Erweiterungen (Während der Entwicklung implementiert):
* Dark Mode: Für eine angenehmere Nutzung bei unterschiedlichen Lichtverhältnissen.
* Arbeitszeitlimiten: Zur besseren Kontrolle und Einhaltung von maximalen Arbeitszeiten.

### Physisches Terminal
Für das physische Terminal lag der Fokus auf sofortigem, klarem Audio-Visuellen-Feedback.

* **Check-In:** Rotes Lauflicht (NeoPixel), kurzes Piepen, Begrüssung auf dem Display.
* **Check-Out:** Grünes Lauflicht, langes Piepen, Verabschiedung inkl. Aufenthaltsdauer.
* **Unbekannte Karte:** Blaues Licht, Doppel-Pieps, Aufforderung zur Web-Zuweisung.
* **Fehler:** Rotes Blinken und Piepen.

## Setup

### Voraussetzungen

#### Hardware:
* ESP32-C6 Development Board
* PN532 NFC RFID Module V3 (I2C)
* 0.96" OLED Display I2C (SSD1306)
* 12-Bit WS2812B RGB LED Ring (NeoPixel)
* Active Piezo Buzzer
* Elektrolytkondensator (1000 µF) zur Spannungsglättung des LED-Rings
* MIFARE Classic NFC-Karten/Tags

#### Software:
* Arduino IDE (mit installiertem ESP32 Board-Manager)
* Libraries: Adafruit_PN532, Adafruit_SSD1306, Adafruit_NeoPixel, Arduino_JSON
* 2.4 GHz WLAN (z.B. Hotspot mit maximierter Kompatibilität)

### Installationsanleitung WebApp

### 1. Was benötige ich an Infrastruktur?

| Komponente | Anforderung |
|------------|------------|
| **Webhosting** | Apache/Nginx mit **PHP 8.x**, PDO/MySQL, HTTPS |
| **Datenbank** | MySQL/MariaDB (z. B. Hostpoint, Infomaniak) |
| **Domain** | z. B. `im4.nkstudios.ch` → Document Root `/web` |
| **Optional lokal** | PHP CLI (`php -S`), Node/npm nur für `npm run dev` |
| **Hardware** | ESP32-C6 + PN532 NFC-Reader, WLAN 2,4 GHz |

Kein Node.js-Server in Produktion nötig – statische HTML/JS/CSS + PHP-API.

---

### 2. Was muss ich auf meinem Webserver installieren?

Auf dem Hosting reicht standardmässig:

- **PHP 8.0+** mit Extensions: `pdo_mysql`, `json`, `session`
- **MySQL/MariaDB** (Datenbank beim Hoster anlegen)
- **Apache** mit `.htaccess`-Unterstützung (optional für `SetEnv`)

**Nicht** auf den Server hochladen:

- `.git/`, `.vscode/`, `node_modules/`, `hardware/`, `scripts/` (SQL lokal ausführen)
- `system/config.php` (Secrets – nur manuell auf dem Server anlegen)

**Hochladen** (Inhalt des Projektordners bzw. `dist/`):

```
index.html, login.html, settings.html, stundenuebersicht.html,
admindashboard.html, newuser.html, register.html, …
css/  js/  svgs/  api/  system/
```

Alles direkt ins Webroot (`/web` bei Hostpoint/Infomaniak), **nicht** in einen Unterordner wie `/clockedin/` – ausser die Domain soll bewusst unter `/unterordner/` laufen.

---

### 3. Wie kann ich die Datenbank importieren?

**Schritt 1 – Tabellen anlegen** (einmalig):

In phpMyAdmin → Datenbank wählen → Reiter **SQL** → Inhalt von [`setup.sql`](setup.sql) einfügen → Ausführen.

Erstellt:

- `users` – Benutzer, Rollen, Standort, NFC `card_id`
- `arbeitszeiten` – Check-In/Out, Kategorien, Dauer
- `unbekannte_karten` – gescannte, nicht zugeordnete NFC-UIDs

**Schritt 2 – Demo-Daten (optional):**

[`scripts/screenrecording-seed.sql`](scripts/screenrecording-seed.sql) in phpMyAdmin ausführen → Demo-Admin + Demo-Worker mit 6 Wochen Historie.

**Schritt 3 – Legacy-Spalte (falls nötig):**

Falls `unbekannte_karten` noch Spalte `erfasst_am` statt `seen_at` hat:

[`scripts/migrate-unbekannte-karten-seen-at.sql`](scripts/migrate-unbekannte-karten-seen-at.sql)

*(Die App unterstützt beide Spaltennamen automatisch.)*

---

### 4. Wo muss ich die DB-Credentials eintragen?

1. Auf dem Server: `system/config.example.php` → **`system/config.php`** kopieren
2. Werte aus dem Hosting-Panel eintragen:

```php
// system/config.php (oder via SetEnv in .htaccess)
$host = 'mysqlXX.db.hostpoint.internal';  // oder localhost
$db   = 'epakubix_im4';
$user = 'epakubix_im4';
$pass = '***';
```

Alternativ Umgebungsvariablen: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, optional `DEVICE_API_KEY` für ESP32.

**Lokal:** dieselbe `system/config.php` anlegen. Für Remote-DB: im Hostpoint-Panel **Externer MySQL-Zugriff** für deine IP aktivieren.

---

### 5. WebApp deployen & testen

```bash
# Optional: sauberen Upload-Ordner bauen
npm run build          # füllt dist/ (falls build-Script vorhanden)

# Lokal testen
npm run dev            # http://localhost:8000
```

1. Dateien per FTP/SFTP ins Webroot hochladen
2. `https://deine-domain.ch/login.html` öffnen
3. Mit Demo-Account einloggen (siehe unten)
4. Hard-Refresh nach CSS/JS-Updates: **Cmd+Shift+R**

---

### 6. Wie nehme ich das physische Artefakt (ESP32) in Betrieb?

Firmware: [`hardware/esp32_zeiterfassung.ino`](hardware/esp32_zeiterfassung.ino)

**Voraussetzungen:**

- ESP32-C6 + PN532 (I2C), OLED, NeoPixel (Pins im Sketch)
- WLAN **2,4 GHz** (kein 5 GHz)
- NFC-UID in `users.card_id` (Grossbuchstaben, z. B. `1638047A74BC3D`)

**Konfiguration im Sketch:**

```cpp
const char* WLAN_SSID     = "dein-wlan";
const char* WLAN_PASSWORT = "dein-passwort";
const char* SERVER_URL    = "https://im4.nkstudios.ch/api/load.php";
const char* DEVICE_API_KEY = "";  // optional, muss mit Server übereinstimmen
```

**Ablauf NFC-Scan:**

1. ESP32 sendet `POST {"card_id":"1638047A74BC3D"}` an `api/load.php`
2. **Bekannte Karte** → Check-In oder Check-Out (Server entscheidet)
3. **Unbekannte Karte** → Eintrag in `unbekannte_karten`, Admin sieht UID im Dashboard

**Lokal testen:**

```cpp
const bool  SERVER_USE_HTTPS = false;
const char* SERVER_URL = "http://192.168.x.x:8000/api/load.php";
```

---

## Projektstruktur

```
Projekt_Clocked_In/
├── index.html              # Home – Clock In/Out, Timer
├── login.html / register.html
├── settings.html           # Profil, Dark Mode
├── stundenuebersicht.html  # Stundenübersicht + PDF-Export
├── admindashboard.html     # Admin: Team, unbekannte UIDs
├── newuser.html            # Admin: Mitarbeiter anlegen
├── css/style.css           # Design System + Dark Mode
├── js/                     # Frontend-Module (siehe unten)
├── api/                    # PHP REST-Endpoints
├── system/
│   ├── bootstrap.php       # Session, DB-Helfer, Business Logic
│   ├── cors.php
│   ├── config.php          # (nicht in Git – lokal/Server)
│   └── config.example.php
├── setup.sql
├── scripts/                # SQL-Hilfen, Dev-Server
└── hardware/               # ESP32 Firmware
```

---

## Frontend – Design & JavaScript

### Design System (`css/style.css`)

| Token | Wert | Verwendung |
|-------|------|------------|
| `--clr-purple` | `#8c79ef` | Headlines, Nav, Timer-Karte |
| `--clr-green` | `#dbf58a` | Entry-Cards, Erfolg |
| `--clr-pink` | `#fea3e0` | Akzente, Welcome |
| `--clr-black` | `#06050a` | Buttons, Text |
| `--font-display` | Snaga Unicase (Adobe Typekit) | Headlines |
| `--app-max-width` | `430px` (Mobile), `min(60%, 1200px)` ab 1024px | Zentrierte Spalte |

**Layout:**

- Mobile-first, zentrierte App-Spalte
- Ab **1024px Desktop:** Header-Zeile + Bottom-Nav **full-width**, Inhalt bleibt zentriert
- **Dark Mode:** `data-theme="dark"` auf `<html>`, gespeichert in `localStorage` (`clockedin-theme`)

### HTML-Seiten

| Seite | Zweck | JS |
|-------|-------|-----|
| `login.html` | Anmeldung | `login.js` |
| `register.html` | Self-Registration | `register.js` |
| `index.html` | Home, Timer, Clock In/Out | `home.js` |
| `stundenuebersicht.html` | Tag/Woche/Monat, PDF | `overview.js`, `timesheet-export.js` |
| `settings.html` | Profil bearbeiten, Dark Mode | `settings.js`, `logout.js` |
| `admindashboard.html` | Team-Übersicht, unbekannte UIDs | `admin.js` |
| `newuser.html` | Mitarbeiter anlegen (Admin) | Inline-Script |
| `welcomepage.html` | Landing nach Registrierung | — |

### JavaScript-Module

| Datei | Aufgabe |
|-------|---------|
| `api-config.js` | `window.apiUrl()` – relative API-Pfade |
| `theme.js` | Dark/Light Mode, `localStorage` |
| `nav-boot.js` | Admin-Nav sofort anzeigen (kein Layout-Sprung) |
| `auth.js` | `requireAuth()`, `applyRoleVisibility()`, Session-Check |
| `format.js` | Zeitformatierung, `apiGet()`, `apiPost()` |
| `dialog.js` | Modale Alerts/Confirm |
| `home.js` | Clock In/Out, Timer, 10h-Auto-Checkout-Poll (60s) |
| `overview.js` | Kalender, Stundenübersicht |
| `timesheet-export.js` | PDF-Export (pdfmake) |
| `admin.js` | Team-Liste, unbekannte UIDs, Löschen, → `newuser.html` |
| `settings.js` | Profil speichern via `PUT api/profile.php` |

**Auth-Flow:** Jede geschützte Seite ruft `requireAuth()` → `GET api/profile.php` → bei 401 Redirect zu `login.html`. Admin-Seiten prüfen zusätzlich `app_role === 'admin'`.

---

## Datenbank (MySQL)

### `users`

| Spalte | Typ | Beschreibung |
|--------|-----|--------------|
| `id` | INT PK | |
| `email` | VARCHAR UNIQUE | Login |
| `password` | VARCHAR | bcrypt-Hash |
| `firstname`, `lastname` | VARCHAR | Anzeigename |
| `app_role` | VARCHAR | `admin` \| `user` |
| `job_title` | VARCHAR | z. B. Admin, Ausleihe, Büro |
| `location_id` | INT | 1=Chur, 2=Bern, 3=Zürich |
| `card_id` | VARCHAR UNIQUE | NFC-UID |

### `arbeitszeiten`

| Spalte | Typ | Beschreibung |
|--------|-----|--------------|
| `user_id` | FK → users | |
| `zeitstempel` | TIMESTAMP | Check-In/Out-Zeitpunkt |
| `aktion` | VARCHAR | `Check-In`, `Check-Out` |
| `kategorie` | VARCHAR | Kita, Büro, Meeting, Cleanup |
| `dauer_sekunden` | INT | Nur bei Check-Out gesetzt |
| `uid` | VARCHAR | NFC-Karten-ID oder `web-{user_id}` |

### `unbekannte_karten`

| Spalte | Typ | Beschreibung |
|--------|-----|--------------|
| `card_id` | VARCHAR UNIQUE | Gescannte NFC-UID |
| `seen_at` | DATETIME | Erstmals gesehen *(Legacy: `erfasst_am`)* |

---

## API – alle GET- & POST-Routen

Basis-URL: `https://im4.nkstudios.ch/api/`  
Auth: PHP-Session-Cookie (`credentials: "include"` im Frontend).

### Authentifizierung

| Route | Methode | Auth | Body / Query | Response |
|-------|---------|------|--------------|----------|
| `login.php` | **POST** | — | `{ email, password }` | `{ status: "success" }` |
| `logout.php` | **GET** | Session | — | `{ status: "success" }` |
| `register.php` | **POST** | — | `{ email, password }` | `{ status: "success" }` |
| `profile.php` | **GET** | User | — | `{ success, user }` |
| `profile.php` | **PUT/POST** | User | `{ firstname, lastname, email }` | `{ success, user }` |
| `protected.php` | **GET** | User | — | `{ status, user }` (Legacy-Test) |

### Zeiterfassung

| Route | Methode | Auth | Body / Query | Beschreibung |
|-------|---------|------|--------------|--------------|
| `arbeitszeiten.php` | **GET** | User | `?mode=status` | Clock-Status, heute Sekunden, Auto-Checkout bei 10h |
| `arbeitszeiten.php` | **GET** | User | `?from=YYYY-MM-DD&to=YYYY-MM-DD` | Events + Tages-Summen |
| `arbeitszeiten.php` | **POST** | User | `{ aktion: "Check-In", kategorie }` | Einchecken |
| `arbeitszeiten.php` | **POST** | User | `{ aktion: "Check-Out" }` | Auschecken (nie blockiert, gecapped) |
| `arbeitszeiten.php` | **POST** | User | `{ aktion: "Manual", von, bis, datum, kategorie }` | Manueller Eintrag |

### Admin

| Route | Methode | Auth | Body / Query | Beschreibung |
|-------|---------|------|--------------|--------------|
| `admin_team.php` | **GET** | Admin | `?location_id=1\|2\|3` | Team mit Status + Monatsstunden |
| `unbekannte_karten.php` | **GET** | Admin | — | Liste unbekannter NFC-UIDs |
| `unbekannte_karten.php` | **POST** | Admin | `{ action: "delete", id }` | UID entfernen |
| `benutzer_erstellen.php` | **POST** | Admin | `multipart/form-data` | Neuer User (siehe Felder unten) |

**`benutzer_erstellen.php` Formularfelder:**

`firstname`, `lastname`, `rolle` (Admin|Ausleihe), `ort` (Chur|Bern|Zürich), `email`, `passwort`, `card_id`

→ Entfernt passende Zeile aus `unbekannte_karten`.

### ESP32 / Hardware

| Route | Methode | Auth | Body | Beschreibung |
|-------|---------|------|------|--------------|
| `load.php` | **POST** | Optional `X-Device-Key` | `{ card_id }` | NFC Check-In/Out oder unbekannte Karte speichern |
| `load.php` | **OPTIONS** | — | — | CORS Preflight |

**Antwort `load.php` (bekannte Karte):**

```json
{ "success": true, "aktion": "Check-In"|"Check-Out", "mitarbeiter": "...", "dauer_sekunden": 0 }
```

**Antwort (unbekannte Karte):**

```json
{ "success": true, "aktion": "Unbekannt", "card_id": "1638047A74BC3D" }
```

---

## Geschäftsregeln

| Regel | Implementierung |
|-------|-----------------|
| **Max. 10h/Tag** | `CLOCKED_MAX_DAILY_SECONDS = 36000` in `system/bootstrap.php` |
| Auto-Checkout | Bei ≥10h wird offene Session automatisch geschlossen (`clocked_maybe_auto_checkout`) |
| Manueller Checkout | Immer erlaubt, Dauer wird auf Resttageslimit gekappt |
| Manueller Eintrag | Abgelehnt wenn >10h/Tag |
| Kategorien | Kita, Büro, Meeting, Cleanup |
| Standorte | 1=Chur, 2=Bern, 3=Zürich |
| Admin-Rolle | `app_role = 'admin'` → Admin-Dashboard + `newuser.html` |

---

## Lokale Entwicklung vs. Produktion

```bash
npm run dev    # startet php -S 0.0.0.0:8000
```

| | Lokal | Produktion |
|---|-------|------------|
| URL | `http://localhost:8000` | `https://im4.nkstudios.ch` |
| Dateien | Projektordner | FTP → Webroot |
| DB | Hostpoint MySQL (Remote) oder lokal | Hostpoint MySQL |
| ESP32 | Mac-IP:8000 oder Produktions-URL | `https://im4.nkstudios.ch/api/load.php` |

**Tipp:** Gleiche DB lokal und live → Änderungen sofort sichtbar, aber Vorsicht bei Test-Daten.

---

## Demo-Zugangsdaten

Nach [`scripts/screenrecording-seed.sql`](scripts/screenrecording-seed.sql):

| Rolle | E-Mail | Passwort |
|-------|--------|----------|
| Admin | `demo.admin@clockedin.test` | `Demo2026!` |
| Worker | `demo.worker@clockedin.test` | `Demo2026!` |

**Admin testen:** `admindashboard.html` → Unbekannte UID → Chip wählen → «Als Mitarbeiter erfassen» → `newuser.html`


### Bauanleitung Physical Computing

Das System ist als reiner "Thin Client" ("Dummes Terminal") konzipiert. Der ESP32 trifft keine eigenen Entscheidungen und speichert keine Mitarbeiterdaten.
* **Sensoren:** PN532 (liest UID der Karte)
* **Aktoren:** OLED Display, NeoPixel Ring, Buzzer
* **Programm:** clockedin_terminal.ino
* **Kommunikationsweg:** ESP32 -> HTTP POST (WLAN) -> PHP Backend (load.php) -> MySQL Datenbank.


#### Steckplan (Pin-Mapping)

<img width="1000" height="871" alt="Steckplan" src="https://github.com/user-attachments/assets/00de0ade-cc76-4a0f-92cb-2ad786837610" />

Zur Reproduzierbarkeit hier das exakte Pin-Mapping:

* **I2C Bus 1 (OLED):** SDA -> Pin 6, SCL -> Pin 7 (an 3.3V)
* **I2C Bus 0 (PN532):** SDA -> Pin 21, SCL -> Pin 22 (an 5V)
* **NeoPixel Ring:** DIN -> Pin 10 (an 5V, inkl. Kondensator zwischen 5V und GND)
* **Buzzer:** Signal (+) -> Pin 15, GND (-) -> GND


## Technische Details

### Projektstruktur / Code-Struktur (Physical Computing)
* **Setup:** Initialisierung der Hardware, wifiTLS.setInsecure() (bzw. HTTP-Fallback) und Aufbau der WLAN-Verbindung inkl. Reconnect-Logik bei Hängern (WiFi.disconnect(true)).

* **Loop:** Warten auf NFC-Scan. Bei Erkennung Konvertierung der Bytes in einen Hex-String und Auslösen der Funktion datenAnServerSenden(uid).

* **Parser:** Die Server-Antwort wird mittels Arduino_JSON zerlegt. Basierend auf dem Feld aktion (Check-In, Check-Out, Unbekannt) triggert eine Switch/If-Logik die entsprechenden UI-Funktionen (Licht, Ton, Display).


### Datenschnittstelle (API)
* **Request (ESP32 an Server):** JSON POST-Body: {"uid": "8FCA391F"}
* **Response (Server an ESP32):** JSON Body. Beispiel Check-Out:
{"success": true, "mitarbeiter": "Nils", "aktion": "Check-Out", "dauer_sekunden": 28800}

### ERM & Authentifizierung:
* Siehe WebApp-Dokumentation. Das Hardware-Terminal authentifiziert sich optional über den Header X-Device-Key.


## Known bugs

* **5GHz Hotspot Kompatibilität:** Handys funken standardmässig auf 5 GHz oder nutzen WPA3. Der ESP32 kann sich dabei in einer Endlosschleife aufhängen (cannot set config). Workaround: Am Smartphone muss zwingend "Kompatibilität maximieren" (2.4 GHz) aktiviert sein.

* **Strenge SSL-Handshakes:** Bei stark gesicherten Netzwerken oder strengen Hostern (wie Infomaniak) schlägt der HTTPS-Handshake des ESP32 trotz setInsecure() gelegentlich mit HTTP Fehler -1 fehl. Für stabile Demonstrationen musste temporär auf unverschlüsseltes HTTP umgestellt werden.

## Umsetzungsprozess

### Reflexion
Zu Beginn war geplant, die Zuordnung von Karten zu Namen sowie die Arbeitszeitberechnung (millis()) lokal auf dem ESP32 zu speichern. Es stellte sich jedoch als enorm unflexibel heraus, da bei jeder neu hinzugefügten Karte der Code neu geflasht werden musste. Der grösste Lernfortschritt war die Umstellung auf eine "Thin Client"-Architektur, bei der das PHP-Skript die gesamte Logik übernimmt.
  
### Herausforderungen & Lösungen
Das Hinzufügen noch nicht registrierter Karten war mühsam. Die Lösung: Das PHP-Skript fängt unbekannte UIDs ab, speichert sie in einer separaten Tabelle und sendet dem Terminal die Aktion "Unbekannt". Der ESP32 reagiert daraufhin mit einem speziellen blauen UI und zeigt die UID zum einfachen Abtippen im Web an.
* Das WLAN hängte sich beim Reconnect oft auf. Lösung: Ein harter Reset des WLAN-Moduls (WiFi.disconnect(true);) vor jedem neuen Verbindungsversuch machte das System extrem robust.

### KI-Einsatz
Beim Erstellen von Mock-Ups mit Hilfe von *Figma Make* wurde schnell klar, dass die KI trotz verschiedener Prompts meist das selbe Design mit nur dezenten Unterschieden ausspuckt. Der grösste gemeinsame Nenner bei allen *Figma Make*-Designs: langweilig!

Gemini wurde unterstützend eingesetzt, insbesondere beim Debugging von kryptischen ESP32-Fehlermeldungen (wie dem HTTP -1 Fehler). Zudem half die KI bei der Restrukturierung des C++ Codes, um die JSON-Payloads des Servers effizient zu parsen und die Pin-Belegung für externe Schaltplan-Tools sauber zu dokumentieren.

### Fazit
Die Auslagerung der Business-Logik auf den Web-Server war die beste Entscheidung des Projekts. Das Terminal läuft nun extrem stabil, ist beliebig skalierbar und bietet eine intuitive Hardware-Experience für die Nutzer.

