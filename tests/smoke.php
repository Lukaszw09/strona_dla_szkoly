<?php
require __DIR__.'/../src/config.php';
require __DIR__.'/../src/db.php';

$pdo=db();
$required=[
 'users','students','classes','subjects','assignments','schedule','lessons','attendance','excuses',
 'grades','remarks','cms_sections','cms_menu','announcements','events','gallery','documents',
 'meal_menus','newsletter_subscribers','feedback','messages','privacy_consents','migration_runs'
];
$failures=[];
foreach($required as $table){
    $exists=$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=".$pdo->quote($table))->fetchColumn();
    if(!$exists) $failures[]="Brak tabeli: $table";
}
$roles=$pdo->query("SELECT COUNT(DISTINCT role) c FROM users")->fetch()['c'];
if((int)$roles<7) $failures[]='Brak wymaganych 7 ról';

if($failures){
    fwrite(STDERR,implode("\n",$failures)."\n");
    exit(1);
}
echo "Smoke test: OK (schemat, migracje i role)\n";
