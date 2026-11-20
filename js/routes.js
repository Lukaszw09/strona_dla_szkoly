"use strict";
// ---------- widoki ----------
const ALL = ["admin", "dyrektor", "sekretariat", "nauczyciel", "wychowawca", "rodzic", "uczen"];
const STAFF = ["admin", "dyrektor", "sekretariat", "nauczyciel", "wychowawca"];
const V = {
  dashboard: ["Pulpit", ALL, vDashboard],
  lesson: ["Lekcja i frekwencja", ["nauczyciel", "wychowawca"], vLesson],
  missing: ["Braki we frekwencji", ["wychowawca", "dyrektor", "admin"], vMissing],
  excuses: ["Usprawiedliwienia", ["rodzic", "wychowawca", "dyrektor", "sekretariat", "admin"], vExcuses],
  history: ["Historia frekwencji", ALL, vHistory],
  stats: ["Statystyki frekwencji", ["admin", "dyrektor", "sekretariat", "wychowawca"], vStats],
  students: ["Uczniowie", STAFF, vStudents],
  classes: ["Klasy", STAFF.slice(0, 3).concat(["wychowawca", "nauczyciel"]), vClasses],
  teachers: ["Nauczyciele", ["admin", "dyrektor", "sekretariat"], vTeachers],
  parents: ["Rodzice i opiekunowie", ["admin", "sekretariat", "dyrektor"], vParents],
  subjects: ["Przedmioty", ["admin", "dyrektor", "sekretariat", "nauczyciel", "wychowawca"], vSubjects],
  assign: ["Przydziały", ["admin", "dyrektor", "sekretariat"], vAssign],
  schedule: ["Plan lekcji", STAFF, vSchedule],
  users: ["Użytkownicy i role", ["admin"], vUsers],
  log: ["Logi dostępu", ["admin", "dyrektor"], vLog],
  grades: ["Oceny i uwagi", ["nauczyciel", "wychowawca", "rodzic", "uczen"], vGrades],
  journal: ["Wydruk dziennika", ["nauczyciel", "wychowawca"], vJournal],
  reports: ["Raporty i dokumenty", ["admin", "dyrektor", "sekretariat", "wychowawca"], vReports],
  notif: ["Powiadomienia", ALL, vNotif],
  school: ["O szkole", ALL, vSchool],
  cms: ["CMS strony szkoły", ["admin", "sekretariat"], vCms],
  messages: ["Komunikacja", ALL, vMessages],
  training: ["Szkolenie", ALL, vTraining],
  privacy: ["RODO i prywatność", ALL, vPrivacy],
};
const NAV_GROUPS = [
  ["Start", ["dashboard"]],
  ["Moja praca", ["lesson", "grades", "schedule", "journal"]],
  ["Frekwencja", ["missing", "excuses", "history", "stats"]],
  ["Osoby i klasy", ["students", "classes", "teachers", "parents", "subjects", "assign"]],
  ["Kontakt", ["notif", "messages"]],
  ["Administracja", ["users", "log", "reports", "cms"]],
  ["Szkoła i pomoc", ["school", "training", "privacy"]],
];
function animateEntry(target) {
  const gsap = window.gsap;
  if (!target || !gsap || window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
  gsap.killTweensOf(target);
  gsap.fromTo(
    target,
    { autoAlpha: 0, y: 8 },
    { autoAlpha: 1, y: 0, duration: 0.26, ease: "power2.out", clearProps: "transform,opacity,visibility" },
  );
}

// ---------- logowanie i nawigacja ----------
H.login = async (d) => {
  const r = await api("login", d);
  start(r);
};
H.demoLogin = (email) => H.login({ email, password: "haslo123" });
H.logout = async () => {
  await api("logout");
  ME = null;
  showSchoolHome();
};
H.showLogin = () => showLogin();
H.showSchoolHome = () => showSchoolHome();
H.showReset = () => {
  $("#resetBox").hidden = !$("#resetBox").hidden;
};
H.resetReq = async (d) => {
  await api("reset_request", d);
  toast("Jeśli konto istnieje, wysłano token resetu");
};
H.resetDo = async (d) => {
  await api("reset_confirm", d);
  toast("Hasło zmienione – możesz się zalogować");
};
H.go = (a) => go(a);
H.toggleTheme = () => setTheme(document.documentElement.dataset.theme === "dark" ? "light" : "dark", true);
function head() {
  $("#who").textContent = `${ME.name} (${ME.role})${ME.unread ? ` · nowe powiadomienia: ${ME.unread}` : ""}`;
}
function showLogin() {
  closeNav();
  $("#app").hidden = true;
  $("#publicSchool").hidden = true;
  $("#login").hidden = false;
  animateEntry($("#login .login-shell"));
}
async function showSchoolHome() {
  closeNav();
  $("#app").hidden = true;
  $("#login").hidden = true;
  $("#publicSchoolContent").innerHTML = await vSchool();
  $("#publicSchool").hidden = false;
  window.scrollTo(0, 0);
  animateEntry($("#publicSchool .school-hero"));
}
function start(r) {
  closeNav();
  ME = { ...r.user, unread: r.unread };
  $("#publicSchoolContent").innerHTML = "";
  $("#publicSchool").hidden = true;
  $("#login").hidden = true;
  $("#app").hidden = false;
  head();
  const keys = Object.keys(V).filter((k) => V[k][1].includes(ME.role));
  $("#nav").innerHTML = NAV_GROUPS.map(([label, groupKeys]) => {
    const visible = groupKeys.filter((k) => keys.includes(k));
    if (!visible.length) return "";
    return `<div class="nav-group"><span class="nav-section-label">${label}</span>${visible
      .map((k) => `<button data-c="go" data-a="${k}" id="n_${k}">${V[k][0]}</button>`)
      .join("")}</div>`;
  })
    .join("");
  go(keys[0]);
}
async function go(k) {
  CUR = k;
  const currentView = $("#currentView");
  if (currentView) currentView.textContent = V[k][0];
  closeNav();
  document.querySelectorAll("#nav button").forEach((b) => {
    const active = b.id === "n_" + k;
    b.classList.toggle("on", active);
    if (active) b.setAttribute("aria-current", "page");
    else b.removeAttribute("aria-current");
  });
  try {
    $("#main").innerHTML = await V[k][2]();
    animateEntry($("#main"));
  } catch (e) {
    if (!e.error) console.error(e);
  }
}
function closeNav(restoreFocus = false) {
  $("#app").classList.remove("nav-open");
  document.body.classList.remove("nav-open");
  const toggle = $("#navToggle");
  if (!toggle) return;
  toggle.setAttribute("aria-expanded", "false");
  if (restoreFocus) toggle.focus();
}
H.toggleNav = () => {
  const open = !$("#app").classList.contains("nav-open");
  $("#app").classList.toggle("nav-open", open);
  document.body.classList.toggle("nav-open", open);
  $("#navToggle").setAttribute("aria-expanded", String(open));
  if (open) $("#nav button")?.focus();
};
document.addEventListener("submit", async (e) => {
  const h = e.target.dataset.h;
  if (!h) return;
  e.preventDefault();
  try {
    await H[h](Object.fromEntries(new FormData(e.target)), e.target);
  } catch (x) {
    if (!x.error) console.error(x);
  }
});
document.addEventListener("click", async (e) => {
  const b = e.target.closest("[data-c]");
  if (!b) return;
  e.preventDefault();
  try {
    await H[b.dataset.c](b.dataset.a, b);
  } catch (x) {
    if (!x.error) console.error(x);
  }
});
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && $("#app").classList.contains("nav-open")) closeNav(true);
});
setTheme(initialTheme());
api("me")
  .then((r) => (r.user ? start(r) : showSchoolHome()))
  .catch(showSchoolHome);
