"use strict";
async function vDashboard() {
  const firstName = ME.name.trim().split(/\s+/)[0];
  const now = new Date();
  const todayLabel = new Intl.DateTimeFormat("pl-PL", { dateStyle: "full" }).format(now);
  const localDate = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, "0"), String(now.getDate()).padStart(2, "0")].join("-");
  let familyPicker = "";
  if (ME.role === "rodzic" || ME.role === "uczen") {
    const students = await api("students_list");
    if (students.length) {
      familyPicker = `<form class="row dashboard-family-picker" data-h="showFamilyDashboard"><label>Dziecko<select name="student_id">${students.map((student) => `<option value="${student.id}">${esc(student.first)} ${esc(student.last)}${student.class ? ` (${esc(student.class)})` : ""}</option>`).join("")}</select></label><button>Pokaż podsumowanie</button></form><div id="familyDashboard" aria-live="polite"></div>`;
      setTimeout(() => H.showFamilyDashboard({ student_id: students[0].id }), 0);
    } else {
      familyPicker = `<p class="dashboard-no-student">To konto nie ma powiązanego ucznia.</p>`;
    }
  }
  const shortcuts = [];
  for (const [group, keys] of NAV_GROUPS) {
    const key = keys.find((candidate) => candidate !== "dashboard" && V[candidate]?.[1].includes(ME.role));
    if (key) shortcuts.push({ group, key, title: V[key][0] });
  }
  const descriptions = {
    lesson: "Otwórz lekcję, wpisz temat i uzupełnij frekwencję.",
    grades: "Sprawdź oceny i komentarze dotyczące ucznia.",
    schedule: "Sprawdź tygodniowy plan wybranej klasy.",
    journal: "Przygotuj dziennik lekcyjny do wydruku.",
    missing: "Sprawdź lekcje z nieuzupełnioną frekwencją.",
    excuses: "Przejdź do zgłoszeń i usprawiedliwień nieobecności.",
    history: "Sprawdź historię frekwencji ucznia.",
    stats: "Przejrzyj zestawienia frekwencji.",
    students: "Wyszukaj uczniów i zarządzaj ich danymi.",
    classes: "Przeglądaj klasy i przypisania wychowawców.",
    teachers: "Sprawdź listę nauczycieli i ich przedmioty.",
    parents: "Zarządzaj kontami opiekunów i powiązaniami.",
    subjects: "Przejdź do przedmiotów nauczania.",
    assign: "Zarządzaj przydziałami nauczycieli.",
    notif: "Otwórz powiadomienia z systemu.",
    messages: "Przejdź do komunikacji szkolnej.",
    users: "Zarządzaj kontami i rolami użytkowników.",
    log: "Przejrzyj historię dostępu do systemu.",
    reports: "Przygotuj raporty i dokumenty szkolne.",
    cms: "Edytuj treści publikowane na stronie szkoły.",
    school: "Zobacz informacje i aktualności szkoły.",
    training: "Otwórz instrukcje obsługi dziennika.",
    privacy: "Przejdź do ustawień prywatności."
  };
  return `<section class="dashboard" aria-labelledby="dashboardTitle">
    <header class="dashboard-heading">
      <div>
        <p class="eyebrow">Dziennik elektroniczny · ZSTI Gliwice</p>
        <h2 id="dashboardTitle">Dzień dobry, ${esc(firstName)}.</h2>
        <p>Twoje szkolne sprawy, uporządkowane w jednym miejscu.</p>
      </div>
      <time class="dashboard-date" datetime="${localDate}">${esc(todayLabel)}</time>
    </header>
    ${familyPicker}
    <section class="dashboard-panel" aria-labelledby="dashboardShortcutsTitle">
      <header class="dashboard-panel-heading">
        <div><p class="eyebrow">Szybki dostęp</p><h3 id="dashboardShortcutsTitle">Od czego zaczynamy?</h3></div>
        <span>${shortcuts.length} ${shortcuts.length === 1 ? "obszar" : shortcuts.length < 5 ? "obszary" : "obszarów"}</span>
      </header>
      <div class="dashboard-shortcuts">${shortcuts.map((item, index) => `<button class="dashboard-shortcut" type="button" data-c="go" data-a="${item.key}">
        <span class="dashboard-shortcut-index" aria-hidden="true">${String(index + 1).padStart(2, "0")}</span>
        <span class="dashboard-shortcut-copy"><small>${esc(item.group)}</small><strong>${esc(item.title)}</strong><span>${esc(descriptions[item.key] || "Otwórz ten obszar dziennika.")}</span></span>
        <span class="dashboard-shortcut-arrow" aria-hidden="true">&rarr;</span>
      </button>`).join("")}</div>
    </section>
  </section>`;
}

H.showFamilyDashboard = async (data) => {
  const result = await api("family_dashboard", { student_id: data.student_id });
  $("#familyDashboard").innerHTML = `<p class="eyebrow">Podsumowanie: ${esc(result.student.first)} ${esc(result.student.last)} · ${esc(result.student.class || "bez przypisanej klasy")}</p>
    <div class="dashboard-overview">
      <section class="dashboard-panel"><h3>Plan na dziś</h3>${tbl(["Lekcja", "Przedmiot", "Grupa", "Nauczyciel"], result.schedule.map((item) => [item.lesson_no, esc(item.subject), esc(item.group_name || "–"), esc(item.teacher)]))}</section>
      <section class="dashboard-panel"><h3>Ostatnie oceny</h3>${tbl(["Przedmiot", "Ocena", "Rodzaj", "Data"], result.grades.map((item) => [esc(item.subject), `<strong>${esc(item.value)}</strong>`, esc(item.kind), esc(item.date || "–")]))}</section>
      <section class="dashboard-panel"><h3>Ostatnia frekwencja</h3>${tbl(["Data", "Lekcja", "Przedmiot", "Status"], result.attendance.map((item) => [esc(item.date), item.lesson_no, esc(item.subject), badge(item.status)]))}</section>
      <section class="dashboard-panel"><h3>Ostatnie uwagi</h3>${tbl(["Data", "Nauczyciel", "Treść"], result.remarks.map((item) => [esc(item.date || "–"), esc(item.teacher), esc(item.text)]))}</section>
    </div>`;
};
