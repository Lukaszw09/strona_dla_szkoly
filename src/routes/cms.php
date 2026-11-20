<?php
// CMS strony publicznej, newsletter, kalendarz i komunikaty

function act_public_school() {
    $sections = all("SELECT * FROM cms_sections WHERE published=1 ORDER BY section_order,title");
    $menu = all("SELECT * FROM cms_menu WHERE published=1 ORDER BY menu_order,title");
    $announcements = all("SELECT id,title,content,published_at FROM announcements WHERE published=1 ORDER BY published_at DESC,id DESC");
    out(['sections' => $sections, 'menu' => $menu, 'announcements' => $announcements]);
}

function act_public_search() {
    $q = trim(g('q', ''));
    if (mb_strlen($q) < 2)
        out([]);
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($q)) . '%';
    $sql = "SELECT 'sekcja' type,title,slug url,content text FROM cms_sections WHERE published=1 AND (lower(title) LIKE ? ESCAPE '\\' OR lower(content) LIKE ? ESCAPE '\\')
        UNION ALL SELECT 'ogloszenie',title,CAST(id AS TEXT),content FROM announcements WHERE published=1 AND (lower(title) LIKE ? ESCAPE '\\' OR lower(content) LIKE ? ESCAPE '\\')
        UNION ALL SELECT 'wydarzenie',title,event_date,description FROM events WHERE lower(title) LIKE ? ESCAPE '\\' OR lower(description) LIKE ? ESCAPE '\\'";
    out(all($sql, [$like,$like,$like,$like,$like,$like]));
}

