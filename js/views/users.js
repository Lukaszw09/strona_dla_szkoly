"use strict";
async function vUsers() {
  const [u, students] = await Promise.all([api("users_list"), api("students_list")]);
  CACHE.u = u;
  return (
    `<h2>Użytkownicy i role</h2>` +
    tbl(
      ["Imię i nazwisko", "E-mail", "Rola", "Konto", ""],
      u.map((x) => [
        esc(x.name),
        esc(x.email),
        esc(x.role),
        x.locked ? '<span class="b s-nieobecny">zablokowane</span>' : "aktywne",
        btn("editUser", x.id, "Edytuj", "sec"),
      ]),
    ) +
    `<section class="user-management" aria-labelledby="userManagementTitle"><h3 id="userManagementTitle">Dodaj konto lub zarządzaj użytkownikiem</h3><p class="muted">Administrator może utworzyć konto dla każdej roli. Podaj użytkownikowi wybrany e-mail i hasło początkowe.</p><form data-h="saveUser"><input type="hidden" name="id"><fieldset><label>Imię i nazwisko<input name="name" required></label><label>E-mail<input name="email" type="email" required></label>
 <label>Rola<select name="role">${["admin", "dyrektor", "sekretariat", "nauczyciel", "wychowawca", "rodzic", "uczen"].map((r) => `<option>${r}</option>`).join("")}</select></label>
 <label>Powiązany uczeń (wymagane dla roli uczeń)<select name="student_id"><option value="">– nie dotyczy –</option>${students.map((student) => `<option value="${student.id}">${esc(student.last)} ${esc(student.first)}${student.class ? ` (${esc(student.class)})` : ""}</option>`).join("")}</select></label>
 <label>Hasło początkowe<input name="password" minlength="8" autocomplete="new-password" placeholder="Co najmniej 8 znaków"></label><label class="ck"><input type="checkbox" name="unlock">odblokuj konto</label></fieldset><p class="muted">Przy edycji konta zostaw hasło puste, aby go nie zmieniać.</p><div><button>Zapisz użytkownika</button></div></form></section>`
  );
}
H.editUser = (id) =>
  fill(
    $("form[data-h=saveUser]"),
    CACHE.u.find((x) => x.id == id),
  );
H.saveUser = async (d) => {
  await api("user_save", d);
  toast("Zapisano użytkownika");
  go("users");
};
