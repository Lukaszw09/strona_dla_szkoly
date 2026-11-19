"use strict";
async function vTraining() {
  const tutorials={
    admin:["Administrator","Zarządzanie użytkownikami i rolami","Blokady kont i odblokowanie","Logi dostępu i bezpieczeństwo","CMS oraz eksport danych"],
    dyrektor:["Dyrektor","Raporty szkoły i statystyki","Lista wyróżnionych i zagrożenia","Plan wdrożenia i wsparcie","Zgody RODO i dostęp do logów"],
    sekretariat:["Sekretariat","Dodawanie uczniów i klas","Powiązania rodziców","CMS, galeria, dokumenty i kalendarz","Eksport danych i raporty"],
    nauczyciel:["Nauczyciel","Wybór przydziału","Wpis tematu i frekwencji","Wystawianie i edycja ocen","Druk dziennika i komunikaty"],
    wychowawca:["Wychowawca","Wpisy tematu i obecności","Zatwierdzanie usprawiedliwień","Oceny zachowania i arkusz ucznia","Zestawienia i świadectwa"],
    rodzic:["Rodzic","Logowanie i reset hasła","Przeglądanie ocen i frekwencji","Zgłaszanie usprawiedliwień","Wiadomości i newsletter"],
    uczen:["Uczeń","Logowanie i bezpieczeństwo konta","Przeglądanie własnych ocen","Historia własnej frekwencji","Wiadomości do szkoły"]
  };
  const role=ME.role;
  const cards=tutorials[role].slice(1).map((step,i)=>`<article class="card training-step"><span class="school-award"><strong>Krok ${i+1}</strong></span><h3>${esc(step)}</h3><p>${esc(role==="rodzic"?"Dane dostępne są tylko dla powiązanego ucznia.":"Pracuj w swoim obszarze uprawnień i zapisuj zmiany przyciskiem formularza.")}</p></article>`).join("");
  return `<h2>Szkolenie obsługi systemu</h2><p class="muted">Krótkie instrukcje przygotowane dla roli: <strong>${esc(tutorials[role][0])}</strong>.</p>
  <ol class="training-list">${tutorials[role].slice(1).map(step=>`<li>${esc(step)}</li>`).join("")}</ol>
  <div class="school-grid">${cards}</div>
  <p class="noprint"><button class="sec" data-c="print">Drukuj instrukcję</button></p>`;
}
