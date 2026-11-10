"use strict";
async function vSubjects() {
  const s = await api("subjects_list");
  return (
    `<h2>Przedmioty</h2>` +
    tbl(
      ["Przedmiot"],
      s.map((x) => [esc(x.name)]),
    ) +
    (is("admin")
      ? `<form class="row" data-h="saveSubj"><label>Nowy przedmiot<input name="name" required></label><button>Dodaj</button></form>`
      : "")
  );
}
H.saveSubj = async (d) => {
  await api("subject_save", d);
  toast("Dodano przedmiot");
  go("subjects");
};
