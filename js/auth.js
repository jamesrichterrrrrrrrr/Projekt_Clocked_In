/**
 * Session check + profile load for protected pages.
 */
async function requireAuth() {
  try {
    const res = await fetch(apiUrl("api/profile.php"), { credentials: "include" });
    if (!res.ok) {
      window.location.href = "login.html";
      return null;
    }
    const data = await res.json();
    if (!data.success || !data.user) {
      window.location.href = "login.html";
      return null;
    }
    return data.user;
  } catch (e) {
    console.error("Auth failed:", e);
    window.location.href = "login.html";
    return null;
  }
}

function applyLocationTag(user) {
  const el = document.querySelector(".location-tag");
  if (el && user?.location_name) {
    el.textContent = "Standort " + user.location_name;
  }
}

function isAdminUser(user) {
  return String(user?.app_role || "").toLowerCase() === "admin";
}

function applyRoleVisibility(user, scope = document) {
  const showAdminOnly = isAdminUser(user);
  scope.querySelectorAll("[data-role-visible='admin']").forEach((el) => {
    el.hidden = !showAdminOnly;
    el.style.display = showAdminOnly ? "" : "none";
  });
}
