<?php
// Połączenie z SQLite; schemat i dane demo tworzone przy pierwszym uruchomieniu
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/seed.php';
function storageDir() {
    static $dir;
    if ($dir !== null)
        return $dir;

    $projectRoot = dirname(__DIR__);
    $publicRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: $projectRoot;
    $configuredDir = getenv('APP_DATA_DIR');
    if ($configuredDir !== false && trim($configuredDir) !== '') {
        $dir = trim($configuredDir);
        if ($dir[0] !== DIRECTORY_SEPARATOR)
            $dir = $projectRoot . DIRECTORY_SEPARATOR . $dir;
    } else {
        $dir = dirname($publicRoot) . DIRECTORY_SEPARATOR . '.' . basename($projectRoot) . '-data';
    }

    $parent = dirname($dir);
    if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent))
        throw new RuntimeException('Nie można utworzyć katalogu danych aplikacji');
    $parent = realpath($parent);
    if ($parent === false)
        throw new RuntimeException('Nie można ustalić katalogu danych aplikacji');
    $dir = $parent . DIRECTORY_SEPARATOR . basename($dir);

    $publicRoot = realpath($publicRoot) ?: $projectRoot;
    if ($dir === $publicRoot || str_starts_with($dir, $publicRoot . DIRECTORY_SEPARATOR))
        throw new RuntimeException('Katalog danych musi znajdować się poza katalogiem publicznym');
    if (!is_dir($dir) && !mkdir($dir, 0700) && !is_dir($dir))
        throw new RuntimeException('Nie można utworzyć katalogu danych aplikacji');
    $dir = realpath($dir);
    if ($dir === false || $dir === $publicRoot || str_starts_with($dir, $publicRoot . DIRECTORY_SEPARATOR))
        throw new RuntimeException('Katalog danych musi znajdować się poza katalogiem publicznym');

    $legacyDir = $projectRoot . DIRECTORY_SEPARATOR . 'data';
    foreach (['dziennik.sqlite', 'dziennik.sqlite-wal', 'dziennik.sqlite-shm', 'dziennik.sqlite-journal', 'mail.log'] as $file) {
        $legacyFile = $legacyDir . DIRECTORY_SEPARATOR . $file;
        $newFile = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_file($legacyFile)) {
            $destination = $newFile;
            for ($suffix = 1; file_exists($destination); $suffix++)
                $destination = $dir . DIRECTORY_SEPARATOR . 'legacy-' . $file . '-' . $suffix;
            if (!rename($legacyFile, $destination))
                throw new RuntimeException('Nie udało się przenieść pliku danych poza katalog publiczny');
        }
    }

    return $dir;
}

function db() {
    static $p;
    if ($p)
        return $p;
    $f = storageDir() . DIRECTORY_SEPARATOR . 'dziennik.sqlite';
    $new = !file_exists($f);
    $p = new PDO('sqlite:' . $f);
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    if ($new) {
        $p->exec(SCHEMA);
        seed($p);
    }
    ensureAppSchema($p);
    return $p;
}

function ensureAppSchema($p) {
    $tables = preg_split('/;\s*/', SCHEMA);
    foreach ($tables as $tableSql) {
        $tableSql = trim($tableSql);
        if ($tableSql !== '') {
            $tableSql = preg_replace('/^CREATE TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $tableSql);
            $p->exec($tableSql);
        }
    }
    $scheduleColumns = array_column($p->query('PRAGMA table_info(schedule)')->fetchAll(), 'name');
    if (!in_array('group_name', $scheduleColumns, true)) {
        $p->exec("ALTER TABLE schedule ADD COLUMN group_name TEXT NOT NULL DEFAULT ''");
    }
    $count = (int)$p->query("SELECT COUNT(*) c FROM cms_sections")->fetch()['c'];
    if (!$count) {
        $p->exec("INSERT INTO cms_sections(slug,title,content,section_order,published) VALUES
            ('about','O szkole','ZSTI to Zespół Szkół Techniczno-Informatycznych w Gliwicach. Zapewniamy nowoczesną edukację techniczną i sportową.',1,1),
            ('offer','Kierunki nauczania','Technik informatyk, technik programista, technik cyberbezpieczeństwa oraz technik elektryk.',2,1),
            ('sport','XI Liceum Sportowe','Oddziały mistrzostwa sportowego: piłka ręczna dziewcząt oraz piłka nożna chłopców z elementami futsalu.',3,1),
            ('contact','Kontakt','Zespół Szkół Techniczno-Informatycznych, ul. Chorzowska 5, 44-100 Gliwice. Sekretariat: (32) 230 68 31.',4,1),
            ('first-graders','Dla przyszłych pierwszoklasistów','Informacje o zapisach, wymaganych dokumentach i dniu otwartym są publikowane przez sekretariat.',5,1)");
    }
    $count = (int)$p->query("SELECT COUNT(*) c FROM cms_menu")->fetch()['c'];
    if (!$count)
        $p->exec("INSERT INTO cms_menu(title,slug,menu_order,published) VALUES
            ('O szkole','about',1,1),('Kierunki','offer',2,1),('Sport','sport',3,1),('Kontakt','contact',4,1)");

    $demo = [
        'admin@szkola.local' => ['role'=>'admin','name'=>'Adam Administrator'],
        'dyrektor@szkola.local' => ['role'=>'dyrektor','name'=>'Dorota Dyrektor'],
        'sekretariat@szkola.local' => ['role'=>'sekretariat','name'=>'Sylwia Sekretarz'],
        'nauczyciel@szkola.local' => ['role'=>'nauczyciel','name'=>'Anna Nowak'],
        'wychowawca@szkola.local' => ['role'=>'wychowawca','name'=>'Jan Kowalski'],
        'rodzic@szkola.local' => ['role'=>'rodzic','name'=>'Piotr Wiśniewski'],
        'uczen@szkola.local' => ['role'=>'uczen','name'=>'Maja Wiśniewska'],
    ];
    $password = password_hash('haslo123', PASSWORD_DEFAULT);
    $insert = $p->prepare('INSERT OR IGNORE INTO users(email,password,role,name) VALUES(?,?,?,?)');
    foreach ($demo as $email => $data)
        $insert->execute([$email,$password,$data['role'],$data['name']]);
}
