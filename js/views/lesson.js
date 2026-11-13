"use strict";
async function vLesson() {
  const as = await api("my_assignments");
  return `<h2>Lekcja i frekwencja</h2><p class="muted">Widzisz tylko klasy i przedmioty przypisane do Ciebie.</p>
 <form class="row" data-h="openLesson"><label>Klasa i przedmiot<select name="assignment_id">${opt(as, "id", (a) => a.class + " – " + a.subject)}</select></label>
 <label>Data<input type="date" name="date" value="${today()}" required></label><label>Nr lekcji<input type="number" name="lesson_no" min="1" max="10" value="1"></label><button>Otwórz lekcję</button></form><div id="lessonBox"></div>
 <h3>Dodaj własną lekcję</h3><p class="muted">Własna lekcja zapisuje się na stałe i pojawia się na liście powyżej. Plan lekcji możesz zostawić pusty.</p>
 <form class="row" data-h="saveCustomLesson"><label>Nazwa lekcji<input name="name" maxlength="60" placeholder="np. Koło naukowe" required></label>
 <label>Klasa<select name="class_id">${opt(await api("classes_list"), "id", "name")}</select></label>
 <label>Dzień (opcjonalnie)<select name="weekday"><option value="">–</option>${DAYS.map((x, i) => `<option value="${i + 1}">${x}</option>`).join("")}</select></label>
 <label>Nr lekcji<input type="number" name="lesson_no" min="1" max="10"></label><button>Dodaj lekcję</button></form>`;
}
H.saveCustomLesson = async (d) => {
  await api("custom_lesson_save", d);
  toast("Dodano własną lekcję");
  go("lesson");
};
H.openLesson = async (d) => {
  const r = await api("lesson_open", d);
  LESSON = d;
  $("#lessonBox").innerHTML =
    `<form data-h="saveLesson"><h3>${esc(r.assignment.class)} – ${esc(r.assignment.subject)}, ${esc(d.date)}, lekcja ${esc(d.lesson_no)}</h3>
 <label>Temat lekcji<input name="topic" value="${esc(r.topic)}" required></label>
 ${tbl(
   ["Uczeń", "Status"],
   r.students.map((s) => [
     esc(s.last + " " + s.first),
     `<select name="st_${s.id}">${ST.map((x) => `<option${(s.status || "obecny") == x ? " selected" : ""}>${x}</option>`).join("")}</select>`,
   ]),
 )}
 <div><button>Zapisz temat i frekwencję</button> <button type="button" class="sec" data-c="allPresent">Wszyscy obecni</button></div></form>`;
};
H.allPresent = () => document.querySelectorAll("[name^=st_]").forEach((s) => (s.value = "obecny"));
H.saveLesson = async (d) => {
  const att = {};
  for (const k in d) if (k.startsWith("st_")) att[k.slice(3)] = d[k];
  await api("lesson_save", { ...LESSON, topic: d.topic, attendance: att });
  toast("Zapisano temat i frekwencję");
};
