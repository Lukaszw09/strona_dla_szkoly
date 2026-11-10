"use strict";
async function vStudents() {
  const cl = await api("classes_list");
  CACHE.cl = cl;
  const ed = is("admin", "sekretariat");
  setTimeout(() => H.searchStu({}), 0);
  let f = "";
  if (ed)
    f = `<h3>Dodaj / edytuj ucznia</h3><form data-h="saveStu"><input type="hidden" name="id"><fieldset>
 <label>Imię<input name="first" required></label><label>Nazwisko<input name="last" required></label><label>Data urodzenia<input type="date" name="birth"></label>
 <label>PESEL<input name="pesel" maxlength="11" pattern="\\d{11}"></label><label>Adres<input name="address"></label><label>Klasa (tylko nowy uczeń)<select name="class_id"><option value="">–</option>${opt(cl, "id", "name")}</select></label></fieldset>
 <div><button>Zapisz ucznia</button></div></form>
 <h3>Przenieś ucznia do innej klasy</h3><form class="row" data-h="moveStu"><label>Uczeń<select name="student_id" id="moveSel"></select></label><label>Nowa klasa<select name="class_id">${opt(cl, "id", "name")}</select></label><label>Data zmiany<input type="date" name="date" value="${today()}" required></label><button>Przenieś</button></form>`;
  return `<h2>Uczniowie</h2><form class="row" data-h="searchStu"><label>Szukaj (nazwisko, klasa${ed ? ", PESEL" : ""})<input name="q"></label><label>Klasa<select name="class_id"><option value="">wszystkie</option>${opt(cl, "id", "name")}</select></label><button>Szukaj</button></form><div id="stuList"></div>${f}`;
}
H.searchStu = async (d) => {
  const r = await api("students_list", d);
  CACHE.stu = r;
  const ed = is("admin", "sekretariat");
  $("#stuList").innerHTML = tbl(
    ["Nazwisko i imię", "Klasa", ...(ed ? ["Data ur.", "PESEL", "Adres", ""] : [])],
    r.map((s) => [
      esc(s.last + " " + s.first),
      esc(s.class),
      ...(ed ? [esc(s.birth), esc(s.pesel), esc(s.address), btn("editStu", s.id, "Edytuj", "sec")] : []),
    ]),
  );
  const m = $("#moveSel");
  if (m && !d.q && !d.class_id)
    m.innerHTML = opt(r, "id", (s) => s.last + " " + s.first + " (" + (s.class || "-") + ")");
};
H.editStu = (id) =>
  fill(
    $("form[data-h=saveStu]"),
    CACHE.stu.find((s) => s.id == id),
  );
H.saveStu = async (d, f) => {
  await api("student_save", d);
  toast("Zapisano ucznia");
  f.reset();
  go("students");
};
H.moveStu = async (d) => {
  await api("student_move", d);
  toast("Uczeń przeniesiony – historia zachowana");
  go("students");
};
