(() => {
  "use strict";

  const LS = {
    reminder: "hm_reminder_state_v2",
    quote: "hm_quote_date_v1",
    sidebarCollapsed: "hm_sidebar_collapsed_v2",
    sidebarOpen: "hm_sidebar_open_v2",
    foodDraft: "hm_food_draft_v2",
  };

  const COLORS = {
    primary: "#2ECC71",
    secondary: "#27AE60",
    accent: "#F39C12",
    dark: "#2C3E50",
    light: "#ECF0F1",
    danger: "#E74C3C",
    info: "#3498DB",
  };

  const parseJSON = (v, d = {}) => { try { return JSON.parse(v); } catch (_) { return d; } };

  function injectStyles() {
    if (document.getElementById("hm-mainjs-style")) return;
    const s = document.createElement("style");
    s.id = "hm-mainjs-style";
    s.textContent = `
      .hm-toast-root{position:fixed;top:16px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:10px}
      .hm-toast{width:min(360px,92vw);background:var(--bg,#fff);border-left:4px solid var(--b,#3498db);border-radius:12px;padding:10px 12px;box-shadow:0 12px 28px rgba(0,0,0,.12);opacity:0;transform:translateX(120%);transition:all .25s}
      .hm-toast.in{opacity:1;transform:translateX(0)}
      .hm-toast.out{opacity:0;transform:translateX(120%)}
      .hm-toast .head{display:flex;justify-content:space-between;align-items:center;gap:8px}
      .hm-toast .title{font-size:12px;font-weight:700;letter-spacing:.4px}
      .hm-toast .close{border:0;background:transparent;cursor:pointer;font-size:16px;line-height:1}
      .hm-toast .msg{margin-top:5px;font-size:14px;color:#20343e}
      .hm-toast .prog{height:3px;background:#0001;border-radius:99px;overflow:hidden;margin-top:8px}
      .hm-toast .prog>span{display:block;height:100%;background:var(--b,#3498db);transform-origin:left center;animation:hmProg linear forwards}
      @keyframes hmProg{from{transform:scaleX(1)}to{transform:scaleX(0)}}

      .hm-loading{position:fixed;inset:0;z-index:9998;background:#2c3e5033;display:grid;place-items:center;backdrop-filter:blur(1px)}
      .hm-spinner{width:46px;height:46px;border:4px solid #d9e2e8;border-top-color:${COLORS.primary};border-radius:50%;animation:hmSpin .8s linear infinite}
      .hm-btn-spinner{width:14px;height:14px;border:2px solid #fff;border-right-color:transparent;border-radius:50%;display:inline-block;margin-right:8px;animation:hmSpin .8s linear infinite;vertical-align:middle}
      @keyframes hmSpin{to{transform:rotate(360deg)}}

      .hm-page-enter{opacity:0;transition:opacity .28s ease}
      .hm-page-enter.hm-active{opacity:1}
      .hm-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
      .hm-row-hover{background:#2ecc7114!important}
      .hm-empty{border:1px dashed #cfe1d7;border-radius:14px;padding:16px;text-align:center;background:#f7fffb}
      .hm-empty .icon{font-size:28px;line-height:1}
      .hm-skeleton{position:relative;overflow:hidden;background:#eef3f4;border-radius:10px;min-height:80px}
      .hm-skeleton::after{content:\"\";position:absolute;inset:0;transform:translateX(-100%);background:linear-gradient(90deg,transparent,#ffffffa8,transparent);animation:hmShimmer 1.2s infinite}
      @keyframes hmShimmer{100%{transform:translateX(100%)}}
      .char-counter{margin-top:6px;font-size:12px;color:#70828a}.char-counter.warn{color:${COLORS.accent};font-weight:600}
      .drag-over{outline:2px dashed ${COLORS.primary};outline-offset:3px}
      .hm-bmi-pop{animation:hmPop .25s ease}.hm-goal-pulse{animation:hmPulse 1s ease 3}
      .hm-heart-pop{animation:hmHeart .42s ease}.hm-check-pop{animation:hmPop .4s ease}.hm-water-pop{animation:hmWater .5s ease}
      @keyframes hmPop{0%{transform:scale(.9)}60%{transform:scale(1.12)}100%{transform:scale(1)}}
      @keyframes hmPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.04)}}
      @keyframes hmHeart{0%{transform:scale(1)}35%{transform:scale(1.2)}100%{transform:scale(1)}}
      @keyframes hmWater{0%{transform:translateY(0)}40%{transform:translateY(-3px)}100%{transform:translateY(0)}}
      .hm-conf{position:fixed;width:8px;height:12px;border-radius:2px;z-index:9999;animation:hmConf 1.2s ease-out forwards}
      @keyframes hmConf{to{transform:translateY(100px) rotate(360deg);opacity:0}}
      @media (max-width:991.98px){body.sidebar-open .sidebar{transform:translateX(0)}}
    `;
    document.head.appendChild(s);
  }

  class NotificationSystem {
    constructor() {
      this.root = document.getElementById("hmToastRoot") || document.body.appendChild(Object.assign(document.createElement("div"), { id: "hmToastRoot", className: "hm-toast-root" }));
      this.i = 0;
      this.themes = {
        success: { bg: "#ebfff2", b: COLORS.primary },
        error: { bg: "#fff0ee", b: COLORS.danger },
        warning: { bg: "#fff8e8", b: COLORS.accent },
        info: { bg: "#eaf5ff", b: COLORS.info },
        reminder: { bg: "#f0fff9", b: COLORS.secondary },
      };
    }
    show(message, type = "info", duration = 5000) {
      const t = this.themes[type] || this.themes.info;
      const el = document.createElement("div");
      el.className = `hm-toast hm-${type}`;
      el.style.setProperty("--bg", t.bg);
      el.style.setProperty("--b", t.b);
      el.innerHTML = `<div class="head"><div class="title">${type.toUpperCase()}</div><button class="close" type="button" aria-label="close">x</button></div><div class="msg"></div><div class="prog"><span></span></div>`;
      el.querySelector(".msg").textContent = String(message || "");
      el.querySelector(".close").addEventListener("click", () => this.remove(el));
      const p = el.querySelector(".prog>span");
      p.style.animationDuration = `${Math.max(1000, duration)}ms`;
      this.root.appendChild(el);
      requestAnimationFrame(() => el.classList.add("in"));
      if (duration > 0) setTimeout(() => this.remove(el), duration);
      return `toast-${Date.now()}-${this.i++}`;
    }
    remove(el) {
      if (!el || !el.parentNode) return;
      el.classList.add("out");
      setTimeout(() => el.remove(), 250);
    }
  }

  class DietReminder {
    constructor(notifier) {
      this.n = notifier;
      this.s = Object.assign({ breakfast: "", lunch: "", dinner: "", waterTs: 0, calorieDate: "" }, parseJSON(localStorage.getItem(LS.reminder), {}));
      this.motivationalQuotes = [
        "Small healthy choices become life-changing results.","Nourish your body and your mind follows.","Consistency beats intensity every time.","Healthy eating is self-respect in action.","Every balanced meal is progress.","Your body hears everything your mind says.","Eat for your goals, not cravings alone.","Hydration powers every system in your body.","Discipline today creates strength tomorrow.","One workout and one meal at a time.","Good habits are the strongest medicine.","Progress, not perfection.","Fuel your body, focus your mind.","Wellness is built daily, not instantly.","The best project you will ever work on is you.","Your future health starts with today's plate.","A healthy outside starts from the inside.","Strong body, steady mind, better life.","Healthy routines create lasting freedom.","You are one choice away from a better day."
      ];
    }
    saveReminderState() { localStorage.setItem(LS.reminder, JSON.stringify(this.s)); }
    day() { return new Date().toISOString().slice(0, 10); }
    hour() { return new Date().getHours(); }
    meal(kind, text) { if (this.s[kind] === this.day()) return; this.n.show(text, "reminder", 7000); this.s[kind] = this.day(); this.saveReminderState(); }
    checkMealReminders() {
      const h = this.hour();
      if (h >= 7 && h < 9) this.meal("breakfast", "Time for Breakfast! Start your day right ??");
      else if (h >= 12 && h < 13) this.meal("lunch", "Lunch time! Don't skip your meal ??");
      else if (h >= 19 && h < 20) this.meal("dinner", "Dinner time! Keep it light and healthy ??");
    }
    checkWaterReminder() {
      const now = Date.now();
      if (!this.s.waterTs || now - this.s.waterTs >= 2 * 60 * 60 * 1000) { this.n.show("Stay hydrated! Drink water ??", "reminder", 6000); this.s.waterTs = now; this.saveReminderState(); }
    }
    checkCalorieReminder() {
      if (this.hour() < 15 || this.s.calorieDate === this.day()) return;
      const c = parseFloat((document.querySelector("[data-calorie-consumed]") || {}).textContent || "0");
      const g = parseFloat((document.querySelector("[data-calorie-goal]") || {}).textContent || "0");
      if (g > 0 && c / g < 0.5) { this.n.show("You're behind on your calorie goal", "warning", 7000); this.s.calorieDate = this.day(); this.saveReminderState(); }
    }
    showQuote() {
      const d = this.day();
      if (localStorage.getItem(LS.quote) === d) return;
      const q = this.motivationalQuotes[Math.floor(Math.random() * this.motivationalQuotes.length)];
      this.n.show(q, "info", 5500);
      localStorage.setItem(LS.quote, d);
    }
    init() { this.showQuote(); this.checkMealReminders(); this.checkWaterReminder(); this.checkCalorieReminder(); setInterval(() => { this.checkMealReminders(); this.checkWaterReminder(); this.checkCalorieReminder(); }, 30 * 60 * 1000); }
  }

  function bmiValue(w, h) { const W = parseFloat(w), H = parseFloat(h); if (!W || !H || H <= 0) return 0; const m = H / 100; return W / (m * m); }
  function bmiCat(v) { if (v < 18.5) return { n: "Underweight", c: COLORS.info }; if (v < 25) return { n: "Normal", c: COLORS.primary }; if (v < 30) return { n: "Overweight", c: COLORS.accent }; return { n: "Obese", c: COLORS.danger }; }

  function drawBmiGauge(cv, v) {
    if (!cv || !cv.getContext) return;
    const x = cv.getContext("2d"), w = cv.width, h = cv.height, cx = w / 2, cy = h * 0.88, r = Math.min(w * 0.42, h * 0.75);
    x.clearRect(0, 0, w, h); x.lineWidth = 16; x.lineCap = "round";
    const sec = [[18.5, "#3498DB"], [25, "#2ECC71"], [30, "#F39C12"], [40, "#E74C3C"]]; let p = 10;
    sec.forEach(([m, c]) => { const a = Math.PI + ((p - 10) / 30) * Math.PI, b = Math.PI + ((m - 10) / 30) * Math.PI; x.beginPath(); x.strokeStyle = c; x.arc(cx, cy, r, a, b); x.stroke(); p = m; });
    const cl = Math.max(10, Math.min(40, v || 10)), n = Math.PI + ((cl - 10) / 30) * Math.PI;
    x.strokeStyle = COLORS.dark; x.lineWidth = 3; x.beginPath(); x.moveTo(cx, cy); x.lineTo(cx + Math.cos(n) * (r - 10), cy + Math.sin(n) * (r - 10)); x.stroke(); x.beginPath(); x.fillStyle = COLORS.dark; x.arc(cx, cy, 5, 0, 2 * Math.PI); x.fill();
  }

  function initBMIWidget() {
    const w = document.querySelector("[data-bmi-weight],#weight,#weightInput"), h = document.querySelector("[data-bmi-height],#height,#heightInput"), o = document.querySelector("[data-bmi-result],#bmiPreview,#bmiResult");
    if (!w || !h || !o) return;
    const c = document.querySelector("[data-bmi-category],#bmiCategory"), i = document.querySelector("[data-ideal-range],#idealWeightRange"), cv = document.querySelector("[data-bmi-gauge]");
    const r = () => {
      const v = bmiValue(w.value, h.value);
      if (!v) { o.textContent = "--"; if (c) c.textContent = "N/A"; if (i) i.textContent = "--"; if (cv) drawBmiGauge(cv, 10); return; }
      const cat = bmiCat(v); o.textContent = v.toFixed(2); o.style.color = cat.c; o.classList.remove("hm-bmi-pop"); void o.offsetWidth; o.classList.add("hm-bmi-pop");
      if (c) { c.textContent = cat.n; c.style.color = cat.c; }
      const hm = parseFloat(h.value) / 100; if (i && hm > 0) i.textContent = `${(18.5 * hm * hm).toFixed(1)}kg - ${(24.9 * hm * hm).toFixed(1)}kg`;
      if (cv) drawBmiGauge(cv, v);
    };
    w.addEventListener("input", r); h.addEventListener("input", r); window.addEventListener("resize", r); r();
  }

  function drawCalRing(cv, consumed, goal) {
    if (!cv || !cv.getContext) return;
    const x = cv.getContext("2d"), w = cv.width, h = cv.height, cx = w / 2, cy = h / 2, r = Math.min(w, h) / 2 - 14;
    const raw = goal > 0 ? consumed / goal : 0, p = Math.max(0, Math.min(1.4, raw));
    let col = COLORS.primary; if (raw >= 1) col = COLORS.danger; else if (raw >= .9) col = COLORS.accent;
    x.clearRect(0, 0, w, h); x.lineWidth = 12; x.beginPath(); x.strokeStyle = "#dde6e8"; x.arc(cx, cy, r, -Math.PI / 2, 1.5 * Math.PI); x.stroke(); x.beginPath(); x.strokeStyle = col; x.arc(cx, cy, r, -Math.PI / 2, -Math.PI / 2 + 2 * Math.PI * p); x.stroke();
    x.fillStyle = COLORS.dark; x.font = "600 20px Poppins, sans-serif"; x.textAlign = "center"; x.fillText(`${Math.round(consumed)}`, cx, cy + 2); x.font = "500 12px Poppins, sans-serif"; x.fillStyle = "#6c7d87"; x.fillText(`of ${Math.round(goal)} cal`, cx, cy + 22);
  }

  function confetti(x = innerWidth / 2, y = 60) {
    const c = ["#2ECC71", "#3498DB", "#F39C12", "#E74C3C"];
    for (let k = 0; k < 24; k += 1) {
      const s = document.createElement("span"); s.className = "hm-conf"; s.style.left = `${x + (Math.random() * 60 - 30)}px`; s.style.top = `${y}px`; s.style.background = c[k % 4];
      document.body.appendChild(s); setTimeout(() => s.remove(), 1200);
    }
  }

  function initCalorieTrackerWidget() {
    const cv = document.querySelector("[data-calorie-ring]"); if (!cv) return;
    const c = parseFloat((document.querySelector("[data-calorie-consumed]") || {}).textContent || "0") || 0;
    const g = parseFloat((document.querySelector("[data-calorie-goal]") || {}).textContent || "0") || 0;
    let f = 0, max = 36; const t = () => { f += 1; drawCalRing(cv, c * (f / max), g); if (f < max) requestAnimationFrame(t); else if (g > 0 && c >= g) { cv.classList.add("hm-goal-pulse"); confetti(cv.getBoundingClientRect().left + 30, cv.getBoundingClientRect().top + 20); } }; t();
  }

  function initSidebarBehavior() {
    const b = document.body, t = document.getElementById("sidebarToggle") || document.querySelector("[data-sidebar-toggle]"), m = document.querySelector("[data-mobile-menu], .hamburger"), mq = matchMedia("(min-width:768px) and (max-width:991.98px)");
    if (localStorage.getItem(LS.sidebarCollapsed) === "1") b.classList.add("sidebar-collapsed");
    if (localStorage.getItem(LS.sidebarOpen) === "1") b.classList.add("sidebar-open");
    const sync = () => mq.matches ? b.classList.add("sidebar-collapsed-auto") : b.classList.remove("sidebar-collapsed-auto"); sync(); mq.addEventListener("change", sync);
    if (t) t.addEventListener("click", () => { b.classList.toggle("sidebar-collapsed"); localStorage.setItem(LS.sidebarCollapsed, b.classList.contains("sidebar-collapsed") ? "1" : "0"); });
    if (m) m.addEventListener("click", () => { b.classList.toggle("sidebar-open"); localStorage.setItem(LS.sidebarOpen, b.classList.contains("sidebar-open") ? "1" : "0"); });
    const p = location.pathname.split("/").pop(); document.querySelectorAll(".sidebar-menu a[href], .sidebar a[href]").forEach(a => { const h = (a.getAttribute("href") || "").split("?")[0]; if (h.endsWith(p)) a.classList.add("active"); });
  }

  function formatPhone(i) { const d = i.value.replace(/\D/g, "").slice(0, 15); if (d.length <= 3) i.value = d; else if (d.length <= 6) i.value = `${d.slice(0, 3)}-${d.slice(3)}`; else if (d.length <= 10) i.value = `${d.slice(0, 3)}-${d.slice(3, 6)}-${d.slice(6)}`; else i.value = `+${d}`; }
  function initDatePickers() { const max = new Date().toISOString().slice(0, 10); document.querySelectorAll('input[type="date"]').forEach(d => { if (!d.max) d.max = max; if (d.dataset.min) d.min = d.dataset.min; }); }

  function initFormEnhancements() {
    document.querySelectorAll('input[type="tel"],input[name*="phone"]').forEach(i => i.addEventListener("input", () => formatPhone(i)));
    const wu = document.querySelector("[data-weight-unit]"), hu = document.querySelector("[data-height-unit]"), w = document.querySelector("[data-weight-input],#weight"), h = document.querySelector("[data-height-input],#height");
    if (wu && w) wu.addEventListener("change", () => { const v = parseFloat(w.value); if (!v) return; w.value = wu.value === "lbs" ? (v * 2.20462).toFixed(1) : (v / 2.20462).toFixed(1); });
    if (hu && h) hu.addEventListener("change", () => { const v = parseFloat(h.value); if (!v) return; if (hu.value === "ftin") { const ti = v / 2.54; h.value = `${Math.floor(ti / 12)}.${Math.round(ti % 12)}`; } else { const p = String(h.value).split("."); h.value = ((((parseFloat(p[0]) || 0) * 12) + (parseFloat(p[1]) || 0)) * 2.54).toFixed(1); } });
    initDatePickers();

    document.querySelectorAll('input[type="file"][accept*="image"]').forEach(inp => {
      const prevSel = inp.dataset.previewTarget, prev = prevSel ? document.querySelector(prevSel) : inp.closest(".form-group")?.querySelector(".image-preview"), dz = inp.closest("[data-dropzone]");
      const draw = f => { if (!f || !prev || !f.type.startsWith("image/")) return; const u = URL.createObjectURL(f); if (prev.tagName === "IMG") prev.src = u; else prev.innerHTML = `<img src="${u}" alt="preview" style="max-width:100%;height:auto;border-radius:10px;">`; };
      inp.addEventListener("change", () => draw(inp.files && inp.files[0]));
      if (!dz) return;
      ["dragenter", "dragover"].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.add("drag-over"); }));
      ["dragleave", "drop"].forEach(e => dz.addEventListener(e, ev => { ev.preventDefault(); dz.classList.remove("drag-over"); }));
      dz.addEventListener("drop", ev => { const fs = ev.dataTransfer?.files; if (!fs || !fs.length) return; inp.files = fs; draw(fs[0]); });
    });

    document.querySelectorAll("textarea[maxlength], textarea[data-char-counter]").forEach(t => {
      let o = t.parentNode ? t.parentNode.querySelector(".char-counter") : null;
      if (!o) { o = document.createElement("div"); o.className = "char-counter"; t.parentNode && t.parentNode.appendChild(o); }
      const m = t.maxLength > 0 ? t.maxLength : Number(t.dataset.charCounter || 1000);
      const u = () => { o.textContent = `${t.value.length}/${m}`; o.classList.toggle("warn", t.value.length > m * .9); };
      t.addEventListener("input", u); u();
    });
  }

  let overlay = null;
  function showLoadingOverlay() {
    if (overlay) return;
    overlay = document.createElement("div");
    overlay.className = "hm-loading";
    overlay.innerHTML = '<div class="hm-spinner" aria-label="Loading"></div>';
    document.body.appendChild(overlay);
  }
  function hideLoadingOverlay() { if (!overlay) return; overlay.remove(); overlay = null; }

  function ajaxPost(url, data, callback) {
    showLoadingOverlay();
    fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: new URLSearchParams(data || {}).toString(),
      credentials: "same-origin",
    }).then(r => r.json()).then(j => callback && callback(j)).catch(() => notifier.show("Request failed. Please try again.", "error")).finally(() => hideLoadingOverlay());
  }

  function ajaxGet(url, params, callback) {
    showLoadingOverlay();
    const q = new URLSearchParams(params || {}).toString();
    fetch(`${url}${url.includes("?") ? "&" : "?"}${q}`, { method: "GET", credentials: "same-origin" })
      .then(r => r.json()).then(j => callback && callback(j)).catch(() => notifier.show("Request failed. Please try again.", "error")).finally(() => hideLoadingOverlay());
  }

  function setButtonLoading(btn, loading, text) {
    if (!btn) return;
    if (loading) { btn.dataset.original = btn.innerHTML; btn.disabled = true; btn.innerHTML = `<span class="hm-btn-spinner"></span>${text || "Loading..."}`; }
    else { btn.disabled = false; btn.innerHTML = btn.dataset.original || btn.innerHTML; }
  }

  function showSkeleton(selector) {
    document.querySelectorAll(selector).forEach(el => el.classList.add("hm-skeleton"));
  }

  function hideSkeleton(selector) {
    document.querySelectorAll(selector).forEach(el => el.classList.remove("hm-skeleton"));
  }

  function initFoodDraftAutosave() {
    const f = document.getElementById("addForm") || document.getElementById("manualAddForm");
    if (!f) return;
    const prev = parseJSON(localStorage.getItem(LS.foodDraft), {});
    Object.keys(prev).forEach(k => { const field = f.querySelector(`[name="${k}"]`); if (field && !field.value) field.value = prev[k]; });
    const save = () => {
      const p = {};
      new FormData(f).forEach((v, k) => { if (k !== "action") p[k] = String(v); });
      localStorage.setItem(LS.foodDraft, JSON.stringify(p));
    };
    setInterval(save, 30000);
    f.addEventListener("submit", () => localStorage.removeItem(LS.foodDraft));
  }

  function initTableFeatures() {
    document.querySelectorAll("[data-table-tools]").forEach(w => {
      const t = w.querySelector("table"); if (!t) return;
      w.classList.add("hm-table-wrap");
      const rows = () => Array.from(t.querySelectorAll("tbody tr"));
      const s = w.querySelector("[data-table-search]");
      if (s) s.addEventListener("input", () => { const q = s.value.toLowerCase().trim(); rows().forEach(r => { r.style.display = r.textContent.toLowerCase().includes(q) ? "" : "none"; }); });
      t.querySelectorAll("th[data-sort]").forEach((th, i) => {
        th.style.cursor = "pointer";
        th.addEventListener("click", () => {
          const asc = th.dataset.dir !== "asc"; th.dataset.dir = asc ? "asc" : "desc";
          const sorted = rows().sort((a, b) => {
            const av = (a.children[i]?.textContent || "").trim(), bv = (b.children[i]?.textContent || "").trim();
            const an = Number(av.replace(/[^\d.-]/g, "")), bn = Number(bv.replace(/[^\d.-]/g, ""));
            if (!Number.isNaN(an) && !Number.isNaN(bn) && av !== "" && bv !== "") return asc ? an - bn : bn - an;
            return asc ? av.localeCompare(bv) : bv.localeCompare(av);
          });
          const b = t.querySelector("tbody"); sorted.forEach(r => b && b.appendChild(r));
        });
      });
      rows().forEach(r => { r.addEventListener("mouseenter", () => r.classList.add("hm-row-hover")); r.addEventListener("mouseleave", () => r.classList.remove("hm-row-hover")); });
    });
  }

  function initEmptyStates() {
    const defs = {
      food: { i: "??", t: "No food logged today", b: "Add Food", h: "#addFood" },
      message: { i: "??", t: "No messages yet", b: "Compose", h: "#composeMessage" },
      plan: { i: "??", t: "No diet plan assigned", b: "Request Plan", h: "/user/diet_plan.php" },
      default: { i: "??", t: "No data available", b: "Refresh", h: "#" },
    };
    document.querySelectorAll("[data-empty-state]").forEach(box => {
      if (box.querySelector(".hm-empty")) return;
      const d = defs[box.dataset.emptyState || "default"] || defs.default;
      box.innerHTML = `<div class="hm-empty"><div class="icon">${d.i}</div><p>${d.t}</p><a class="btn btn-primary btn-sm" href="${d.h}">${d.b}</a></div>`;
    });
  }

  function initMicroInteractions() {
    document.querySelectorAll("[data-favorite-btn], .favorite-btn").forEach(b => b.addEventListener("click", () => { b.classList.add("hm-heart-pop"); setTimeout(() => b.classList.remove("hm-heart-pop"), 420); }));
    document.querySelectorAll("[data-log-food-btn]").forEach(b => b.addEventListener("click", () => { b.classList.add("hm-check-pop"); setTimeout(() => b.classList.remove("hm-check-pop"), 420); }));
    document.querySelectorAll("[data-log-water-btn]").forEach(b => b.addEventListener("click", () => { b.classList.add("hm-water-pop"); setTimeout(() => b.classList.remove("hm-water-pop"), 520); }));
  }

  function initChartResizeFix() {
    let raf = null;
    addEventListener("resize", () => {
      if (raf) cancelAnimationFrame(raf);
      raf = requestAnimationFrame(() => {
        if (window.Chart && window.Chart.instances) {
          const c = window.Chart.instances;
          Object.keys(c).forEach(k => { const ch = c[k]; if (ch && typeof ch.resize === "function") ch.resize(); });
        }
      });
    });
  }

  function initPageFade() { document.body.classList.add("hm-page-enter"); setTimeout(() => document.body.classList.add("hm-active"), 10); }

  function initPrintFns() {
    window.printPage = () => window.print();
    window.downloadPDF = () => { notifier.show("Use Print and choose Save as PDF in your browser.", "info", 4500); setTimeout(() => window.print(), 200); };
    window.showPrintPreview = (url = null) => window.open(url || "/diet_system/user/download_plan.php", "_blank", "noopener,noreferrer");
  }

  const notifier = new NotificationSystem();

  document.addEventListener("DOMContentLoaded", () => {
    injectStyles();
    initPageFade();
    initSidebarBehavior();
    initBMIWidget();
    initCalorieTrackerWidget();
    initFormEnhancements();
    initFoodDraftAutosave();
    initTableFeatures();
    initEmptyStates();
    initMicroInteractions();
    initChartResizeFix();
    initPrintFns();

    const reminders = new DietReminder(notifier);
    reminders.init();

    window.HMNotify = (m, t, d) => notifier.show(m, t, d);
    window.NotificationSystem = NotificationSystem;
    window.DietReminder = DietReminder;
    window.ajaxPost = ajaxPost;
    window.ajaxGet = ajaxGet;
    window.showLoadingOverlay = showLoadingOverlay;
    window.hideLoadingOverlay = hideLoadingOverlay;
    window.setButtonLoading = setButtonLoading;
    window.showSkeleton = showSkeleton;
    window.hideSkeleton = hideSkeleton;
  });
})();
