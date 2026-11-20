<?php
// Dane szkoły i plan lekcji (TEST-4, TEST-144)
function route_school($A) {
    global $U;
    switch ($A) {
        // ---------- Dane szkoły (TEST-4, TEST-144) ----------
        case 'classes_list':
            need('admin', 'dyrektor', 'sekretariat', 'nauczyciel', 'wychowawca');
            out(all('SELECT c.*,u.name homeroom,(SELECT COUNT(*) FROM students WHERE class_id=c.id) n FROM classes c LEFT JOIN users u ON u.id=c.homeroom_id ORDER BY c.name'));
        case 'class_save':
            need('admin', 'sekretariat');
            $n = trim(g('name', ''));
            if ($n === '')
                err('Podaj nazwę klasy');
            $h = g('homeroom_id') ?: null;
            try {
                if (g('id'))
                    q('UPDATE classes SET name=?,homeroom_id=? WHERE id=?', [$n, $h, g('id')]);
                else q('INSERT INTO classes(name,homeroom_id) VALUES(?,?)', [$n, $h]);
            } catch (PDOException $e) {
                err('Klasa o tej nazwie już istnieje');
            }
            logit('class_save', $n);
            out(['ok' => 1]);
        case 'students_list':
            need();
            $qq = trim(g('q', ''));
            $c = g('class_id');
            $res = [];
            foreach (all('SELECT s.*,c.name class FROM students s LEFT JOIN classes c ON c.id=s.class_id ORDER BY s.last,s.first') as $s) {
                if (!canStudent($s['id']))
                    continue;
                if ($c && $s['class_id'] != $c)
                    continue;
                if ($qq !== '' && mb_stripos($s['first'] . ' ' . $s['last'] . ' ' . $s['pesel'] . ' ' . $s['class'], $qq) === false)
                    continue;
                if (!in_array($U['role'], MGMT)) {
                    $s['pesel'] = null;
                    $s['address'] = null;
                    $s['birth'] = null;
                }
                $res[] = $s;
            }
            logit('students_list', $qq);
            out($res);
        case 'student_save':
            need('admin', 'sekretariat');
            $f = trim(g('first', ''));
            $l = trim(g('last', ''));
            $pe = trim(g('pesel', ''));
            if ($f === '' || $l === '')
                err('Podaj imię i nazwisko ucznia');
            if ($pe !== '' && !preg_match('/^\d{11}$/', $pe))
                err('PESEL musi mieć 11 cyfr');
            $c = g('class_id') ?: null;
            if (g('id')) {
                q('UPDATE students SET first=?,last=?,birth=?,pesel=?,address=? WHERE id=?', [$f, $l, g('birth'), $pe, g('address'), g('id')]);
            } else {
                q('INSERT INTO students(first,last,birth,pesel,address,class_id) VALUES(?,?,?,?,?,?)', [$f, $l, g('birth'), $pe, g('address'), $c]);
                if ($c)
                    q("INSERT INTO class_history(student_id,class_id,from_date) VALUES(?,?,date('now'))", [db()->lastInsertId(), $c]);
            }
            logit('student_save', "$f $l");
            out(['ok' => 1]);
        case 'student_move':
            need('admin', 'sekretariat');
            if (!validDate(g('date')))
                err('Podaj datę zmiany');
            if (!one('SELECT 1 FROM classes WHERE id=?', [g('class_id')]))
                err('Wybierz klasę');
            q('UPDATE students SET class_id=? WHERE id=?', [g('class_id'), g('student_id')]);
            q('INSERT INTO class_history(student_id,class_id,from_date) VALUES(?,?,?)', [g('student_id'), g('class_id'), g('date')]);
            logit('student_move', (string)g('student_id'));
            out(['ok' => 1]);
            // oceny i frekwencja zostają przypisane do ucznia, więc historia się zachowuje
        case 'teachers_list':
            need('admin', 'dyrektor', 'sekretariat');
            out(all("SELECT u.id,u.name,u.email,u.role,(SELECT group_concat(s.name,', ') FROM teacher_subjects t JOIN subjects s ON s.id=t.subject_id WHERE t.user_id=u.id) subjects FROM users u WHERE role IN('nauczyciel','wychowawca') ORDER BY u.name"));
        case 'parents_list':
            need('admin', 'dyrektor', 'sekretariat');
            out(all("SELECT id,name,email FROM users WHERE role='rodzic' ORDER BY name"));
        case 'links_list':
            need('admin', 'dyrektor', 'sekretariat');
            out(all('SELECT l.*,u.name parent,s.first||" "||s.last student FROM parent_links l JOIN users u ON u.id=l.parent_id JOIN students s ON s.id=l.student_id ORDER BY s.last'));
        case 'link_save':
            need('admin', 'sekretariat');
            q('INSERT INTO parent_links(parent_id,student_id,relation,contact,pickup,restricted) VALUES(?,?,?,?,?,?) ON CONFLICT(parent_id,student_id) DO UPDATE SET relation=excluded.relation,contact=excluded.contact,pickup=excluded.pickup,restricted=excluded.restricted', [g('parent_id'), g('student_id'), g('relation'), g('contact'), g('pickup') ? 1 : 0, g('restricted') ? 1 : 0]);
            logit('link_save', g('parent_id') . '->' . g('student_id'));
            out(['ok' => 1]);
        case 'link_delete':
            need('admin', 'sekretariat');
            q('DELETE FROM parent_links WHERE id=?', [g('id')]);
            out(['ok' => 1]);
        case 'subjects_list':
            need();
            out(all('SELECT * FROM subjects ORDER BY name'));
        case 'subject_save':
            need('admin');
            $n = trim(g('name', ''));
            if ($n === '')
                err('Podaj nazwę przedmiotu');
            try {
                q('INSERT INTO subjects(name) VALUES(?)', [$n]);
            } catch (PDOException $e) {
                err('Taki przedmiot już istnieje');
            }
            out(['ok' => 1]);
        case 'assignments_list':
            need('admin', 'dyrektor', 'sekretariat');
            out(all('SELECT a.id,a.class_id,a.teacher_id,a.subject_id,c.name class,s.name subject,u.name teacher FROM assignments a JOIN classes c ON c.id=a.class_id JOIN subjects s ON s.id=a.subject_id JOIN users u ON u.id=a.teacher_id ORDER BY c.name,s.name'));
        case 'assignment_save':
            need('admin', 'dyrektor');
            try {
                q('INSERT INTO assignments(teacher_id,class_id,subject_id) VALUES(?,?,?)', [g('teacher_id'), g('class_id'), g('subject_id')]);
            } catch (PDOException $e) {
                err('Taki przydział już istnieje');
            }
            logit('assignment_save');
            out(['ok' => 1]);
        case 'assignment_delete':
            need('admin', 'dyrektor');
            q('DELETE FROM schedule WHERE assignment_id=?', [g('id')]);
            q('DELETE FROM assignments WHERE id=?', [g('id')]);
            out(['ok' => 1]);
        case 'schedule_list':
            need('admin', 'dyrektor', 'sekretariat', 'nauczyciel', 'wychowawca');
            out(all('SELECT sc.id,sc.weekday,sc.lesson_no,sc.group_name,s.name subject,u.name teacher FROM schedule sc JOIN assignments a ON a.id=sc.assignment_id JOIN subjects s ON s.id=a.subject_id JOIN users u ON u.id=a.teacher_id WHERE a.class_id=? ORDER BY sc.lesson_no,sc.weekday,sc.group_name,s.name', [g('class_id')]));
        case 'schedule_save':
            need('admin', 'dyrektor', 'sekretariat');
            $w = (int)g('weekday');
            $n = filter_var(g('lesson_no'), FILTER_VALIDATE_INT);
            $groupName = trim((string)g('group_name', ''));
            if ($w < 1 || $w > 5 || $n === false || $n < 1 || $n > 10)
                err('Nieprawidłowy dzień lub numer lekcji');
            if (mb_strlen($groupName) > 40)
                err('Nazwa grupy może mieć maksymalnie 40 znaków');
            $a = one('SELECT class_id,teacher_id FROM assignments WHERE id=?', [g('assignment_id')]);
            if (!$a)
                err('Wybierz przydział');
            if (one("SELECT 1 FROM schedule sc JOIN assignments x ON x.id=sc.assignment_id WHERE sc.weekday=? AND sc.lesson_no=? AND (x.teacher_id=? OR (x.class_id=? AND (sc.group_name='' OR ?='' OR sc.group_name=?)))", [$w, $n, $a['teacher_id'], $a['class_id'], $groupName, $groupName]))
                err('Ta godzina jest już zajęta przez nauczyciela lub tę samą grupę klasy');
            q('INSERT INTO schedule(assignment_id,weekday,lesson_no,group_name) VALUES(?,?,?,?)', [g('assignment_id'), $w, $n, $groupName]);
            out(['ok' => 1]);
        case 'schedule_delete':
            need('admin', 'dyrektor', 'sekretariat');
            q('DELETE FROM schedule WHERE id=?', [g('id')]);
            out(['ok' => 1]);

        }
    }
