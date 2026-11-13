"use strict";
async function vTeachers() {
  const [t, s] = await Promise.all([api("teachers_list"), api("subjects_list")]);
  const ed = is("admin", "sekretariat");
  return (
    `<h2>Nauczyciele</h2>` +
    tbl(
      ["Imię i nazwisko", "E-mail", "Rola", "Przedmioty"],
      t.map((x) => [esc(x.name), esc(x.email), esc(x.role), esc(x.subjects)]),
    ) +
    (ed
      ? `<h3>Dodaj nauczyciela</h3><form data-h="saveTeacher"><fieldset><label>Imię i nazwisko<input name="name" required></label><label>E-mail<input name="email" type="email" required></label>
 <label>Hasło początkowe<input name="password" minlength="8" required></label><label>Rola<select name="role"><option>nauczyciel</option><option>wychowawca</option></select></label></fieldset>
 <fieldset><legend>Przedmioty</legend>${s.map((x) => `<label class="ck"><input type="checkbox" name="subjects" value="${x.id}">${esc(x.name)}</label>`).join("")}</fieldset><div><button>Dodaj nauczyciela</button></div></form>`
      : "")
  );
}
H.saveTeacher = async (d, f) => {
  d.subjects = checked(f, "subjects");
  await api("user_save", d);
  toast("Dodano nauczyciela");
  go("teachers");
};
