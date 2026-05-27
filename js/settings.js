(async function initSettings() {
  const user = await requireAuth();
  if (!user) return;
  applyLocationTag(user);

  const firstEl = document.getElementById("firstname");
  const lastEl = document.getElementById("lastname");
  const emailEl = document.getElementById("email");
  const nameDisplay = document.getElementById("profile-name-display");
  const tagEl = document.querySelector(".profile-info .entry-tag");
  const saveBtn = document.getElementById("save-btn");

  function applyProfile(u) {
    if (firstEl) firstEl.value = u.firstname || "";
    if (lastEl) lastEl.value = u.lastname || "";
    if (emailEl) emailEl.value = u.email || "";
    if (nameDisplay) {
      nameDisplay.textContent =
        u.display_name || `${u.firstname} ${u.lastname}`.trim();
    }
    if (tagEl && u.job_title) {
      tagEl.textContent = u.job_title;
      tagEl.classList.toggle("buero", u.job_title === "Büro");
    }
  }

  applyProfile(user);

  function updateNamePreview() {
    const first = firstEl ? firstEl.value.trim() : "";
    const last = lastEl ? lastEl.value.trim() : "";
    if (nameDisplay) nameDisplay.textContent = `${first} ${last}`.trim();
  }

  ["firstname", "lastname"].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.addEventListener("input", updateNamePreview);
  });

  if (saveBtn) {
    saveBtn.addEventListener("click", async () => {
      saveBtn.disabled = true;
      try {
        const res = await fetch(apiUrl("api/profile.php"), {
          method: "PUT",
          credentials: "include",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            firstname: firstEl ? firstEl.value.trim() : "",
            lastname: lastEl ? lastEl.value.trim() : "",
            email: emailEl ? emailEl.value.trim() : "",
          }),
        });
        const data = await res.json();
        if (!res.ok) {
          throw new Error(data.message || "Speichern fehlgeschlagen.");
        }
        applyProfile(data.user);
        saveBtn.textContent = "✓ Gespeichert!";
        setTimeout(() => {
          saveBtn.textContent = "Speichern";
        }, 2000);
      } catch (e) {
        alert(e.message || "Speichern fehlgeschlagen.");
      } finally {
        saveBtn.disabled = false;
      }
    });
  }

  let tapCount = 0;
  const profileCard = document.querySelector(".profile-card");
  if (profileCard) {
    profileCard.addEventListener("click", () => {
      tapCount++;
      if (tapCount >= 5) {
        tapCount = 0;
        document.getElementById("easter-overlay").classList.add("active");
        startEaster();
      }
    });
  }

  function startEaster() {
    const btn = document.getElementById("easter-close");
    const countdown = document.getElementById("easter-countdown");
    const anim = document.getElementById("easter-anim");
    const clocks = [
      "🕐", "🕑", "🕒", "🕓", "🕔", "🕕", "🕖", "🕗", "🕘", "🕙", "🕚", "🕛",
    ];
    btn.disabled = true;
    let seconds = 10;
    let clockIndex = 0;
    countdown.textContent = seconds;

    const interval = setInterval(() => {
      seconds--;
      clockIndex = (clockIndex + 1) % clocks.length;
      anim.textContent = clocks[clockIndex];
      countdown.textContent = seconds;
      if (seconds <= 0) {
        clearInterval(interval);
        countdown.textContent = "🎉";
        document.getElementById("easter-text").textContent =
          "Glückwunsch. Du hast 10 Sekunden deines Lebens verschwendet. Wir sind stolz auf dich.";
        btn.disabled = false;
      }
    }, 1000);
  }

  const easterClose = document.getElementById("easter-close");
  if (easterClose) {
    easterClose.addEventListener("click", () => {
      document.getElementById("easter-overlay").classList.remove("active");
    });
  }
})();
