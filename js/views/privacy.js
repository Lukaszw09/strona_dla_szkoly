"use strict";
async function vPrivacy() {
  const consents=await api("privacy_consents");
  return `<h2>RODO i prywatność</h2><p>W aplikacji udostępniamy użytkownikowi tylko dane niezbędne do jego roli. PESEL i dane adresowe ucznia są ukrywane przed rodzicami i uczniami.</p>
  <h3>Historia zgód</h3>${tbl(["Data","Cel","Zgoda"],consents.map(c=>[esc(c.created_at),esc(c.purpose),c.consent?"tak":"nie"]))}
  <h3>Zarządzanie zgodami</h3><form data-h="saveConsent"><label>Cel zgody<input name="purpose" value="Newsletter i komunikacja" required></label><label>Treść zgody<input name="consent_text" value="Zgoda na przetwarzanie danych w zakresie wskazanym w formularzu"></label><label class="ck"><input type="checkbox" name="consent" value="1" checked>Wyrażam zgodę</label><button>Zapisz zgodę</button></form>
  <p><button class="sec" data-c="exportPrivacy">Eksportuj moje dane (JSON)</button></p>
  <p><button class="sec" data-c="feedback">Zgłoś błąd lub sugestię</button></p><div id="feedbackBox"></div>`;
}
H.saveConsent=async d=>{await api("privacy_consent_save",{...d,consent:d.consent?1:0});toast("Zapisano zgodę");go("privacy")};
H.feedback=()=>{$("#feedbackBox").innerHTML=`<form data-h="submitFeedback"><label>Imię i nazwisko<input name="name"></label><label>E-mail<input name="email" type="email"></label><label>Temat<input name="subject" required></label><label>Treść<textarea name="message" required></textarea></label><button>Wyślij zgłoszenie</button></form>`};
H.submitFeedback=async d=>{await api("feedback_submit",d);toast("Zgłoszenie wysłane");go("privacy")};
H.exportPrivacy=async()=>{
 const r=await api("privacy_export");
 const blob=new Blob([JSON.stringify(r,null,2)],{type:"application/json"});
 const a=document.createElement("a");a.href=URL.createObjectURL(blob);a.download="moje-dane.json";a.click();URL.revokeObjectURL(a.href);
 toast("Pobrano eksport danych");
};
