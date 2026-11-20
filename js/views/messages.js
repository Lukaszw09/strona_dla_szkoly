"use strict";
async function vMessages() {
  const [recipients,inbox,sent]=await Promise.all([api("message_recipients"),api("message_inbox"),api("message_sent")]);
  return `<h2>Komunikacja</h2>
  <h3>Nowa wiadomość</h3><form data-h="sendMessage"><label>Odbiorca<select name="recipient_id">${opt(recipients,"id",x=>x.name+" ("+x.role+")")}</select></label><label>Temat<input name="subject" required></label><label>Treść<textarea name="body" required></textarea></label><button>Wyślij wiadomość</button></form>
  <h3>Odebrane</h3>${tbl(["Data","Nadawca","Temat","Treść",""],inbox.map(m=>[esc(m.created),esc(m.sender),esc(m.subject),esc(m.body),m.is_read?"":"Przeczytaj"]))}
  <h3>Wysłane</h3>${tbl(["Data","Odbiorca","Temat","Treść"],sent.map(m=>[esc(m.created),esc(m.recipient),esc(m.subject),esc(m.body)]))}`;
}
H.sendMessage=async d=>{await api("message_send",d);toast("Wiadomość wysłana");go("messages")};
