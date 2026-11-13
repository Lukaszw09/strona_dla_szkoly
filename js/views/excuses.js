"use strict";
async function vExcuses() {
  const e = await api("excuses_list");
  let form = "";
  if (is("rodzic")) {
    const kids = await api("students_list");
    form = `<h3>Zgłoś nieobecność dziecka</h3><form class="row" data-h="submitExcuse"><label>Dziecko<select name="student_id">${opt(kids, "id", (k) => k.first + " " + k.last)}</select></label>
 <label>Od<input type="date" name="from" value="${today()}" required></label><label>Do<input type="date" name="to" value="${today()}" required></label>
 <label>Powód<input name="reason" placeholder="np. choroba" required></label><button>Wyślij zgłoszenie</button></form>`;
  }
  return (
    `<h2>Usprawiedliwienia</h2>${form}` +
    tbl(
      ["Uczeń", "Okres", "Powód", "Status", ""],
      e.map((x) => [
        esc(x.student),
        `${x.date_from} – ${x.date_to}`,
        esc(x.reason),
        badge(x.status),
        x.status == "oczekuje" && is("wychowawca", "admin")
          ? btn("decide", x.id + ":zatwierdzone", "Zatwierdź") +
            " " +
            btn("decide", x.id + ":odrzucone", "Odrzuć", "bad")
          : "",
      ]),
    )
  );
}
H.submitExcuse = async (d) => {
  await api("excuse_submit", d);
  toast("Zgłoszenie wysłane do wychowawcy");
  go("excuses");
};
H.decide = async (a) => {
  const [i, d] = a.split(":");
  await api("excuse_decide", { id: i, decision: d });
  toast("Zapisano decyzję");
  go("excuses");
};
