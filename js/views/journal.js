"use strict";
// ---------- wydruk dziennika lekcyjnego (TEST-35) ----------
async function vJournal() {
  const as = await api("my_assignments");
  const to = today();
  const from = new Date(Date.now() - 30 * 864e5).toISOString().slice(0, 10);
  return `<h2>Wydruk dziennika lekcyjnego</h2>
 <form class="row" data-h="showJournal"><label>Klasa i przedmiot<select name="assignment_id">${opt(as, "id", (a) => a.class + " – " + a.subject)}</select></label>
 <label>Od<input type="date" name="from" value="${from}" required></label><label>Do<input type="date" name="to" value="${to}" required></label><button>Pokaż</button></form><div id="rep"></div>`;
}
H.showJournal = async (d) => {
  const r = await api("report_journal", d);
  $("#rep").innerHTML =
    `<h3>${esc(r.class)} – ${esc(r.subject)}, ${esc(r.from)} do ${esc(r.to)}</h3>` +
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
    `<button class="sec noprint" data-c="print">Drukuj</button>`;
};
H.print = () => window.print();
