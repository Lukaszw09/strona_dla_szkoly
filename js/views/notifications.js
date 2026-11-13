"use strict";
async function vNotif() {
  const n = await api("notif_list");
  ME.unread = 0;
  head();
  return (
    `<h2>Powiadomienia</h2>` +
    tbl(
      ["Data", "Treść"],
      n.map((x) => [esc(x.created), esc(x.text)]),
    )
  );
}
