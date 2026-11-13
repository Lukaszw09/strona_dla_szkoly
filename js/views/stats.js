"use strict";
let STATS = [];
async function vStats() {
  STATS = await api("stats");
  setTimeout(() => H.filterStats({ th: 75 }), 0);
  return `<h2>Statystyki frekwencji</h2><p class="muted">Frekwencja = (obecny + spóźniony) / wszystkie wpisy.</p>
 <form class="row" data-h="filterStats"><label>Pokaż uczniów z frekwencją poniżej (%)<input type="number" name="th" min="0" max="100" value="75"></label><button>Filtruj</button></form><div id="st"></div>`;
}
H.filterStats = (d) => {
  const th = +d.th;
  const rows = STATS.filter((s) => s.pct !== null && s.pct < th);
  $("#st").innerHTML =
    `<p><strong>${rows.length}</strong> uczniów poniżej progu ${th}%</p>` +
    tbl(
      ["Uczeń", "Klasa", "Frekwencja", "Obecny", "Spóźniony", "Nieobecny", "Usprawiedl.", "Zwolniony"],
      rows.map((s) =>
        Object.assign(
          [esc(s.last + " " + s.first), esc(s.class), s.pct + "%", s.ob, s.sp, s.nb, s.us, s.zw],
          { cls: "low" },
        ),
      ),
    ) +
    `<h3>Wszyscy uczniowie</h3>` +
    tbl(
      ["Uczeń", "Klasa", "Frekwencja"],
      STATS.map((s) => [esc(s.last + " " + s.first), esc(s.class), s.pct === null ? "–" : s.pct + "%"]),
    );
};
