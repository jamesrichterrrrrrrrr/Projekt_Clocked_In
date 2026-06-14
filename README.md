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
* **Video-Dokumentation:** 

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

#### Ablauf
1. *Was benötige ich an Infrastruktur?*  
2. *Was muss ich auf meinem Webserver installieren?*  
3. *Wie kann ich die Datenbank importieren?*  
4. *Wo muss ich die DB-Credentials eintragen?*  
5. *…*  
6. *Wie nehme ich das physische Artefakt in Betrieb?*

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

