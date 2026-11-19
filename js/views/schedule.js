"use strict";
const LESSON_TIMES = [
  "7:10–7:55",
  "8:00–8:45",
  "8:50–9:35",
  "9:45–10:30",
  "10:40–11:25",
  "11:35–12:20",
  "12:45–13:30",
  "13:35–14:20",
  "14:25–15:10",
  "15:15–16:00",
  "16:05–16:50",
];
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
  for (let n = 0; n < LESSON_TIMES.length; n++)
    rows.push([
      `${n}<br><span class="muted">${LESSON_TIMES[n]}</span>`,
      ...DAYS.map(
        (_, i) =>
          p
            .filter((x) => x.lesson_no == n && x.weekday == i + 1)
            .map(
              (x) =>
                `${esc(x.subject)}<br><span class="muted">${esc(x.teacher)}</span> ${ed ? `<button type="button" class="plan-delete" data-c="delSlot" data-a="${esc(x.id)}" aria-label="Usuń lekcję: ${esc(x.subject)} (${esc(x.teacher)})" title="Usuń lekcję"><svg aria-hidden="true" focusable="false" viewBox="0 0 16 16"><path d="M3 4h10M6 4V2.8h4V4m1.5 0-.6 9H5.1l-.6-9m3 2v4m3-4v4" /></svg></button>` : ""}`,
            )
            .join("") || "",
      ),
    ]);
  let f = "";
  if (ed) {
    const a = (await api("assignments_list")).filter((x) => x.class_id == d.class_id);
    f = `<h3>Dodaj lekcję do planu</h3><form class="row" data-h="saveSlot"><label>Przedmiot<select name="assignment_id">${opt(a, "id", (x) => x.subject + " – " + x.teacher)}</select></label><label>Dzień<select name="weekday">${DAYS.map((x, i) => `<option value="${i + 1}">${x}</option>`).join("")}</select></label><label>Nr lekcji<input type="number" name="lesson_no" min="0" max="10" value="1" required></label><button>Dodaj do planu</button></form>`;
  }
  $("#plan").innerHTML = tbl(["Lekcja / godzina", ...DAYS], rows) + f;
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
