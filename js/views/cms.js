"use strict";
async function vCms() {
  const [sections,menu,events,gallery,documents,meals,announcements,feedback]=await Promise.all([
    api("cms_sections_list"),api("cms_menu_list"),api("cms_events"),api("cms_gallery"),
    api("cms_documents"),api("cms_meals"),api("cms_announcements"),api("feedback_list")
  ]);
  return `<h2>CMS strony szkoły</h2>
  <section><h3>Strony i sekcje</h3>${tbl(["Slug","Tytuł","Publikacja"],sections.map(s=>[esc(s.slug),esc(s.title),s.published?"tak":"nie"]))}
  <form data-h="saveCmsSection"><input type="hidden" name="id"><label>Identyfikator (slug)<input name="slug" required></label><label>Tytuł<input name="title" required></label>
  <label>Treść<textarea name="content" required></textarea></label><label>Kolejność<input type="number" name="section_order" value="0"></label>
  <label class="ck"><input type="checkbox" name="published" value="1" checked>Opublikowana</label><button>Zapisz sekcję</button></form></section>
  <section><h3>Menu</h3>${tbl(["Pozycja","Adres","Publikacja",""],menu.map(m=>[esc(m.title),esc(m.slug),m.published?"tak":"nie",btn("deleteCmsMenu",m.id,"Usuń","bad")]))}
  <form class="row" data-h="saveCmsMenu"><label>Nazwa<input name="title" required></label><label>Slug<input name="slug" required></label><label>Kolejność<input type="number" name="menu_order" value="0"></label><label class="ck"><input type="checkbox" name="published" value="1" checked>Widoczne</label><button>Dodaj pozycję</button></form></section>
  <section><h3>Ogłoszenia</h3>${tbl(["Tytuł","Publikacja"],announcements.map(a=>[esc(a.title),a.published?"tak":"nie"]))}
  <form data-h="saveAnnouncement"><label>Tytuł<input name="title" required></label><label>Treść<textarea name="content" required></textarea></label><label>Data<input name="published_at" type="datetime-local"></label><label class="ck"><input type="checkbox" name="published" value="1" checked>Opublikowane</label><button>Zapisz ogłoszenie</button></form></section>
  <section><h3>Kalendarz wydarzeń</h3>${tbl(["Data","Wydarzenie","Miejsce"],events.map(e=>[esc(e.event_date),esc(e.title),esc(e.location||"–")]))}
  <form class="row" data-h="saveEvent"><label>Nazwa<input name="title" required></label><label>Data<input type="date" name="event_date" required></label><label>Godzina<input type="time" name="event_time"></label><label>Do dnia<input type="date" name="end_date"></label><label>Miejsce<input name="location"></label><button>Zapisz wydarzenie</button></form></section>
  <section><h3>Galeria</h3>${tbl(["Tytuł","Adres"],gallery.map(g=>[esc(g.title),esc(g.image_url)]))}
  <form class="row" data-h="saveGallery"><label>Tytuł<input name="title" required></label><label>Adres obrazu /upload*<input name="image_url" required></label><label>Opis<input name="description"></label><button>Dodaj zdjęcie</button></form></section>
  <section><h3>Dokumenty</h3>${tbl(["Nazwa","Kategoria"],documents.map(d=>[esc(d.title),esc(d.category||"–")]))}
  <form class="row" data-h="saveDocument"><label>Nazwa<input name="title" required></label><label>Adres pliku<input name="file_url" required></label><label>Kategoria<input name="category"></label><button>Dodaj dokument</button></form></section>
  <section><h3>Jadłospis</h3>${tbl(["Data","Posiłek"],meals.map(m=>[esc(m.menu_date),esc(m.title||"–")]))}
  <form class="row" data-h="saveMeal"><label>Data<input type="date" name="menu_date" required></label><label>Posiłek<input name="title"></label><label>Opis<input name="description"></label><button>Zapisz jadłospis</button></form></section>
  <section><h3>Błędy i sugestie</h3>${tbl(["Data","Temat","Status"],feedback.map(f=>[esc(f.created_at),esc(f.subject),esc(f.status)]))}</section>
  <section><h3>Migracja danych</h3><p class="muted">Wklej dane uczniów w formacie JSON: [{"first":"Jan","last":"Nowak","class":"1A","pesel":"..."}]</p>
  <form data-h="importMigration"><label>Rekordy JSON<textarea name="records" required></textarea></label><button>Zaimportuj dane</button></form></section>`;
}
H.saveCmsSection=async d=>{await api("cms_section_save",{...d,published:d.published?1:0});toast("Zapisano sekcję");go("cms")};
H.saveCmsMenu=async d=>{await api("cms_menu_save",{...d,published:d.published?1:0});toast("Zapisano pozycję menu");go("cms")};
H.deleteCmsMenu=async id=>{if(confirm("Usunąć pozycję menu?")){await api("cms_menu_delete",{id});go("cms")}};
H.saveAnnouncement=async d=>{await api("cms_announcement_save",{...d,published:d.published?1:0});toast("Zapisano ogłoszenie");go("cms")};
H.saveEvent=async d=>{await api("cms_event_save",d);toast("Zapisano wydarzenie");go("cms")};
H.saveGallery=async d=>{await api("cms_gallery_save",d);toast("Zapisano zdjęcie");go("cms")};
H.saveDocument=async d=>{await api("cms_document_save",d);toast("Zapisano dokument");go("cms")};
H.saveMeal=async d=>{await api("cms_meal_save",d);toast("Zapisano jadłospis");go("cms")};
H.importMigration=async d=>{
 let records;
 try{records=JSON.parse(d.records)}catch(e){return toast("Niepoprawny format JSON",1)}
 if(!Array.isArray(records)) return toast("Rekordy muszą być tablicą",1);
 const r=await api("migration_import",{records});
 toast("Zaimportowano "+r.imported+" rekordów"+(r.errors.length?"; błędów: "+r.errors.length:""));
};
