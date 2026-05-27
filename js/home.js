(async function initHome() {
  const user = await requireAuth();
  if (!user) return;
  applyLocationTag(user);
  applyRoleVisibility(user);

  const app = document.getElementById("app");
  const overlay = document.getElementById("activity-overlay");
  const clockBtn = document.getElementById("btn-clock-in");
  const manualBtn = document.getElementById("btn-manual");
  const clockoutTitle = document.getElementById("clockout-title");
  const closeBtn = document.getElementById("activity-close");
  const confirmBtn = document.getElementById("activity-confirm");
  const activityBtns = document.querySelectorAll(".activity-btn");

  const timerLabel = document.getElementById("timer-label");
  const tHours = document.getElementById("t-hours");
  const tMinutes = document.getElementById("t-minutes");
  const tSeconds = document.getElementById("t-seconds");
  const totalTodayEl = document.getElementById("total-today");

  const clockoutOverlay = document.getElementById("clockout-overlay");
  const clockoutClose = document.getElementById("clockout-close");
  const clockoutSave = document.getElementById("clockout-save");
  const coHours = document.getElementById("co-hours");
  const coMinutes = document.getElementById("co-minutes");
  const coSeconds = document.getElementById("co-seconds");
  const coFrom = document.getElementById("co-from");
  const coTo = document.getElementById("co-to");
  const coPills = document.querySelectorAll(".co-pill");

  let selectedActivity = "Kita";
  let timerInterval = null;
  let startTime = null;
  let saving = false;
  let manualMode = false;

  async function refreshTodayTotal() {
    try {
      const data = await apiGet("api/arbeitszeiten.php?mode=status");
      if (totalTodayEl) {
        totalTodayEl.textContent = formatTotalToday(data.today_seconds || 0);
      }
      return data;
    } catch (e) {
      console.error(e);
      return null;
    }
  }

  async function loadStatus() {
    const data = await refreshTodayTotal();
    if (!data) return;

    if (data.clocked_in && data.check_in_at) {
      startTime = new Date(data.check_in_at.replace(" ", "T")).getTime();
      if (!Number.isNaN(startTime)) {
        applyClockedInUI();
        timerInterval = setInterval(updateTimer, 1000);
        updateTimer();
      }
    }
  }

  function applyClockedInUI() {
    app.classList.add("clocked-in");
    timerLabel.textContent = `${selectedActivity} seit`;
    clockBtn.textContent = "Clock Out";
  }

  clockBtn.addEventListener("click", () => {
    if (app.classList.contains("clocked-in")) {
      performClockOut();
    } else {
      overlay.classList.add("active");
    }
  });

  if (manualBtn) {
    manualBtn.addEventListener("click", async () => {
      if (app.classList.contains("clocked-in")) {
        alert("Bitte zuerst ausstempeln, bevor du manuell erfasst.");
        return;
      }
      try {
        const status = await apiGet("api/arbeitszeiten.php?mode=status");
        if (status.clocked_in) {
          alert("Du bist noch eingecheckt. Bitte zuerst ausstempeln.");
          await loadStatus();
          return;
        }
      } catch (_) {
        /* ignore */
      }
      openManualModal();
    });
  }

  async function performClockOut() {
    if (saving) return;
    saving = true;
    clockBtn.disabled = true;
    try {
      const body = { aktion: "Check-Out" };
      if (startTime) {
        body.repair_check_in = formatRepairDateTime(startTime);
      }
      await apiPost("api/arbeitszeiten.php", body);
      clockOutLocal();
      await refreshTodayTotal();
    } catch (e) {
      alert(e.message || "Check-Out fehlgeschlagen.");
    } finally {
      saving = false;
      clockBtn.disabled = false;
    }
  }

  closeBtn.addEventListener("click", closeModal);
  overlay.addEventListener("click", (e) => {
    if (e.target === overlay) closeModal();
  });

  function closeModal() {
    overlay.classList.remove("active");
    activityBtns.forEach((b) => b.classList.remove("selected"));
    confirmBtn.disabled = true;
  }

  activityBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      activityBtns.forEach((b) => b.classList.remove("selected"));
      btn.classList.add("selected");
      selectedActivity = btn.dataset.activity;
      confirmBtn.disabled = false;
    });
  });

  confirmBtn.addEventListener("click", async () => {
    if (!selectedActivity || saving) return;
    saving = true;
    confirmBtn.disabled = true;
    try {
      const result = await apiPost("api/arbeitszeiten.php", {
        aktion: "Check-In",
      });
      if (result.check_in_at) {
        startTime = new Date(
          String(result.check_in_at).replace(" ", "T")
        ).getTime();
      }
      if (!startTime || Number.isNaN(startTime)) {
        startTime = Date.now();
      }
      applyClockedInUI();
      timerInterval = setInterval(updateTimer, 1000);
      updateTimer();
      closeModal();
    } catch (e) {
      alert(e.message || "Check-In fehlgeschlagen.");
    } finally {
      saving = false;
      confirmBtn.disabled = !selectedActivity;
    }
  });

  function clockOutLocal() {
    app.classList.remove("clocked-in");
    clearInterval(timerInterval);
    timerInterval = null;
    startTime = null;
    timerLabel.textContent = "Lock in!";
    clockBtn.textContent = "Clock In";
    tHours.textContent = "00";
    tMinutes.textContent = "00";
    tSeconds.textContent = "00";
  }

  function updateTimer() {
    if (!startTime) return;
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    const f = formatHMS(elapsed);
    tHours.textContent = f.h;
    tMinutes.textContent = f.m;
    tSeconds.textContent = f.s;
  }

  function openManualModal() {
    manualMode = true;
    if (clockoutTitle) {
      clockoutTitle.textContent = "Manuell erfassen";
    }

    const now = new Date();
    const start = new Date(now);
    start.setHours(8, 0, 0, 0);
    if (now < start) {
      start.setHours(now.getHours() - 1, now.getMinutes(), 0, 0);
    }

    coFrom.value = toTimeStr(start);
    coTo.value = toTimeStr(now);
    coPills.forEach((p) => {
      p.classList.toggle("selected", p.dataset.activity === selectedActivity);
    });
    recalcClockoutDuration();
    updateCircleVisual();
    clockoutOverlay.classList.add("active");
  }

  function closeClockoutModal() {
    manualMode = false;
    clockoutOverlay.classList.remove("active");
  }

  function toTimeStr(date) {
    return `${String(date.getHours()).padStart(2, "0")}:${String(date.getMinutes()).padStart(2, "0")}`;
  }

  function recalcClockoutDuration() {
    if (!coFrom.value || !coTo.value) return;
    const [fh, fm] = coFrom.value.split(":").map(Number);
    const [th, tm] = coTo.value.split(":").map(Number);
    let diff = th * 60 + tm - (fh * 60 + fm);
    if (diff < 0) diff += 24 * 60;
    const f = formatHMS(diff * 60);
    coHours.textContent = f.h;
    coMinutes.textContent = f.m;
    coSeconds.textContent = f.s;
  }

  clockoutClose.addEventListener("click", closeClockoutModal);
  clockoutOverlay.addEventListener("click", (e) => {
    if (e.target === clockoutOverlay) closeClockoutModal();
  });
  const clockoutModal = document.querySelector(".clockout-modal");
  if (clockoutModal) {
    clockoutModal.addEventListener("click", (e) => e.stopPropagation());
  }

  coPills.forEach((pill) => {
    pill.addEventListener("click", () => {
      coPills.forEach((p) => p.classList.remove("selected"));
      pill.classList.add("selected");
      selectedActivity = pill.dataset.activity;
      timerLabel.textContent = `${selectedActivity} seit`;
    });
  });

  coFrom.addEventListener("input", () => {
    recalcClockoutDuration();
    updateCircleVisual();
  });
  coTo.addEventListener("input", () => {
    recalcClockoutDuration();
    updateCircleVisual();
  });

  const coRing = document.getElementById("co-ring");
  const coArc = document.getElementById("co-arc");
  const coDotFrom = document.getElementById("co-dot-from");
  const coDotTo = document.getElementById("co-dot-to");
  const CENTER = 120;
  const RADIUS = 100;
  const SNAP_MIN = 5;

  function timeToAngle(timeStr) {
    const [h, m] = timeStr.split(":").map(Number);
    return ((h * 60 + m) / (24 * 60)) * 360;
  }
  function angleToTime(angle) {
    angle = ((angle % 360) + 360) % 360;
    let totalMin = Math.round((angle / 360) * 24 * 60);
    totalMin = Math.round(totalMin / SNAP_MIN) * SNAP_MIN;
    totalMin = ((totalMin % (24 * 60)) + 24 * 60) % (24 * 60);
    const h = Math.floor(totalMin / 60);
    const m = totalMin % 60;
    return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
  }
  function angleToPos(angle) {
    const rad = ((angle - 90) * Math.PI) / 180;
    return {
      x: CENTER + RADIUS * Math.cos(rad),
      y: CENTER + RADIUS * Math.sin(rad),
    };
  }
  function describeArc(fromAngle, toAngle) {
    const from = angleToPos(fromAngle);
    const to = angleToPos(toAngle);
    let sweep = toAngle - fromAngle;
    sweep = ((sweep % 360) + 360) % 360;
    const largeArc = sweep > 180 ? 1 : 0;
    return `M ${from.x} ${from.y} A ${RADIUS} ${RADIUS} 0 ${largeArc} 1 ${to.x} ${to.y}`;
  }
  function updateCircleVisual() {
    if (!coFrom.value || !coTo.value) return;
    const aFrom = timeToAngle(coFrom.value);
    const aTo = timeToAngle(coTo.value);
    const pFrom = angleToPos(aFrom);
    const pTo = angleToPos(aTo);
    coDotFrom.setAttribute("cx", pFrom.x);
    coDotFrom.setAttribute("cy", pFrom.y);
    coDotTo.setAttribute("cx", pTo.x);
    coDotTo.setAttribute("cy", pTo.y);
    coArc.setAttribute("d", describeArc(aFrom, aTo));
  }
  function clientToSvg(clientX, clientY) {
    const pt = coRing.createSVGPoint();
    pt.x = clientX;
    pt.y = clientY;
    return pt.matrixTransform(coRing.getScreenCTM().inverse());
  }
  function posToAngle(x, y) {
    const dx = x - CENTER;
    const dy = y - CENTER;
    let deg = (Math.atan2(dy, dx) * 180) / Math.PI + 90;
    if (deg < 0) deg += 360;
    return deg;
  }
  function setupDrag(dot, target) {
    let dragging = false;
    const onMove = (e) => {
      if (!dragging) return;
      e.preventDefault();
      const { x, y } = clientToSvg(e.clientX, e.clientY);
      const time = angleToTime(posToAngle(x, y));
      if (target === "from") coFrom.value = time;
      else coTo.value = time;
      updateCircleVisual();
      recalcClockoutDuration();
    };
    const onUp = (e) => {
      if (!dragging) return;
      dragging = false;
      dot.style.cursor = "grab";
      try {
        dot.releasePointerCapture(e.pointerId);
      } catch (_) {}
    };
    dot.addEventListener("pointerdown", (e) => {
      e.preventDefault();
      dragging = true;
      dot.style.cursor = "grabbing";
      try {
        dot.setPointerCapture(e.pointerId);
      } catch (_) {}
    });
    dot.addEventListener("pointermove", onMove);
    dot.addEventListener("pointerup", onUp);
    dot.addEventListener("pointercancel", onUp);
  }
  setupDrag(coDotFrom, "from");
  setupDrag(coDotTo, "to");

  clockoutSave.addEventListener("click", async () => {
    if (saving || !manualMode) return;
    saving = true;
    clockoutSave.disabled = true;
    try {
      const today = toDateInput(new Date());
      await apiPost("api/arbeitszeiten.php", {
        aktion: "Manual",
        von: (coFrom.value || "").slice(0, 5),
        bis: (coTo.value || "").slice(0, 5),
        datum: today,
      });
      closeClockoutModal();
      await refreshTodayTotal();
    } catch (e) {
      alert(e.message || "Speichern fehlgeschlagen.");
    } finally {
      saving = false;
      clockoutSave.disabled = false;
    }
  });

  await loadStatus();
})();
