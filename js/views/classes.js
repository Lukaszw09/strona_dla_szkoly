"use strict";
async function vClasses() {
  const c = await api("classes_list");
  const ed = is("admin", "sekretariat");
  let f = "";
  if (ed) {
    const t = (await api("teachers_list")).filter((x) => x.role == "wychowawca");
    f = `<h3>Dodaj / edytuj klasę</h3><form class="row" data-h="saveClass"><input type="hidden" name="id"><label>Nazwa<input name="name" required></label><label>Wychowawca<select name="homeroom_id"><option value="">–</option>${opt(t, "id", "name")}</select></label><button>Zapisz klasę</button></form>`;
    CACHE.c = c;
  }
  return (
    `<h2>Klasy</h2>` +
    tbl(
      ["Klasa", "Wychowawca", "Liczba uczniów", ...(ed ? [""] : [])],
      c.map((x) => [
        esc(x.name),
        esc(x.homeroom),
        x.n,
        ...(ed ? [btn("editClass", x.id, "Edytuj", "sec")] : []),
      ]),
    ) +
    f
  );
}
H.editClass = (id) =>
  fill(
    $("form[data-h=saveClass]"),
    CACHE.c.find((x) => x.id == id),
  );
H.saveClass = async (d) => {
  await api("class_save", d);
  toast("Zapisano klasę");
  go("classes");
};
