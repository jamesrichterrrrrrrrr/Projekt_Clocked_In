/* Admin dashboard (UI-first). */
(async function initAdminDashboard() {
  const user = await requireAuth();
  if (!user) return;

  if (!isAdminUser(user)) {
    window.location.href = "index.html";
    return;
  }

  applyLocationTag(user);
  applyRoleVisibility(user);

  const locationPills = document.querySelectorAll(".admin-pill");
  const uidInput = document.getElementById("unknown-uid-input");
  const uidList = document.getElementById("unknown-uid-list");
  const uidCount = document.getElementById("unknown-count");
  const createBtn = document.getElementById("unknown-create-btn");

  const unknownUids = ["1638047A74BC3D"];

  function normalizeUid(value) {
    return String(value || "")
      .trim()
      .replace(/[^a-zA-Z0-9]/g, "")
      .toUpperCase();
  }

  function goToCreateUser(uid) {
    const normalized = normalizeUid(uid);
    if (!normalized) return;
    window.location.href = `newuser.html?card_id=${encodeURIComponent(normalized)}`;
  }

  function renderUnknownUidList() {
    if (!uidList) return;
    uidList.innerHTML = "";

    unknownUids.forEach((uid) => {
      const chip = document.createElement("button");
      chip.type = "button";
      chip.className = "admin-uid-chip";
      chip.dataset.uid = uid;
      chip.textContent = uid;
      chip.addEventListener("click", () => goToCreateUser(uid));
      uidList.appendChild(chip);
    });

    if (uidCount) {
      uidCount.textContent = String(unknownUids.length);
    }
  }

  locationPills.forEach((pill) => {
    pill.addEventListener("click", () => {
      locationPills.forEach((p) => p.classList.remove("active"));
      pill.classList.add("active");
    });
  });

  if (createBtn) {
    createBtn.addEventListener("click", () => {
      const uid = normalizeUid(uidInput?.value || "");
      if (!uid) {
        alert("Bitte eine UID eingeben.");
        return;
      }
      if (!unknownUids.includes(uid)) {
        unknownUids.unshift(uid);
      }
      renderUnknownUidList();
      goToCreateUser(uid);
    });
  }

  if (uidInput) {
    uidInput.addEventListener("keydown", (event) => {
      if (event.key !== "Enter") return;
      event.preventDefault();
      createBtn?.click();
    });
  }

  renderUnknownUidList();
})();
