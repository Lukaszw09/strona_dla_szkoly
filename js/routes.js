"use strict";
// ---------- widoki ----------
const ALL = ["admin", "dyrektor", "sekretariat", "nauczyciel", "wychowawca", "rodzic", "uczen"];
const STAFF = ["admin", "dyrektor", "sekretariat", "nauczyciel", "wychowawca"];
const V = {
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
  grades: ["Oceny i uwagi", ["nauczyciel", "wychowawca"], vGrades],
  journal: ["Wydruk dziennika", ["nauczyciel", "wychowawca"], vJournal],
  reports: ["Raporty i dokumenty", ["admin", "dyrektor", "sekretariat", "wychowawca"], vReports],
  notif: ["Powiadomienia", ALL, vNotif],
};

// ---------- logowanie i nawigacja ----------
H.login = async (d) => {
  const r = await api("login", d);
  start(r);
};
H.demoLogin = (email) => H.login({ email, password: "haslo123" });
H.logout = async () => {
  await api("logout");
  ME = null;
  showLogin();
};
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
function head() {
  $("#who").textContent = `${ME.name} (${ME.role})${ME.unread ? ` · nowe powiadomienia: ${ME.unread}` : ""}`;
}
function showLogin() {
  $("#app").hidden = true;
  $("#login").hidden = false;
}
function start(r) {
  ME = { ...r.user, unread: r.unread };
  $("#login").hidden = true;
  $("#app").hidden = false;
  head();
  const keys = Object.keys(V).filter((k) => V[k][1].includes(ME.role));
  $("#nav").innerHTML = keys
    .map((k) => `<button data-c="go" data-a="${k}" id="n_${k}">${V[k][0]}</button>`)
    .join("");
  go(keys[0]);
}
async function go(k) {
  CUR = k;
  document.querySelectorAll("nav button").forEach((b) => b.classList.toggle("on", b.id == "n_" + k));
  try {
    $("#main").innerHTML = await V[k][2]();
  } catch (e) {
    if (!e.error) console.error(e);
  }
}
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
api("me")
  .then((r) => (r.user ? start(r) : showLogin()))
  .catch(showLogin);
