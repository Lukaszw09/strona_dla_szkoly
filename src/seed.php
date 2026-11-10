<?php
// Dane demo (liceum)
function seed($p) {
    $h = password_hash('haslo123', PASSWORD_DEFAULT);
    $u = $p->prepare('INSERT INTO users(email,password,role,name) VALUES(?,?,?,?)');
    foreach ([['admin', 'admin', 'Adam Administrator'], ['dyrektor', 'dyrektor', 'Dorota Dyrektor'], ['sekretariat', 'sekretariat', 'Sylwia Sekretarz'], ['nauczyciel', 'nauczyciel', 'Anna Nowak'], ['wychowawca', 'wychowawca', 'Jan Kowalski'], ['rodzic', 'rodzic', 'Piotr Wiśniewski'], ['uczen', 'uczen', 'Maja Wiśniewska']] as $r)
        $u->execute([$r[0] . '@szkola.local', $h, $r[1], $r[2]]);
    $p->exec("INSERT INTO classes(name,homeroom_id) VALUES('1A',5),('1B',NULL)");
    $s = $p->prepare('INSERT INTO students(user_id,first,last,birth,pesel,address,class_id) VALUES(?,?,?,?,?,?,?)');
    $st = [[7, 'Maja', 'Wiśniewska', 1], [null, 'Kacper', 'Zieliński', 1], [null, 'Zuzanna', 'Lewandowska', 1], [null, 'Antoni', 'Kamiński', 1], [null, 'Julia', 'Szymańska', 1], [null, 'Szymon', 'Dąbrowski', 1], [null, 'Hanna', 'Woźniak', 2], [null, 'Filip', 'Mazur', 2], [null, 'Lena', 'Krawczyk', 2]];
    foreach ($st as $i => $r) {
        $s->execute([$r[0], $r[1], $r[2], '2010-0' . (1+$i%9) . '-1' . ($i%9), '1000' . sprintf('%07d', 1234567+$i*111), 'ul. Licealna ' . ($i+1) . ', Katowice', $r[3]]);
        $p->prepare("INSERT INTO class_history(student_id,class_id,from_date) VALUES(?,?,'2025-09-01')")->execute([$i+1, $r[3]]);
    }
    $p->exec("INSERT INTO subjects(name) VALUES('Język polski'),('Matematyka'),('Język angielski'),('Fizyka'),('Historia'),('Biologia'),('Informatyka'),('Wychowanie fizyczne')");
    $p->exec("INSERT INTO teacher_subjects VALUES(4,2),(4,4),(5,1),(5,3),(5,5)");
    $p->exec("INSERT INTO parent_links(parent_id,student_id,relation,contact,pickup,restricted) VALUES(6,1,'ojciec','600100200',1,0)");
    $p->exec("INSERT INTO assignments(teacher_id,class_id,subject_id) VALUES(5,1,1),(5,1,3),(4,1,2),(4,1,4),(4,2,2)");
    $p->exec("INSERT INTO schedule(assignment_id,weekday,lesson_no) VALUES(1,1,1),(1,2,1),(1,3,1),(2,1,2),(2,3,2),(3,1,3),(3,4,2),(4,2,3),(5,2,2),(5,4,1)");
    // dane demo frekwencji (ostatnie ~3 tygodnie, Matematyka 1A)
    $ss = ['obecny', 'obecny', 'obecny', 'obecny', 'spóźniony', 'nieobecny', 'usprawiedliwiony', 'zwolniony'];
    for ($d = 21;$d >= 1;$d--) {
        $t = strtotime("-$d day");
        if (date('N', $t) > 5)
            continue;
        $date = date('Y-m-d', $t);
        $p->prepare('INSERT INTO lessons(assignment_id,date,lesson_no,topic) VALUES(3,?,3,?)')->execute([$date, 'Temat demo: funkcja kwadratowa (' . $date . ')']);
        $lid = $p->lastInsertId();
        for ($sid = 1;$sid <= 6;$sid++)
            $p->prepare('INSERT INTO attendance VALUES(?,?,?)')->execute([$lid, $sid, $ss[($sid*sid_mix($sid)+$d*3)%8]]);
    }
    seedGrades($p);
}
function seedGrades($p) {
    // demo: kilka ocen cząstkowych i rocznych dla uczniów klas 1A
    $subjects = [1, 2, 3, 4, 5];
    $g = $p->prepare("INSERT INTO grades(student_id,subject_id,teacher_id,value,kind,date) VALUES(?,?,?,?,?,date('now'))");
    foreach (range(1, 6) as $sid) {
        foreach ($subjects as $sub) {
            $teacher = in_array($sub, [2, 4]) ? 4 : 5;
            foreach ([3, 4, 5] as $k => $base)
                $g->execute([$sid, $sub, $teacher, max(1, min(6, $base+(($sid+$sub+$k)%3)-($sid == 4 && $sub == 2 ? 5 : 0))), 'cząstkowa']);
        }
    }
    $p->exec("INSERT INTO remarks(student_id,teacher_id,text,date) VALUES(2,4,'Rozmowa na lekcji',date('now')),(2,5,'Brak zadania',date('now')),(4,4,'Spóźnienie',date('now'))");
    $p->exec("UPDATE students SET conduct='wzorowe' WHERE id IN(1,3)");
}
function sid_mix($s) {
    return $s%3+1;
}
