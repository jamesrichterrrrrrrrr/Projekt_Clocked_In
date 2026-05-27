/*
 * ClockedIn – Kita Zeiterfassung (ESP32-C6 + PN532 + OLED + NeoPixel)
 * Sendet Check-In/Check-Out an: https://im4.nkstudios.ch/api/load.php
 *
 * In phpMyAdmin / newuser.html: users.card_id = UID unten (Großbuchstaben).
 * Optional auf dem Server: SetEnv DEVICE_API_KEY "..." + Header X-Device-Key unten.
 */

#include <Wire.h>
#include <Adafruit_PN532.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <Adafruit_NeoPixel.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <Arduino_JSON.h>

// ── WLAN & Server ─────────────────────────────────────────────
const char* WLAN_SSID     = "tinkergarden";
const char* WLAN_PASSWORT = "strenggeheim";

// Muss mit deinem FTP-Deploy übereinstimmen (api/load.php im Projekt)
const char* SERVER_URL = "https://im4.nkstudios.ch/api/load.php";

// Leer lassen wenn DEVICE_API_KEY auf dem Server nicht gesetzt ist
const char* DEVICE_API_KEY = "";

bool isWlanConnected = false;

// ── Mitarbeiter: UID (NFC) → Anzeigename ──────────────────────
// card_id in MySQL muss exakt diese UID sein (Großbuchstaben)
struct BekannterMitarbeiter {
  const char* uid;
  const char* name;
};

BekannterMitarbeiter mitarbeiterListe[] = {
  {"8FCA391F",       "Nils"},
  {"1638047A74BC3D", "Len"},
  {"04A3BD1F210289", "Marko"},
  {"0453E522210289", "Bomboraas"},
};
const int ANZAHL_MITARBEITER = 4;

// ── Pins (ESP32-C6) ───────────────────────────────────────────
#define PN532_SDA      21
#define PN532_SCL      22
#define LED_RING_PIN   10
#define LED_RING_COUNT 12
#define ONBOARD_LED     2
#define BUZZER_PIN     15

#define SCREEN_WIDTH  128
#define SCREEN_HEIGHT 64

TwoWire I2C_DISPLAY = TwoWire(1);
Adafruit_SSD1306 display(SCREEN_WIDTH, SCREEN_HEIGHT, &I2C_DISPLAY, -1);

TwoWire I2C_NFC = TwoWire(0);
Adafruit_PN532 nfc(PN532_SDA, PN532_SCL, &I2C_NFC);

Adafruit_NeoPixel ring(LED_RING_COUNT, LED_RING_PIN, NEO_GRB + NEO_KHZ800);

// ── Lokale Check-In-State (Display / Dauer bis Checkout) ─────
struct CardRecord {
  String uid;
  unsigned long checkInTime;
  bool checkedIn;
};

const int MAX_CARDS = 20;
CardRecord cards[MAX_CARDS];
int cardCount = 0;

WiFiClientSecure wifiTLS;

// ── Hilfsfunktionen ───────────────────────────────────────────
String bytesToUID(uint8_t* uid, uint8_t uidLength) {
  String result = "";
  for (uint8_t i = 0; i < uidLength; i++) {
    if (uid[i] < 0x10) result += "0";
    result += String(uid[i], HEX);
  }
  result.toUpperCase();
  return result;
}

String getNameZurUID(const String& uid) {
  for (int i = 0; i < ANZAHL_MITARBEITER; i++) {
    if (uid.equalsIgnoreCase(mitarbeiterListe[i].uid)) {
      return String(mitarbeiterListe[i].name);
    }
  }
  return "Unbekannt";
}

String formatMillis(unsigned long ms) {
  unsigned long totalSec = ms / 1000;
  unsigned long h = totalSec / 3600;
  unsigned long m = (totalSec % 3600) / 60;
  unsigned long s = totalSec % 60;
  char buf[12];
  snprintf(buf, sizeof(buf), "%02lu:%02lu:%02lu", h, m, s);
  return String(buf);
}

CardRecord* findCard(const String& uid) {
  for (int i = 0; i < cardCount; i++) {
    if (cards[i].uid == uid) return &cards[i];
  }
  return nullptr;
}

