/**
 * Lokale HTML (z. B. http://localhost:8000) → PHP auf Infomaniak
 * Setze die Basis-URL deines Webhostings (ohne abschließenden Slash).
 * Beispiel: https://deinedomain.ch oder https://deinedomain.ch/unterordner
 * Leer lassen: API-Aufrufe bleiben relativ (Seite und PHP auf derselben Domain).
 */
window.API_BASE = "";

window.apiUrl = function (path) {
  const p = path.replace(/^\/+/, "");
  const base = String(window.API_BASE || "").replace(/\/+$/, "");
  if (!base) return p;
  return base + "/" + p;
};

if (typeof window !== "undefined" && window.location && window.location.protocol === "file:") {
  console.warn(
    "[api-config] HTML per file:// geöffnet: Aufrufe zu PHP schlagen fehl. Bitte dieselbe Seite über http(s):// auf dem Webserver öffnen."
  );
}
