(async function initOverview() {
  const user = await requireAuth();
  if (!user) return;
  applyLocationTag(user);

  const entryList = document.getElementById("entry-list");
  const periodLabel = document.getElementById("period-label");
  const periodTotal = document.getElementById("period-total");
  const periodPrev = document.getElementById("period-prev");
  const periodNext = document.getElementById("period-next");
  const tabBtns = document.querySelectorAll(".tab-btn[data-view]");

  let view = "week";
  const currentDay = { d: startOfDay(new Date()) };
  const currentMonday = { d: getMonday(new Date()) };
  const monthAnchor = {
    d: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
  };

  const tag = user.job_title || "Kita";

  function startOfDay(date) {
    const x = new Date(date);
    x.setHours(0, 0, 0, 0);
    return x;
  }

  function setActiveTab() {
    tabBtns.forEach((b) => {
      b.classList.toggle("active", b.dataset.view === view);
    });
  }

  tabBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      view = btn.dataset.view || "week";
      setActiveTab();
      load();
    });
  });

  periodPrev.addEventListener("click", () => {
    if (view === "day") {
      currentDay.d.setDate(currentDay.d.getDate() - 1);
    } else if (view === "week") {
      currentMonday.d.setDate(currentMonday.d.getDate() - 7);
    } else {
      monthAnchor.d.setMonth(monthAnchor.d.getMonth() - 1);
    }
    load();
  });

  periodNext.addEventListener("click", () => {
    if (view === "day") {
      currentDay.d.setDate(currentDay.d.getDate() + 1);
    } else if (view === "week") {
      currentMonday.d.setDate(currentMonday.d.getDate() + 7);
    } else {
      monthAnchor.d.setMonth(monthAnchor.d.getMonth() + 1);
    }
    load();
  });

  function dateRange() {
    if (view === "day") {
      const iso = toDateInput(currentDay.d);
      return { from: iso, to: iso, label: formatDayLabel(iso) };
    }
    if (view === "week") {
      const sunday = new Date(currentMonday.d);
      sunday.setDate(sunday.getDate() + 6);
      return {
        from: toDateInput(currentMonday.d),
        to: toDateInput(sunday),
        label:
          "Woche vom " +
          currentMonday.d.toLocaleDateString("de-CH", {
            day: "numeric",
            month: "long",
          }),
      };
    }
    const y = monthAnchor.d.getFullYear();
    const m = monthAnchor.d.getMonth();
    const first = new Date(y, m, 1);
    const last = new Date(y, m + 1, 0);
    return {
      from: toDateInput(first),
      to: toDateInput(last),
      label: first.toLocaleDateString("de-CH", {
        month: "long",
        year: "numeric",
      }),
    };
  }

  function sumSeconds(days) {
    return days.reduce((acc, d) => acc + (d.total_seconds || 0), 0);
  }

  function renderDayView(dayData) {
    entryList.innerHTML = "";
    const sessions = dayData?.sessions || [];

    if (!sessions.length) {
      entryList.innerHTML =
        '<p class="entry-empty">Keine Einträge an diesem Tag.</p>';
      return;
    }

    sessions.forEach((session) => {
      const from = session.check_in
        ? formatTimeStamp(session.check_in)
        : "–";
      const to = session.check_out
        ? formatTimeStamp(session.check_out)
        : "läuft…";

      const card = document.createElement("div");
      card.className = "entry-card";
      card.innerHTML =
        '<div class="entry-info">' +
        '<div class="entry-main">' +
        '<span class="entry-date">' + from + " – " + to + "</span>" +
        '<span class="entry-tag">' + tag + "</span>" +
        "</div>" +
        '<div class="entry-sub">' +
        '<img src="svgs/Clock_Icon.svg" style="width:16px" alt=""> Dauer: ' +
        formatHoursShort(session.seconds) +
        "</div></div>" +
        '<span class="entry-arrow">›</span>';
      entryList.appendChild(card);
    });
  }

  function renderWeekOrMonthDays(days) {
    entryList.innerHTML = "";

    if (!days.length) {
      entryList.innerHTML =
        '<p class="entry-empty">Keine Einträge in diesem Zeitraum.</p>';
      return;
    }

    days.forEach((day) => {
      const card = document.createElement("div");
      card.className = "entry-card";
      const n = (day.sessions || []).length;
      const meta = n > 1 ? n + " Einträge · " : "";

      card.innerHTML =
        '<div class="entry-info">' +
        '<div class="entry-main">' +
        '<span class="entry-date">' + formatDayLabel(day.date) + "</span>" +
        '<span class="entry-tag">' + tag + "</span>" +
        "</div>" +
        '<div class="entry-sub">' +
        '<img src="svgs/Clock_Icon.svg" style="width:16px" alt=""> ' +
        meta + "Total: " + formatHoursShort(day.total_seconds) +
        "</div></div>" +
        '<span class="entry-arrow">›</span>';

      card.addEventListener("click", () => {
        view = "day";
        currentDay.d = parseDateInput(day.date);
        setActiveTab();
        load();
      });

      entryList.appendChild(card);
    });
  }

  async function load() {
    const range = dateRange();
    periodLabel.innerHTML =
      '<img src="svgs/calendar_today.svg" class="week-icon" alt=""> ' +
      range.label;

    entryList.innerHTML = '<p class="entry-empty">Laden…</p>';
    periodTotal.textContent = "";

    try {
      const data = await apiGet(
        "api/arbeitszeiten.php?from=" +
          encodeURIComponent(range.from) +
          "&to=" +
          encodeURIComponent(range.to)
      );
      const days = data.days || [];
      periodTotal.textContent =
        "Gesamt: " + formatHoursShort(sumSeconds(days));

      if (view === "day") {
        const dayData = days.find((d) => d.date === range.from) || {
          date: range.from,
          total_seconds: 0,
          sessions: [],
        };
        renderDayView(dayData);
      } else {
        renderWeekOrMonthDays(days);
      }
    } catch (e) {
      console.error(e);
      entryList.innerHTML =
        '<p class="entry-empty">Daten konnten nicht geladen werden. (' +
        (e.message || "Fehler") +
        ")</p>";
    }
  }

  setActiveTab();
  await load();
})();
