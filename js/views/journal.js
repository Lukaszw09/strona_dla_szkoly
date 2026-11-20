"use strict";
// ---------- wydruk dziennika lekcyjnego (TEST-35) ----------
async function vJournal() {
  const as = await api("my_assignments");
  const to = today();
  const from = new Date(Date.now() - 30 * 864e5).toISOString().slice(0, 10);
  return `<section class="journal-view" aria-labelledby="journalTitle">
 <header class="journal-heading"><div><p class="eyebrow">Moja praca / Raporty</p><h2 id="journalTitle">Wydruk dziennika lekcyjnego</h2><p class="muted">Wybierz klasę, przedmiot i zakres dat, aby przygotować raport.</p></div><span class="journal-heading-mark" aria-hidden="true">ZSTI</span></header>
 <section class="journal-filter-panel" aria-labelledby="journalFiltersTitle"><div class="journal-panel-heading"><div><p class="eyebrow">Ustawienia raportu</p><h3 id="journalFiltersTitle">Zakres dziennika</h3></div><span class="journal-period">Zakres dat</span></div>
 <form class="row journal-filters" data-h="showJournal"><label>Klasa i przedmiot<select name="assignment_id">${opt(as, "id", (a) => a.class + " – " + a.subject)}</select></label>
 <label>Od<input type="date" name="from" value="${from}" required></label><label>Do<input type="date" name="to" value="${to}" required></label><button>Wyświetl raport</button></form></section><div id="rep" aria-live="polite"></div></section>`;
}
H.showJournal = async (d) => {
  const r = await api("report_journal", d);
  $("#rep").innerHTML =
    `<section class="journal-report" aria-labelledby="journalReportTitle"><header class="journal-report-heading"><div><p class="eyebrow">Raport dziennika</p><h3 id="journalReportTitle">${esc(r.class)} – ${esc(r.subject)}</h3><p class="muted">${esc(r.from)} — ${esc(r.to)}</p></div><div class="journal-report-actions"><span class="journal-count">Liczba lekcji: ${r.lessons.length}</span><button class="sec noprint" data-c="print">Drukuj raport</button></div></header>` +
    tbl(
      ["Data", "Lekcja", "Temat", "Obecni", "Nieobecni"],
      r.lessons.map((l) => [
        l.date,
        l.lesson_no,
        esc(l.topic),
        l.attendance_count,
        esc(l.absent.join(", ")) || "–",
      ]),
    ) +
    `</section>`;
};
H.print = () => window.print();
