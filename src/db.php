<?php
// Połączenie z SQLite; schemat i dane demo tworzone przy pierwszym uruchomieniu
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/seed.php';
function db() {
    static $p;
    if ($p)
        return $p;
    $dir = dirname(__DIR__) . '/data';
    if (!is_dir($dir))
        mkdir($dir, 0775, true);
    $f = $dir . '/dziennik.sqlite';
    $new = !file_exists($f);
    $p = new PDO('sqlite:' . $f);
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    if ($new) {
        $p->exec(SCHEMA);
        seed($p);
    }
    return $p;
}
