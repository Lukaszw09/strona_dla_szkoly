"use strict";
let ME = null,
  CUR = "",
  H = {},
  CACHE = {},
  LESSON = null,
  GRADE_CTX = {};
const $ = (s) => document.querySelector(s);
const esc = (s) =>
  String(s ?? "").replace(
    /[&<>"']/g,
    (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c],
  );
const ST = ["obecny", "nieobecny", "spóźniony", "zwolniony", "usprawiedliwiony"];
const DAYS = ["Pn", "Wt", "Śr", "Cz", "Pt"];
const today = () => new Date().toISOString().slice(0, 10);
const badge = (s) => `<span class="b s-${esc(s)}">${esc(s)}</span>`;
const opt = (a, v, l, sel) =>
  a
    .map(
      (x) =>
        `<option value="${esc(x[v])}"${x[v] == sel ? " selected" : ""}>${esc(typeof l == "function" ? l(x) : x[l])}</option>`,
    )
    .join("");
const tbl = (h, r) =>
  `<div class="tw"><table><thead><tr>${h.map((x) => `<th>${x}</th>`).join("")}</tr></thead><tbody>${r.map((x) => `<tr${x.cls ? ` class="${x.cls}"` : ""}>${x.map((c) => `<td>${c}</td>`).join("")}</tr>`).join("") || `<tr><td colspan="${h.length}" class="muted">Brak danych</td></tr>`}</tbody></table></div>`;
const btn = (c, a, t, k = "") => `<button class="sm ${k}" data-c="${c}" data-a="${esc(a)}">${t}</button>`;
const is = (...r) => r.includes(ME.role);
let tt;
function toast(m, bad) {
  const t = $("#toast");
  t.textContent = m;
  t.className = "show" + (bad ? " bad" : "");
  clearTimeout(tt);
  tt = setTimeout(() => (t.className = ""), 3500);
}
async function api(a, d) {
  const r = await fetch("api.php?action=" + a, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(d || {}),
  });
  let j;
  try {
    j = await r.json();
  } catch (e) {
    j = { error: "Błąd serwera" };
  }
  if (r.status === 401 && a !== "login" && a !== "me") {
    ME = null;
    showLogin();
    toast("Sesja wygasła – zaloguj się ponownie", 1);
    throw j;
  }
  if (j.error) {
    toast(j.error, 1);
    throw j;
  }
  return j;
}
const fill = (f, o) => {
  for (const k in o) {
    const e = f.elements[k];
    if (!e) continue;
    if (e.type == "checkbox") e.checked = !!+o[k];
    else e.value = o[k] ?? "";
  }
  f.scrollIntoView({ block: "center" });
};
const checked = (f, n) => [...f.querySelectorAll(`[name=${n}]:checked`)].map((x) => x.value);
const THEME_KEY = "zsti-dziennik-theme";
function setTheme(theme, persist = false) {
  const selected = theme === "dark" ? "dark" : "light";
  document.documentElement.dataset.theme = selected;
  document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
    const dark = selected === "dark";
    button.textContent = dark ? "Jasny motyw" : "Ciemny motyw";
    button.setAttribute("aria-label", dark ? "Włącz jasny motyw" : "Włącz ciemny motyw");
    button.setAttribute("aria-pressed", String(dark));
  });
  if (persist) {
    try {
      localStorage.setItem(THEME_KEY, selected);
    } catch (e) {
      console.warn("Nie udało się zapisać ustawienia motywu.", e);
    }
  }
}
function initialTheme() {
  try {
    const saved = localStorage.getItem(THEME_KEY);
    if (saved === "dark" || saved === "light") return saved;
  } catch (e) {
    console.warn("Nie udało się odczytać ustawienia motywu.", e);
  }
  return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
}
