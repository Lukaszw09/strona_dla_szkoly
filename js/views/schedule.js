"use strict";
async function vSchedule() {
  const c = await api("classes_list");
  CACHE.cl = c;
  setTimeout(() => c[0] && H.showPlan({ class_id: c[0].id }), 0);
  return `<h2>Plan lekcji</h2><form class="row" data-h="showPlan"><label>Klasa<select name="class_id">${opt(c, "id", "name")}</select></label><button>Pokaż plan</button></form><div id="plan"></div>`;
}
H.showPlan = async (d) => {
  CACHE.cid = d.class_id;
  const p = await api("schedule_list", { class_id: d.class_id });
  const ed = is("admin", "dyrektor", "sekretariat");
  let rows = [];
  for (let n = 1; n <= 8; n++)
    rows.push([
      n,
      ...DAYS.map(
        (_, i) =>
          p
            .filter((x) => x.lesson_no == n && x.weekday == i + 1)
            .map(
              (x) =>
                `${esc(x.subject)}<br><span class="muted">${esc(x.teacher)}</span> ${ed ? btn("delSlot", x.id, "×", "bad") : ""}`,
            )
            .join("") || "",
      ),
    ]);
  let f = "";
  if (ed) {
    const a = (await api("assignments_list")).filter((x) => x.class_id == d.class_id);
    f = `<h3>Dodaj lekcję do planu</h3><form class="row" data-h="saveSlot"><label>Przedmiot<select name="assignment_id">${opt(a, "id", (x) => x.subject + " – " + x.teacher)}</select></label><label>Dzień<select name="weekday">${DAYS.map((x, i) => `<option value="${i + 1}">${x}</option>`).join("")}</select></label><label>Nr lekcji<input type="number" name="lesson_no" min="1" max="10" value="1"></label><button>Dodaj do planu</button></form>`;
  }
  $("#plan").innerHTML = tbl(["Lekcja", ...DAYS], rows) + f;
};
H.saveSlot = async (d) => {
  await api("schedule_save", d);
  toast("Dodano do planu");
  H.showPlan({ class_id: CACHE.cid });
};
H.delSlot = async (id) => {
  await api("schedule_delete", { id });
  H.showPlan({ class_id: CACHE.cid });
};
