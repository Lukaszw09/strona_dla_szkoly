"use strict";
// ---------- raporty i dokumenty (TEST-29..34, 36) ----------
async function vReports() {
  const [students, classes] = await Promise.all([api("students_list"), api("classes_list")]);
  const stuSel = `<select name="student_id">${opt(students, "id", (k) => k.last + " " + k.first + (k.class ? " (" + k.class + ")" : ""))}</select>`;
  const classSel = `<select name="class_id">${opt(classes, "id", "name")}</select>`;
  const director = is("admin", "dyrektor");
  return `<h2>Raporty i dokumenty</h2><div class="noprint">
 <form class="row" data-h="repCertificate"><label>Uczeń${stuSel}</label><button>Świadectwo szkolne</button><button type="button" class="sec" data-c="repSheet">Arkusz ocen ucznia</button></form>
 <form class="row" data-h="repClass"><label>Klasa${classSel}</label><button>Zestawienie ocen klasy</button><button type="button" class="sec" data-c="repThreats">Zagrożenia ocenami niedostatecznymi</button></form>
 <div class="row"><button data-c="repHonors">Lista uczniów wyróżnionych</button>${director ? `<button data-c="repSchool">Statystyki szkoły</button>` : ""}${is("admin", "dyrektor", "sekretariat") ? `<button class="sec" data-c="sioExport">Eksport do SIO (CSV)</button>` : ""}</div></div><div id="rep"></div>`;
}
const printBtn = `<p class="noprint"><button class="sec" data-c="print">Drukuj</button></p>`;
const formVal = (name) => document.querySelector(`#main [name=${name}]`).value;
const show = (html) => ($("#rep").innerHTML = html + printBtn);
const gradesTable = (finals) =>
  tbl(
    ["Przedmiot", "Ocena końcowa"],
    Object.entries(finals).map(([k, v]) => [esc(k), v ?? "–"]),
  );

H.repCertificate = async (d) => {
  const r = await api("report_certificate", { student_id: d.student_id });
  const st = r.student;
  show(`<div class="doc"><h2>Świadectwo szkolne ${esc(r.school_year)}${r.honors ? " – z wyróżnieniem (z paskiem)" : ""}</h2>
   <p><strong>${esc(st.first + " " + st.last)}</strong>, ur. ${esc(st.birth)}, klasa ${esc(st.class)}</p>
   ${gradesTable(r.grades)}<p>Zachowanie: <strong>${esc(st.conduct)}</strong> · Średnia: <strong>${r.avg ?? "–"}</strong></p>
   <p>${r.promoted ? "Uczeń otrzymuje promocję do klasy programowo wyższej." : "Uczeń nie otrzymuje promocji."}</p></div>`);
};
H.repSheet = async () => {
  const r = await api("report_sheet", { student_id: formVal("student_id") });
  const bySubject = {};
  r.grades.forEach((g) =>
    (bySubject[g.subject] ??= []).push(g.value + (g.kind == "cząstkowa" ? "" : " (" + g.kind + ")")),
  );
  show(`<div class="doc"><h2>Arkusz ocen: ${esc(r.student.first + " " + r.student.last)}, klasa ${esc(r.student.class)}</h2>
   ${tbl(
     ["Przedmiot", "Oceny", "Końcowa"],
     Object.entries(bySubject).map(([k, v]) => [esc(k), esc(v.join(", ")), r.finals[k] ?? "–"]),
   )}
   <p>Średnia: <strong>${r.avg ?? "–"}</strong> · Zachowanie: <strong>${esc(r.student.conduct)}</strong> · Frekwencja: <strong>${r.attendance_pct ?? "–"}${r.attendance_pct === null ? "" : "%"}</strong></p>
   <h3>Uwagi</h3>${tbl(
     ["Data", "Treść"],
     r.remarks.map((x) => [esc(x.date), esc(x.text)]),
   )}</div>`);
};
H.repClass = async (d) => {
  const r = await api("report_class", d);
  show(
    `<h2>Zestawienie ocen klasy</h2>` +
      tbl(
        ["Uczeń", ...r.subjects.map(esc), "Średnia"],
        r.rows.map((x) => [esc(x.student), ...r.subjects.map((s) => x.finals[s] ?? "–"), x.avg ?? "–"]),
      ),
  );
};
H.repThreats = async () => {
  const r = await api("report_threats", { class_id: formVal("class_id") });
  show(
    `<h2>Zagrożenia ocenami niedostatecznymi</h2>` +
      (r
        .map(
          (x) => `<div class="doc"><h3>Informacja dla rodziców: ${esc(x.student)} (${esc(x.class)})</h3>
   <p>Uczeń jest zagrożony oceną niedostateczną z przedmiotów: <strong>${esc(x.subjects.join(", "))}</strong>.</p></div>`,
        )
        .join("") || `<p class="muted">Brak uczniów zagrożonych.</p>`),
  );
};
H.repHonors = async () => {
  const r = await api("report_honors");
  show(
    `<h2>Uczniowie wyróżnieni (świadectwo z paskiem)</h2>` +
      tbl(
        ["Uczeń", "Klasa", "Średnia", "Zachowanie"],
        r.map((x) => [esc(x.student), esc(x.class), x.avg, esc(x.conduct)]),
      ),
  );
};
H.repSchool = async () => {
  const r = await api("report_school");
  show(
    `<h2>Statystyki szkoły</h2>` +
      tbl(
        ["Klasa", "Uczniów", "Średnia ocen", "Frekwencja", "Liczba uwag"],
        r.map((x) => [
          esc(x.class),
          x.students,
          x.avg ?? "–",
          x.attendance_pct === null ? "–" : x.attendance_pct + "%",
          x.remarks,
        ]),
      ),
  );
};
H.sioExport = async () => {
  const r = await fetch("api.php?action=sio_export", { method: "POST" });
  if (!r.ok) return toast("Brak uprawnień do eksportu", 1);
  const a = Object.assign(document.createElement("a"), {
    href: URL.createObjectURL(await r.blob()),
    download: "sio-export.csv",
  });
  a.click();
  toast("Pobrano plik eksportu SIO");
};
