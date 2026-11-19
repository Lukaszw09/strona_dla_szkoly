"use strict";
async function vSchool() {
  const [cms, events, gallery, documents, meals] = await Promise.all([
    api("public_school"),
    api("public_events"),
    api("public_gallery"),
    api("public_documents"),
    api("public_meals"),
  ]);
  const section = (slug) => cms.sections.find((x) => x.slug === slug);
  const about = section("about");
  const offer = section("offer");
  const sport = section("sport");
  const contact = section("contact");
  const future = section("first-graders");
  const nav=document.querySelector(".school-nav");
  if(nav) nav.innerHTML=cms.menu.map(m=>`<a href="#${esc(m.slug)}">${esc(m.title)}</a>`).join("");
  return `<section class="school-hero" id="about" aria-labelledby="school-title">
    <div class="school-hero-copy"><p class="school-kicker">${esc(about?.title || "O szkole")}</p>
      <h1 id="school-title">ZSTI Gliwice</h1><p class="school-intro">${esc(about?.content || "")}</p>
      <div class="school-hero-actions"><a class="school-hero-link" href="#offer">Zobacz kierunki <span aria-hidden="true">→</span></a>
        <button class="sec" type="button" data-c="showLogin">Otwórz dziennik</button></div></div>
    <div class="school-hero-art" aria-hidden="true">
      <div class="school-hero-art-top"><span>Zespół Szkół</span><span>Gliwice</span></div>
      <div class="school-hero-art-mark">ZSTI</div>
      <div class="school-hero-art-bottom"><span>Technika</span><span>Sport</span></div>
    </div>
  </section>
  <section class="public-tools" aria-label="Wyszukiwarka i newsletter">
    <form class="row" data-h="publicSearch"><label>Szukaj na stronie<input name="q" minlength="2" required></label><button>Wyszukaj</button></form>
    <form class="row" data-h="newsletterSubscribe"><label>E-mail rodzica<input name="email" type="email" required></label>
      <label class="ck"><input type="checkbox" name="consent" value="1" required>Zgadzam się na otrzymywanie newslettera</label><button class="sec">Zapisz się</button></form>
  </section><div id="publicSearchResults"></div>
  <section class="school-section" id="feedback"><p class="school-kicker">Kontakt z redakcją</p><h2>Zgłoś błąd lub sugestię</h2>
    <form data-h="publicFeedback"><label>Imię i nazwisko<input name="name"></label><label>E-mail<input name="email" type="email"></label><label>Temat<input name="subject" required></label><label>Treść<textarea name="message" required minlength="10"></textarea></label><button>Wyślij zgłoszenie</button></form></section>
  <section class="school-section" id="offer"><div class="school-section-heading"><p class="school-kicker">Oferta edukacyjna</p><h2>${esc(offer?.title || "Kierunki")}</h2><p>${esc(offer?.content || "")}</p></div></section>
  <section class="school-section" id="sport"><p class="school-kicker">Edukacja i sport</p><h2>${esc(sport?.title || "Sport")}</h2><p>${esc(sport?.content || "")}</p></section>
  <section class="school-section" id="events"><p class="school-kicker">Dla rodziców</p><h2>Kalendarz wydarzeń</h2>
    ${tbl(["Data","Wydarzenie","Godzina","Miejsce"],events.map(e=>[esc(e.event_date+(e.end_date?" – "+e.end_date:"")),esc(e.title),esc(e.event_time||"–"),esc(e.location||"–")]))}</section>
  <section class="school-section" id="announcements"><p class="school-kicker">Aktualności</p><h2>Ogłoszenia</h2>
    ${cms.announcements.map(a=>`<article class="card"><h3>${esc(a.title)}</h3><p>${esc(a.content)}</p></article>`).join("")||'<p class="muted">Brak ogłoszeń.</p>'}</section>
  <section class="school-section" id="meals"><p class="school-kicker">Stołówka</p><h2>Jadłospis</h2>
    ${tbl(["Data","Posiłek","Opis"],meals.map(m=>[esc(m.menu_date),esc(m.title||"–"),esc(m.description||"–")]))}</section>
  <section class="school-section" id="gallery"><p class="school-kicker">Galeria</p><h2>Zdjęcia ze szkoły</h2>
    <div class="school-grid">${gallery.map(g=>`<article class="card"><img src="${esc(g.image_url)}" alt="${esc(g.title)}" loading="lazy"><h3>${esc(g.title)}</h3><p>${esc(g.description||"")}</p></article>`).join("")||'<p class="muted">Brak zdjęć.</p>'}</div></section>
  <section class="school-section" id="documents"><p class="school-kicker">Dokumenty</p><h2>Dokumenty szkoły</h2>
    ${tbl(["Nazwa","Kategoria","Plik"],documents.map(d=>[esc(d.title),esc(d.category||"–"),`<a href="${esc(d.file_url)}" target="_blank" rel="noopener">Pobierz</a>`]))}</section>
  <section class="school-section" id="first-graders"><p class="school-kicker">Rekrutacja</p><h2>${esc(future?.title || "Dla przyszłych pierwszoklasistów")}</h2><p>${esc(future?.content || "")}</p></section>
  <section class="school-section" id="contact"><p class="school-kicker">Jesteśmy w Gliwicach</p><h2>${esc(contact?.title || "Kontakt")}</h2><address>${esc(contact?.content || "")}</address></section>`;
}
H.publicSearch = async (d) => {
  const r = await api("public_search", d);
  $("#publicSearchResults").innerHTML = `<h2>Wyniki wyszukiwarki</h2>` +
    tbl(["Typ","Tytuł","Treść"],r.map(x=>[esc(x.type),esc(x.title),esc(x.text||"")])) || "<p>Brak wyników.</p>";
};
H.newsletterSubscribe = async (d) => {
  const r = await api("newsletter_subscribe", d);
  toast(r.message || "Zapisano do newslettera");
};
H.publicFeedback = async (d) => {
  await api("feedback_submit", d);
  toast("Zgłoszenie zostało wysłane");
};
