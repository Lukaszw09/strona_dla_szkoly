<?php
// Import danych z poprzedniego systemu (CSV przekazane jako tablica rekordów)
function act_migration_import() {
    need('admin','dyrektor','sekretariat');
    $records=g('records');
    if(!is_array($records)||count($records)>1000) err('Przekaż listę do 1000 rekordów');
    $imported=0;$errors=[];
    $p=db(); $p->beginTransaction();
    try {
        foreach($records as $i=>$r) {
            if(!is_array($r)) { $errors[]='Wiersz '.($i+1).': nieprawidłowy rekord'; continue; }
            $first=trim($r['first']??$r['imie']??$r['Imie']??'');
            $last=trim($r['last']??$r['nazwisko']??$r['Nazwisko']??'');
            $className=trim($r['class']??$r['klasa']??$r['Oddzial']??'');
            $pesel=trim($r['pesel']??$r['PESEL']??'');
            if($first===''||$last==='') { $errors[]='Wiersz '.($i+1).': brak imienia lub nazwiska'; continue; }
            if($pesel!==''&&!preg_match('/^\d{11}$/',$pesel)) { $errors[]='Wiersz '.($i+1).': nieprawidłowy PESEL'; continue; }
            $classId=null;
            if($className!=='') {
                $class=one("SELECT id FROM classes WHERE name=?",[$className]);
                if(!$class) { q("INSERT INTO classes(name) VALUES(?)",[$className]); $classId=(int)db()->lastInsertId(); }
                else $classId=(int)$class['id'];
            }
            q("INSERT INTO students(first,last,birth,pesel,address,class_id) VALUES(?,?,?,?,?,?)",
                [$first,$last,$r['birth']??$r['data_urodzenia']??null,$pesel,$r['address']??$r['adres']??null,$classId]);
            $imported++;
        }
        q("INSERT OR REPLACE INTO migration_runs(name,status,details,run_at) VALUES(?,?,?,datetime('now','localtime'))",
            ['csv-import','completed',$imported.' rekordów, '.count($errors).' błędów']);
        $p->commit();
    } catch(Throwable $e) {
        if($p->inTransaction()) $p->rollBack();
        throw $e;
    }
    logit('migration_import',$imported.' imported');
    out(['ok'=>1,'imported'=>$imported,'errors'=>$errors]);
}