void connectWiFi() {
  Serial.printf("\nVerbinde mit WLAN %s ...\n", WLAN_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WLAN_SSID, WLAN_PASSWORT);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 40) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("\nWiFi OK – IP: %s\n", WiFi.localIP().toString().c_str());
    isWlanConnected = true;
  } else {
    Serial.println("\nWLAN fehlgeschlagen.");
    isWlanConnected = false;
  }
}

bool checkWLAN() {
  if (WiFi.status() != WL_CONNECTED) {
    if (isWlanConnected) {
      Serial.println("WLAN weg – Reconnect...");
      isWlanConnected = false;
    }
    connectWiFi();
    return WiFi.status() == WL_CONNECTED;
  }
  return true;
}

// ── Server: api/load.php ──────────────────────────────────────
bool datenAnServerSenden(const String& name, const String& uid, const String& aktion, unsigned long dauerSek) {
  if (!checkWLAN()) {
    Serial.println("Kein WLAN – nicht gesendet.");
    return false;
  }

  JSONVar payload;
  payload["mitarbeiter"]      = name;
  payload["uid"]              = uid;
  payload["aktion"]           = aktion;
  payload["dauer_sekunden"]   = (int)dauerSek;

  String jsonString = JSON.stringify(payload);
  Serial.println("POST " + String(SERVER_URL));
  Serial.println(jsonString);

  HTTPClient http;
  if (!http.begin(wifiTLS, SERVER_URL)) {
    Serial.println("http.begin fehlgeschlagen.");
    return false;
  }

  http.addHeader("Content-Type", "application/json");
  if (strlen(DEVICE_API_KEY) > 0) {
    http.addHeader("X-Device-Key", DEVICE_API_KEY);
  }

  int code = http.POST(jsonString);
  String response = http.getString();
  http.end();

  Serial.printf("HTTP %d\n", code);
  if (response.length() > 0) {
    Serial.println("Antwort: " + response);
  }

  return (code >= 200 && code < 300);
}

// ── LED ───────────────────────────────────────────────────────
void setRingColor(uint32_t color) {
  for (int i = 0; i < LED_RING_COUNT; i++) {
    ring.setPixelColor(i, color);
  }
  ring.show();
}

// ── Display / Daumenkino ──────────────────────────────────────
void drawHeart(int x, int y, int size) {
  display.fillCircle(x - size, y - size, size, SSD1306_WHITE);
  display.fillCircle(x + size, y - size, size, SSD1306_WHITE);
  display.fillTriangle(x - size * 2, y - size + 1, x + size * 2, y - size + 1, x, y + size * 2, SSD1306_WHITE);
}

void drawWavingGuy(int frame) {
  display.fillCircle(64, 20, 8, SSD1306_WHITE);
  display.drawLine(64, 28, 64, 48, SSD1306_WHITE);
  display.drawLine(64, 32, 50, 42, SSD1306_WHITE);
  display.drawLine(64, 48, 54, 63, SSD1306_WHITE);
  display.drawLine(64, 48, 74, 63, SSD1306_WHITE);
  if (frame == 0) {
    display.drawLine(64, 32, 80, 22, SSD1306_WHITE);
  } else {
    display.drawLine(64, 32, 82, 34, SSD1306_WHITE);
  }
}

void showCheckIn(const String& name) {
  display.clearDisplay();
  display.setTextSize(2);
  display.setTextColor(SSD1306_WHITE);
  display.setCursor(0, 0);
  display.println("CHECK IN");
  display.setTextSize(1);
  display.setCursor(0, 25);
  display.println("Hallo,");
  display.setTextSize(2);
  display.setCursor(0, 40);
  display.println(name);
  display.display();
  delay(800);

  for (int i = 0; i < 2; i++) {
    display.clearDisplay();
    drawHeart(64, 30, 8);
    display.display();
    delay(150);
    display.clearDisplay();
    drawHeart(64, 30, 14);
    display.display();
    delay(200);
    display.clearDisplay();
    drawHeart(64, 30, 6);
    display.display();
    delay(150);
  }
}

