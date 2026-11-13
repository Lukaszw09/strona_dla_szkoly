"use strict";
async function vHistory() {
  const kids = await api("students_list");
  setTimeout(() => kids[0] && H.showHist({ student_id: kids[0].id }), 0);
  return `<h2>Historia frekwencji</h2><form class="row" data-h="showHist"><label>Uczeń<select name="student_id">${opt(kids, "id", (k) => k.last + " " + k.first + (k.class ? " (" + k.class + ")" : ""))}</select></label><button>Pokaż</button></form><div id="hist"></div>`;
}
H.showHist = async (d) => {
  const r = await api("attendance_history", { student_id: d.student_id });
  const m = {};
  r.forEach((x) => (m[x.date.slice(0, 7)] ??= []).push(x));
  $("#hist").innerHTML =
    Object.keys(m)
      .sort()
      .reverse()
      .map((k) => {
        const rows = m[k],
          n = (s) => rows.filter((x) => x.status == s).length;
        return (
          `<h3 class="month">${new Date(k + "-01").toLocaleDateString("pl-PL", { month: "long", year: "numeric" })}</h3>
  <p class="muted">obecny: ${n("obecny")} · spóźniony: ${n("spóźniony")} · nieobecny: ${n("nieobecny")} · usprawiedliwiony: ${n("usprawiedliwiony")} · zwolniony: ${n("zwolniony")}</p>` +
          tbl(
            ["Data", "Lekcja", "Przedmiot", "Temat", "Status"],
            rows.map((x) => [x.date, x.lesson_no, esc(x.subject), esc(x.topic), badge(x.status)]),
          )
        );
      })
      .join("") || '<p class="muted">Brak wpisów frekwencji.</p>';
};
