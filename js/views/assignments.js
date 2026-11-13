"use strict";
async function vAssign() {
  const [a, t, c, s] = await Promise.all([
    api("assignments_list"),
    api("teachers_list"),
    api("classes_list"),
    api("subjects_list"),
  ]);
  const ed = is("admin", "dyrektor");
  return (
    `<h2>Przydziały nauczyciel – klasa – przedmiot</h2>` +
    tbl(
      ["Klasa", "Przedmiot", "Nauczyciel", ...(ed ? [""] : [])],
      a.map((x) => [
        esc(x.class),
        esc(x.subject),
        esc(x.teacher),
        ...(ed ? [btn("delAssign", x.id, "Usuń", "bad")] : []),
      ]),
    ) +
    (ed
      ? `<h3>Nowy przydział</h3><form class="row" data-h="saveAssign"><label>Nauczyciel<select name="teacher_id">${opt(t, "id", "name")}</select></label><label>Klasa<select name="class_id">${opt(c, "id", "name")}</select></label><label>Przedmiot<select name="subject_id">${opt(s, "id", "name")}</select></label><button>Przypisz</button></form>`
      : "")
  );
}
H.saveAssign = async (d) => {
  await api("assignment_save", d);
  toast("Dodano przydział");
  go("assign");
};
H.delAssign = async (id) => {
  if (confirm("Usunąć przydział wraz z jego pozycjami w planie?")) {
    await api("assignment_delete", { id });
    go("assign");
  }
};
