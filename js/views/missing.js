"use strict";
async function vMissing() {
  const r = await api("missing");
  return (
    `<h2>Braki we frekwencji (ostatnie 7 dni)</h2><p class="muted">Lekcje z planu, na których nauczyciel nie uzupełnił frekwencji.</p>` +
    tbl(
      ["Data", "Lekcja", "Klasa", "Przedmiot", "Nauczyciel"],
      r.map((x) => [x.date, x.lesson_no, esc(x.class), esc(x.subject), esc(x.teacher)]),
    )
  );
}
