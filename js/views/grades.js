"use strict";
// ---------- oceny i uwagi ----------
async function vGrades() {
  const as = await api("my_assignments");
  return `<h2>Oceny i uwagi</h2>
 <form class="row" data-h="showGrades"><label>Klasa i przedmiot<select name="assignment_id">${opt(as, "id", (a) => a.class + " – " + a.subject)}</select></label><button>Pokaż uczniów</button></form><div id="gradeBox"></div>`;
}
H.showGrades = async (d) => {
  const r = await api("grades_for_assignment", d);
  GRADE_CTX = { subject_id: r.subject_id, assignment_id: d.assignment_id };
  const kinds = ["cząstkowa", "śródroczna", "roczna"];
  const rows = r.students.map((s) => [
    esc(s.last + " " + s.first),
    s.grades
      .map(
        (g) =>
          `<span class="b" title="${esc(g.kind)} ${esc(g.date)}">${g.value}${g.kind == "roczna" ? " (r)" : g.kind == "śródroczna" ? " (ś)" : ""}</span>
           <button type="button" class="sm sec" data-c="editGrade" data-a="${g.id}:${g.value}:${g.kind}:${s.id}">Edytuj</button>
           <button type="button" class="sm bad" data-c="deleteGrade" data-a="${g.id}:${s.id}">Usuń</button>`,
      )
      .join(" ") || "–",
    `<form class="row" data-h="addGrade"><input type="hidden" name="student_id" value="${s.id}">
      <select name="value">${[6, 5, 4, 3, 2, 1].map((v) => `<option>${v}</option>`).join("")}</select>
      <select name="kind">${kinds.map((k) => `<option>${k}</option>`).join("")}</select><button class="sm">Dodaj ocenę</button></form>`,
    `<form class="row" data-h="addRemark"><input type="hidden" name="student_id" value="${s.id}"><input name="text" placeholder="Uwaga" required><button class="sm sec">Dodaj uwagę</button></form>`,
  ]);
  $("#gradeBox").innerHTML = tbl(["Uczeń", "Oceny", "Nowa ocena", "Uwaga"], rows);
};
H.addGrade = async (d) => {
  await api("grade_save", { ...d, subject_id: GRADE_CTX.subject_id });
  toast("Zapisano ocenę");
  H.showGrades({ assignment_id: GRADE_CTX.assignment_id });
};
H.addRemark = async (d) => {
  await api("remark_save", d);
  toast("Zapisano uwagę");
};
H.editGrade = async (a) => {
  const [id,value,kind,studentId]=a.split(":");
  const newValue=prompt("Nowa ocena (1-6)",value);
  if(!newValue) return;
  const n=Number(newValue);
  if(!Number.isInteger(n)||n<1||n>6) return toast("Ocena musi być liczbą od 1 do 6",1);
  await api("grade_update",{id,value:n,kind});
  toast("Zmieniono ocenę");
  H.showGrades({assignment_id:GRADE_CTX.assignment_id});
};
H.deleteGrade = async (a) => {
  const [id]=a.split(":");
  if(!confirm("Usunąć tę ocenę?")) return;
  await api("grade_delete",{id});
  toast("Usunięto ocenę");
  H.showGrades({assignment_id:GRADE_CTX.assignment_id});
};