function act_newsletter_subscribe() {
    $email = strtolower(trim(g('email', '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        err('Podaj poprawny adres e-mail');
    if (!g('consent'))
        err('Zgoda na newsletter jest wymagana');
    try {
        q("INSERT INTO newsletter_subscribers(email,consent,created_at) VALUES(?,1,datetime('now','localtime'))", [$email]);
    } catch (PDOException $e) {
        out(['ok'=>1,'message'=>'Ten adres jest już zapisany']);
    }
    out(['ok'=>1,'message'=>'Zapisano do newslettera']);
}

function act_public_announcements() {
    out(all("SELECT * FROM announcements WHERE published=1 ORDER BY published_at DESC,id DESC"));
}
function act_public_events() {
    out(all("SELECT * FROM events WHERE event_date >= date('now') OR end_date >= date('now') ORDER BY event_date"));
}
function act_public_gallery() {
    out(all("SELECT * FROM gallery ORDER BY created_at DESC,id DESC"));
}
function act_public_documents() {
    out(all("SELECT * FROM documents ORDER BY created_at DESC,id DESC"));
}
function act_public_meals() {
    out(all("SELECT * FROM meal_menus ORDER BY menu_date DESC LIMIT 31"));
}
function act_feedback_submit() {
    global $U;
    $name = trim(g('name',''));
    $email = strtolower(trim(g('email','')));
    $subject = trim(g('subject',''));
    $message = trim(g('message',''));
    if (mb_strlen($subject)<3 || mb_strlen($message)<10)
        err('Opisz błąd lub sugestię – temat min. 3 znaki, treść min. 10 znaków');
    if ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))
        err('Podaj poprawny adres e-mail');
    q("INSERT INTO feedback(user_id,name,email,subject,message,status,created_at) VALUES(?,?,?,?,?, 'nowe', datetime('now','localtime'))",
        [$U['id']??null,$name,$email,$subject,$message]);
    logit('feedback_submit',$subject);
    out(['ok'=>1,'message'=>'Zgłoszenie zostało wysłane']);
}

function act_cms_sections_list() {
    need('admin','sekretariat');
    out(all("SELECT * FROM cms_sections ORDER BY section_order,id"));
}
function act_cms_section_save() {
    need('admin','sekretariat');
    $slug=trim(g('slug','')); $title=trim(g('title','')); $content=trim(g('content',''));
    if (!preg_match('/^[a-z0-9-]+$/',$slug) || $title==='' || $content==='') err('Podaj poprawny slug, tytuł i treść');
    $id=g('id');
    if ($id) q("UPDATE cms_sections SET slug=?,title=?,content=?,section_order=?,published=? WHERE id=?",[$slug,$title,$content,(int)g('section_order',0),g('published')?1:0,$id]);
    else q("INSERT INTO cms_sections(slug,title,content,section_order,published) VALUES(?,?,?,?,?)",[$slug,$title,$content,(int)g('section_order',0),g('published')?1:0]);
    logit('cms_section_save',$slug);
    out(['ok'=>1]);
}
function act_cms_menu_list() { need('admin','sekretariat'); out(all("SELECT * FROM cms_menu ORDER BY menu_order,id")); }
function act_cms_menu_save() {
    need('admin','sekretariat');
    $title=trim(g('title','')); $slug=trim(g('slug',''));
    if ($title==='' || !preg_match('/^[a-z0-9-]+$/',$slug)) err('Podaj nazwę i poprawny identyfikator strony');
    if (g('id')) q("UPDATE cms_menu SET title=?,slug=?,menu_order=?,published=? WHERE id=?",[$title,$slug,(int)g('menu_order',0),g('published')?1:0,g('id')]);
    else q("INSERT INTO cms_menu(title,slug,menu_order,published) VALUES(?,?,?,?)",[$title,$slug,(int)g('menu_order',0),g('published')?1:0]);
    out(['ok'=>1]);
}
function act_cms_menu_delete() { need('admin','sekretariat'); q("DELETE FROM cms_menu WHERE id=?",[(int)g('id')]); out(['ok'=>1]); }

function act_cms_announcements() { need('admin','sekretariat'); out(all("SELECT * FROM announcements ORDER BY id DESC")); }
function act_cms_announcement_save() {
    need('admin','sekretariat'); $title=trim(g('title','')); $content=trim(g('content',''));
    if ($title==='' || $content==='') err('Podaj tytuł i treść ogłoszenia');
    $publishedAt=g('published_at') ?: date('Y-m-d H:i:s');
    if (g('id')) q("UPDATE announcements SET title=?,content=?,published_at=?,published=? WHERE id=?",[$title,$content,$publishedAt,g('published')?1:0,g('id')]);
    else q("INSERT INTO announcements(title,content,published_at,published) VALUES(?,?,?,?)",[$title,$content,$publishedAt,g('published')?1:0]);
    out(['ok'=>1]);
}
function act_cms_event_save() {
    need('admin','sekretariat');
    if (!validDate(g('event_date',''))) err('Podaj poprawną datę wydarzenia');
    $title=trim(g('title','')); if($title==='') err('Podaj nazwę wydarzenia');
    $values=[$title,g('event_date'),g('event_time',''),g('end_date',''),g('description',''),g('location','')];
    if(g('id')) q("UPDATE events SET title=?,event_date=?,event_time=?,end_date=?,description=?,location=? WHERE id=?",array_merge($values,[g('id')]));
    else q("INSERT INTO events(title,event_date,event_time,end_date,description,location) VALUES(?,?,?,?,?,?)",$values);
    out(['ok'=>1]);
}
function act_cms_events() { need('admin','sekretariat'); out(all("SELECT * FROM events ORDER BY event_date,id")); }
function act_cms_gallery_save() {
    need('admin','sekretariat'); $title=trim(g('title','')); $url=trim(g('image_url',''));
    if($title==='' || !preg_match('#^https?://|^/#',$url)) err('Podaj tytuł i poprawny adres zdjęcia');
    if(g('id')) q("UPDATE gallery SET title=?,image_url=?,description=? WHERE id=?",[$title,$url,g('description',''),g('id')]);
    else q("INSERT INTO gallery(title,image_url,description,created_at) VALUES(?,?,?,datetime('now','localtime'))",[$title,$url,g('description','')]);
    out(['ok'=>1]);
}
function act_cms_gallery() { need('admin','sekretariat'); out(all("SELECT * FROM gallery ORDER BY id DESC")); }
function act_cms_document_save() {
    need('admin','sekretariat'); $title=trim(g('title','')); $url=trim(g('file_url',''));
    if($title==='' || !preg_match('#^https?://|^/#',$url)) err('Podaj tytuł i poprawny adres pliku');
    if(g('id')) q("UPDATE documents SET title=?,file_url=?,category=? WHERE id=?",[$title,$url,g('category',''),g('id')]);
    else q("INSERT INTO documents(title,file_url,category,created_at) VALUES(?,?,?,datetime('now','localtime'))",[$title,$url,g('category','')]);
    out(['ok'=>1]);
}
function act_cms_documents() { need('admin','sekretariat'); out(all("SELECT * FROM documents ORDER BY id DESC")); }
function act_cms_meal_save() {
    need('admin','sekretariat');
    if(!validDate(g('menu_date',''))) err('Podaj poprawną datę jadłospisu');
    if(g('id')) q("UPDATE meal_menus SET menu_date=?,title=?,description=? WHERE id=?",[g('menu_date'),g('title',''),g('description',''),g('id')]);
    else q("INSERT INTO meal_menus(menu_date,title,description) VALUES(?,?,?)",[g('menu_date'),g('title',''),g('description','')]);
    out(['ok'=>1]);
}
function act_cms_meals() { need('admin','sekretariat'); out(all("SELECT * FROM meal_menus ORDER BY menu_date DESC")); }

function act_feedback_list() { need('admin','sekretariat','dyrektor'); out(all("SELECT * FROM feedback ORDER BY id DESC")); }
function act_feedback_update() { need('admin','sekretariat'); q("UPDATE feedback SET status=? WHERE id=?",[g('status','rozpatrzone'),(int)g('id')]); out(['ok'=>1]); }
