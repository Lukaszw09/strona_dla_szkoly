"use strict";
async function vLog() {
  const l = await api("log_list");
  return (
    `<h2>Logi logowań i dostępu do danych</h2>` +
    tbl(
      ["Czas", "Użytkownik", "Zdarzenie", "Szczegóły", "IP"],
      l.map((x) => [esc(x.created), esc(x.email || "–"), esc(x.action), esc(x.detail), esc(x.ip)]),
    )
  );
}
