"use strict";
async function vParents() {
  const [p, l, s] = await Promise.all([api("parents_list"), api("links_list"), api("students_list")]);
  return (
    `<h2>Rodzice i opiekunowie</h2><h3>Powiązania z uczniami</h3>` +
    tbl(
      ["Rodzic", "Uczeń", "Relacja", "Kontakt", "Odbiór", "Ograniczenie", ""],
      l.map((x) => [
        esc(x.parent),
        esc(x.student),
        esc(x.relation),
        esc(x.contact),
        x.pickup ? "tak" : "nie",
        x.restricted ? '<span class="b s-nieobecny">zakaz dostępu</span>' : "–",
        btn("delLink", x.id, "Usuń", "bad"),
      ]),
    ) +
    `<h3>Powiąż rodzica z uczniem</h3><form data-h="saveLink"><fieldset><label>Rodzic<select name="parent_id">${opt(p, "id", "name")}</select></label><label>Uczeń<select name="student_id">${opt(s, "id", (x) => x.last + " " + x.first)}</select></label>
 <label>Relacja<input name="relation" placeholder="matka, ojciec, opiekun"></label><label>Kontakt<input name="contact"></label>
 <label class="ck"><input type="checkbox" name="pickup" checked>prawo do odbioru</label><label class="ck"><input type="checkbox" name="restricted">ograniczenie władzy rodzicielskiej (zakaz dostępu)</label></fieldset><div><button>Zapisz powiązanie</button></div></form>
 <h3>Dodaj konto rodzica</h3><form class="row" data-h="saveParent"><label>Imię i nazwisko<input name="name" required></label><label>E-mail<input name="email" type="email" required></label><label>Hasło początkowe<input name="password" minlength="8" required></label><button>Dodaj rodzica</button></form>`
  );
}
H.saveLink = async (d) => {
  await api("link_save", d);
  toast("Zapisano powiązanie");
  go("parents");
};
H.delLink = async (id) => {
  if (confirm("Usunąć powiązanie?")) {
    await api("link_delete", { id });
    go("parents");
  }
};
H.saveParent = async (d) => {
  await api("user_save", { ...d, role: "rodzic" });
  toast("Dodano rodzica");
  go("parents");
};