void showCheckOut(const String& name, unsigned long duration) {
  display.clearDisplay();
  display.setTextSize(2);
  display.setTextColor(SSD1306_WHITE);
  display.setCursor(0, 0);
  display.println("CHECK OUT");
  display.setTextSize(1);
  display.setCursor(0, 20);
  display.println("Tschuess, " + name);
  display.setCursor(0, 45);
  display.setTextSize(2);
  display.println(formatMillis(duration));
  display.display();
  delay(1800);

  for (int i = 0; i < 4; i++) {
    display.clearDisplay();
    drawWavingGuy(0);
    display.display();
    delay(200);
    display.clearDisplay();
    drawWavingGuy(1);
    display.display();
    delay(200);
  }
  display.clearDisplay();
  display.display();
}

void showReady() {
  display.clearDisplay();
  display.setTextSize(1);
  display.setTextColor(SSD1306_WHITE);
  display.setCursor(20, 5);
  display.println("Kita Zeiterfassung");
  display.drawLine(0, 18, 127, 18, SSD1306_WHITE);
  display.setTextSize(2);
  display.setCursor(10, 35);
  display.println("Bereit...");
  display.display();
}

void piepKurz() {
  digitalWrite(BUZZER_PIN, HIGH);
  delay(100);
  digitalWrite(BUZZER_PIN, LOW);
}

void piepLang() {
  digitalWrite(BUZZER_PIN, HIGH);
  delay(600);
  digitalWrite(BUZZER_PIN, LOW);
}

// ── Setup ─────────────────────────────────────────────────────
void setup() {
  Serial.begin(115200);
  delay(1000);

  pinMode(ONBOARD_LED, OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);

  // HTTPS ohne Zertifikat-Setup (für Schulprojekt / Infomaniak)
  wifiTLS.setInsecure();

  I2C_DISPLAY.begin(6, 7);
  if (!display.begin(SSD1306_SWITCHCAPVCC, 0x3C)) {
    Serial.println("OLED fehlt!");
    while (true) { delay(1000); }
  }

  connectWiFi();

  ring.begin();
  ring.setBrightness(80);
  ring.show();

  I2C_NFC.begin(PN532_SDA, PN532_SCL);
  nfc.begin();
  if (!nfc.getFirmwareVersion()) {
    Serial.println("PN532 nicht gefunden!");
  } else {
    nfc.SAMConfig();
  }

  showReady();
  Serial.println("Bereit – Karte scannen.");
}

// ── Loop ──────────────────────────────────────────────────────
void loop() {
  checkWLAN();

  uint8_t uid[7];
  uint8_t uidLength;

  if (!nfc.readPassiveTargetID(PN532_MIFARE_ISO14443A, uid, &uidLength, 100)) {
    return;
  }

  String uidStr = bytesToUID(uid, uidLength);
  String mitarbeiterName = getNameZurUID(uidStr);

  Serial.println("\n--- Scan ---");
  Serial.println("UID:  " + uidStr);
  Serial.println("Name: " + mitarbeiterName);

  CardRecord* rec = findCard(uidStr);

  if (rec == nullptr || !rec->checkedIn) {
    // CHECK IN
    if (rec == nullptr) {
      if (cardCount < MAX_CARDS) {
        cards[cardCount] = {uidStr, millis(), true};
        rec = &cards[cardCount];
        cardCount++;
      }
    } else {
      rec->checkInTime = millis();
      rec->checkedIn = true;
    }

    piepKurz();
    setRingColor(ring.Color(200, 0, 0));
    showCheckIn(mitarbeiterName);

    datenAnServerSenden(mitarbeiterName, uidStr, "Check-In", 0);

    setRingColor(0);
    showReady();
  } else {
    // CHECK OUT
    unsigned long duration = millis() - rec->checkInTime;
    rec->checkedIn = false;
    unsigned long dauerSek = duration / 1000;

    piepLang();
    setRingColor(ring.Color(0, 200, 0));
    showCheckOut(mitarbeiterName, duration);

    datenAnServerSenden(mitarbeiterName, uidStr, "Check-Out", dauerSek);

    setRingColor(0);
    showReady();
  }

  delay(800);
}
