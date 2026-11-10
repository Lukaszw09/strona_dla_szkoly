"use strict";
async function vUsers() {
  const u = await api("users_list");
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
    `<h3>Dodaj / edytuj użytkownika</h3><form data-h="saveUser"><input type="hidden" name="id"><fieldset><label>Imię i nazwisko<input name="name" required></label><label>E-mail<input name="email" type="email" required></label>
 <label>Rola<select name="role">${["admin", "dyrektor", "sekretariat", "nauczyciel", "wychowawca", "rodzic", "uczen"].map((r) => `<option>${r}</option>`).join("")}</select></label>
 <label>Hasło (puste = bez zmian)<input name="password" minlength="8"></label><label class="ck"><input type="checkbox" name="unlock">odblokuj konto</label></fieldset><div><button>Zapisz użytkownika</button></div></form>`
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
