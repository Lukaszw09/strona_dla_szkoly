<?php
// Dziennik: lekcje, frekwencja, usprawiedliwienia (TEST-37)
function route_journal($A) {
    global $U;
    switch ($A) {
        // ---------- Dziennik: obecności i tematy (TEST-37) ----------
        case 'my_assignments':
            need('nauczyciel', 'wychowawca');
            out(all('SELECT a.id,c.name class,s.name subject FROM assignments a JOIN classes c ON c.id=a.class_id JOIN subjects s ON s.id=a.subject_id WHERE a.teacher_id=? ORDER BY c.name,s.name', [$U['id']]));
        case 'lesson_open':
            need('nauczyciel', 'wychowawca');
            $d = g('date');
            if (!validDate($d))
                err('Podaj poprawną datę');
            $no = (int)g('lesson_no', 1);
            $a = one('SELECT a.*,s.name subject,c.name class FROM assignments a JOIN subjects s ON s.id=a.subject_id JOIN classes c ON c.id=a.class_id WHERE a.id=? AND a.teacher_id=?', [g('assignment_id'), $U['id']]);
            if (!$a) {
                logit('DENIED', 'lesson_open ' . g('assignment_id'));
                err('To nie jest Twoja lekcja', 403);
            }
            $l = one('SELECT * FROM lessons WHERE assignment_id=? AND date=? AND lesson_no=?', [$a['id'], $d, $no]);
            $st = all('SELECT s.id,s.first,s.last,(SELECT status FROM attendance WHERE lesson_id=? AND student_id=s.id) status FROM students s WHERE class_id=? ORDER BY last,first', [$l['id'] ?? 0, $a['class_id']]);
            logit('lesson_open', "{$a['class']} $d");
        out(['assignment' => $a, 'topic' => $l['topic'] ?? '', 'students' => $st]);
        case 'lesson_save':
            need('nauczyciel', 'wychowawca');
            $d = g('date');
            if (!validDate($d))
                err('Podaj poprawną datę');
            $no = (int)g('lesson_no', 1);
            $topic = trim(g('topic', ''));
            if ($topic === '')
                err('Wpisz temat lekcji');
            $a = one('SELECT * FROM assignments WHERE id=? AND teacher_id=?', [g('assignment_id'), $U['id']]);
            if (!$a) {
                logit('DENIED', 'lesson_save');
                err('To nie jest Twoja lekcja', 403);
            }
            q('INSERT INTO lessons(assignment_id,date,lesson_no,topic) VALUES(?,?,?,?) ON CONFLICT(assignment_id,date,lesson_no) DO UPDATE SET topic=excluded.topic', [$a['id'], $d, $no, $topic]);
            $lid = one('SELECT id FROM lessons WHERE assignment_id=? AND date=? AND lesson_no=?', [$a['id'], $d, $no])['id'];
            foreach ((array)g('attendance', []) as $sid => $status) {
                if (!in_array($status, ST))
                    err('Nieprawidłowy status');
                $stu = one('SELECT * FROM students WHERE id=? AND class_id=?', [$sid, $a['class_id']]);
                if (!$stu)
                    continue;
                $prev = one('SELECT status FROM attendance WHERE lesson_id=? AND student_id=?', [$lid, $sid])['status'] ?? null;
                // zatwierdzone usprawiedliwienie obejmujące datę zamienia nieobecność na usprawiedliwioną
                if ($status == 'nieobecny' && one("SELECT 1 FROM excuses WHERE student_id=? AND status='zatwierdzone' AND ? BETWEEN date_from AND date_to", [$sid, $d]))
                    $status = 'usprawiedliwiony';
                q('INSERT INTO attendance VALUES(?,?,?) ON CONFLICT(lesson_id,student_id) DO UPDATE SET status=excluded.status', [$lid, $sid, $status]);
                if ($status == 'nieobecny' && $prev != 'nieobecny')
                    foreach (all('SELECT parent_id FROM parent_links WHERE student_id=? AND restricted=0', [$sid]) as $p)
                    notify($p['parent_id'], "Nieusprawiedliwiona nieobecność: {$stu['first']} {$stu['last']}, $d (lekcja $no).");
    }
    logit('lesson_save', "assignment {$a['id']} $d");
out(['ok' => 1]);
case 'excuse_submit' : need('rodzic');
needStudent(g('student_id'));
if (!validDate(g('from')) || !validDate(g('to')) || g('to') < g('from'))
    err('Podaj poprawny zakres dat');
if (trim(g('reason', '')) === '')
    err('Podaj powód nieobecności');
q("INSERT INTO excuses(student_id,parent_id,date_from,date_to,reason,created) VALUES(?,?,?,?,?,datetime('now','localtime'))", [g('student_id'), $U['id'], g('from'), g('to'), trim(g('reason'))]);
$s = one('SELECT s.first,s.last,c.homeroom_id FROM students s LEFT JOIN classes c ON c.id=s.class_id WHERE s.id=?', [g('student_id')]);
if ($s['homeroom_id'])
    notify($s['homeroom_id'], "Nowe usprawiedliwienie: {$s['first']} {$s['last']} (" . g('from') . ' – ' . g('to') . ')');
logit('excuse_submit', (string)g('student_id'));
out(['ok' => 1]);
case 'excuses_list' : need('rodzic', 'wychowawca', 'admin', 'dyrektor', 'sekretariat');
$rows = all('SELECT e.*,s.first||" "||s.last student,s.class_id FROM excuses e JOIN students s ON s.id=e.student_id ORDER BY e.id DESC');
out(array_values(array_filter($rows, function($e)use($U) { if ($U['role'] == 'rodzic')return $e['parent_id'] == $U['id']; if ($U['role'] == 'wychowawca')return in_array($e['class_id'], homeIds());return true;})));
case 'excuse_decide' : need('wychowawca', 'admin');
$e = one('SELECT * FROM excuses WHERE id=?', [g('id')]);
if (!$e)
err('Nie znaleziono', 404);
if ($U['role'] == 'wychowawca' && !isHomeroomOf($e['student_id'])) {
logit('DENIED', 'excuse_decide');
err('Możesz decydować tylko dla swojej klasy', 403);
}
$d = g('decision');
if (!in_array($d, ['zatwierdzone', 'odrzucone']))
err('Nieprawidłowa decyzja');
q('UPDATE excuses SET status=? WHERE id=?', [$d, $e['id']]);
if ($d == 'zatwierdzone')
q("UPDATE attendance SET status='usprawiedliwiony' WHERE student_id=? AND status='nieobecny' AND lesson_id IN(SELECT id FROM lessons WHERE date BETWEEN ? AND ?)", [$e['student_id'], $e['date_from'], $e['date_to']]);
notify($e['parent_id'], "Usprawiedliwienie ({$e['date_from']} – {$e['date_to']}): $d.");
logit('excuse_decide', "{$e['id']} $d");
out(['ok' => 1]);
case 'attendance_history' : need();
$sid = g('student_id');
needStudent($sid);
$rows = all('SELECT l.date,l.lesson_no,l.topic,s.name subject,a.status FROM attendance a JOIN lessons l ON l.id=a.lesson_id JOIN assignments x ON x.id=l.assignment_id JOIN subjects s ON s.id=x.subject_id WHERE a.student_id=? ORDER BY l.date DESC,l.lesson_no', [$sid]);
logit('attendance_history', (string)$sid);
out($rows);
case 'stats' : need('admin', 'dyrektor', 'sekretariat', 'wychowawca');
$w = '';
$p = [];
if ($U['role'] == 'wychowawca') {
$w = 'WHERE s.class_id IN(SELECT id FROM classes WHERE homeroom_id=?)';
$p = [$U['id']];
}
$rows = all("SELECT s.id,s.first,s.last,c.name class,COUNT(a.status) total,COALESCE(SUM(a.status='obecny'),0) ob,COALESCE(SUM(a.status='spóźniony'),0) sp,COALESCE(SUM(a.status='nieobecny'),0) nb,COALESCE(SUM(a.status='usprawiedliwiony'),0) us,COALESCE(SUM(a.status='zwolniony'),0) zw FROM students s LEFT JOIN classes c ON c.id=s.class_id LEFT JOIN attendance a ON a.student_id=s.id $w GROUP BY s.id ORDER BY c.name,s.last", $p);
foreach ($rows as &$r)
$r['pct'] = $r['total'] ? round(100*($r['ob']+$r['sp'])/$r['total'], 1) : null;
// frekwencja = obecny + spóźniony
out($rows);
case 'missing' : need('wychowawca', 'dyrektor', 'admin');
$w = $U['role'] == 'wychowawca' ? ' AND c.homeroom_id=' . (int)$U['id'] : '';
$slots = all("SELECT sc.weekday,sc.lesson_no,a.id aid,s.name subject,c.name class,u.name teacher FROM schedule sc JOIN assignments a ON a.id=sc.assignment_id JOIN subjects s ON s.id=a.subject_id JOIN classes c ON c.id=a.class_id JOIN users u ON u.id=a.teacher_id WHERE 1=1 $w");
$res = [];
for ($i = 0;$i < 7;$i++) {
$d = date('Y-m-d', strtotime("-$i day"));
$wd = (int)date('N', strtotime($d));
foreach ($slots as $s) {
if ($s['weekday'] != $wd)
continue;
$filled = one('SELECT 1 FROM lessons l WHERE l.assignment_id=? AND l.date=? AND l.lesson_no=? AND EXISTS(SELECT 1 FROM attendance WHERE lesson_id=l.id)', [$s['aid'], $d, $s['lesson_no']]);
if (!$filled)
$res[] = $s+['date' => $d];
}
}
out($res);
case 'notif_list' : need();
$r = all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50', [$U['id']]);
q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$U['id']]);
out($r);
}
}
