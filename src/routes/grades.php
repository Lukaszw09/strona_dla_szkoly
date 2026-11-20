<?php
// Oceny, uwagi i zachowanie

/** Nauczyciel może wystawiać oceny tylko z przedmiotu, który uczy w klasie ucznia. */
function act_grade_save() {
    global $U;
    need('nauczyciel', 'wychowawca');
    $studentId = (int)g('student_id');
    $subjectId = (int)g('subject_id');
    $value = (int)g('value');
    $kind = g('kind', 'cząstkowa');

    if ($value < 1 || $value > 6)
        err('Ocena musi być w skali 1-6');
    if (!in_array($kind, GRADE_KINDS))
        err('Nieprawidłowy rodzaj oceny');

    $student = studentRow($studentId);
    $teaches = one('SELECT 1 FROM assignments WHERE teacher_id = ? AND class_id = ? AND subject_id = ?', [$U['id'], $student['class_id'], $subjectId]);
    if (!$teaches) {
        logit('DENIED', 'grade_save ' . $studentId);
        err('Nie uczysz tego przedmiotu w klasie ucznia', 403);
    }
    $subject = one('SELECT name FROM subjects WHERE id=?', [$subjectId]);
    $p = db();
    $p->beginTransaction();
    try {
        q("INSERT INTO grades(student_id, subject_id, teacher_id, value, kind, date) VALUES(?,?,?,?,?,date('now'))", [$studentId, $subjectId, $U['id'], $value, $kind]);
        $message = "Nowa ocena z {$subject['name']} dla {$student['first']} {$student['last']}.";
        foreach (all('SELECT parent_id FROM parent_links WHERE student_id=? AND restricted=0', [$studentId]) as $parent) {
            notify($parent['parent_id'], $message);
        }
        if ($student['user_id']) {
            notify($student['user_id'], $message);
        }
        logit('grade_save', "$studentId/$subjectId=$value");
        $p->commit();
    } catch (Throwable $e) {
        if ($p->inTransaction()) {
            $p->rollBack();
        }
        throw $e;
    }
    out(['ok' => 1]);
}

function act_student_grades() {
    need('rodzic', 'uczen');
    $studentId = (int)g('student_id');
    needStudent($studentId);
    $student = studentRow($studentId);
    $grades = all('SELECT g.value,g.kind,g.date,s.name subject,u.name teacher FROM grades g JOIN subjects s ON s.id=g.subject_id JOIN users u ON u.id=g.teacher_id WHERE g.student_id=? ORDER BY s.name,g.date DESC,g.id DESC', [$studentId]);
    $remarks = all('SELECT r.text,r.date,u.name teacher FROM remarks r JOIN users u ON u.id=r.teacher_id WHERE r.student_id=? ORDER BY r.date DESC,r.id DESC LIMIT 10', [$studentId]);
    $summary = studentSummary($studentId);
    logit('student_grades', (string)$studentId);
    out([
        'student' => ['first' => $student['first'], 'last' => $student['last']],
        'grades' => $grades,
        'finals' => $summary['finals'],
        'remarks' => $remarks,
    ]);
}

function act_family_dashboard() {
    need('rodzic', 'uczen');
    $studentId = (int)g('student_id');
    needStudent($studentId);
    $student = studentRow($studentId);
    $weekday = (int)date('N');
    $schedule = $weekday <= 5
        ? all('SELECT sc.lesson_no,sc.group_name,s.name subject,u.name teacher FROM schedule sc JOIN assignments a ON a.id=sc.assignment_id JOIN subjects s ON s.id=a.subject_id JOIN users u ON u.id=a.teacher_id WHERE a.class_id=? AND sc.weekday=? ORDER BY sc.lesson_no,sc.group_name,s.name', [$student['class_id'], $weekday])
        : [];
    $attendance = all('SELECT l.date,l.lesson_no,s.name subject,a.status FROM attendance a JOIN lessons l ON l.id=a.lesson_id JOIN assignments x ON x.id=l.assignment_id JOIN subjects s ON s.id=x.subject_id WHERE a.student_id=? ORDER BY l.date DESC,l.lesson_no DESC LIMIT 5', [$studentId]);
    $grades = all('SELECT g.value,g.kind,g.date,s.name subject FROM grades g JOIN subjects s ON s.id=g.subject_id WHERE g.student_id=? ORDER BY g.date DESC,g.id DESC LIMIT 5', [$studentId]);
    $remarks = all('SELECT r.text,r.date,u.name teacher FROM remarks r JOIN users u ON u.id=r.teacher_id WHERE r.student_id=? ORDER BY r.date DESC,r.id DESC LIMIT 5', [$studentId]);
    logit('family_dashboard', (string)$studentId);
    out([
        'student' => ['first' => $student['first'], 'last' => $student['last'], 'class' => $student['class']],
        'schedule' => $schedule,
        'attendance' => $attendance,
        'grades' => $grades,
        'remarks' => $remarks,
    ]);
}

function act_grade_delete() {
    global $U;
    need('nauczyciel', 'wychowawca');
    q('DELETE FROM grades WHERE id = ? AND teacher_id = ?', [g('id'), $U['id']]);
    out(['ok' => 1]);
}

function act_grade_update() {
    global $U;
    need('nauczyciel','wychowawca');
    $value=(int)g('value');
    $kind=g('kind','cząstkowa');
    if($value<1||$value>6) err('Ocena musi być w skali 1-6');
    if(!in_array($kind,GRADE_KINDS)) err('Nieprawidłowy rodzaj oceny');
    $changed=q('UPDATE grades SET value=?,kind=? WHERE id=? AND teacher_id=?',[$value,$kind,(int)g('id'),$U['id']]);
    if(!$changed->rowCount()) err('Nie można edytować tej oceny',403);
    logit('grade_update',(string)g('id'));
    out(['ok'=>1]);
}

/** Lista ocen uczniów wybranego przydziału (klasa + przedmiot nauczyciela). */
function act_grades_for_assignment() {
    global $U;
    need('nauczyciel', 'wychowawca');
    $a = one('SELECT * FROM assignments WHERE id = ? AND teacher_id = ?', [g('assignment_id'), $U['id']]);
    if (!$a)
        err('To nie jest Twój przydział', 403);

    $students = classStudents($a['class_id']);
    foreach ($students as &$s) {
        $s['grades'] = all('SELECT id, value, kind, date FROM grades WHERE student_id = ? AND subject_id = ? ORDER BY id', [$s['id'], $a['subject_id']]);
        $s['pesel'] = $s['address'] = $s['birth'] = null;
    }
    out(['subject_id' => $a['subject_id'], 'students' => $students]);
}

function act_remark_save() {
    global $U;
    need('nauczyciel', 'wychowawca');
    $studentId = (int)g('student_id');
    $text = trim(g('text', ''));
    if ($text === '')
        err('Wpisz treść uwagi');
    needStudent($studentId);
    q("INSERT INTO remarks(student_id, teacher_id, text, date) VALUES(?,?,?,date('now'))", [$studentId, $U['id'], $text]);
    out(['ok' => 1]);
}

function act_conduct_save() {
    need('wychowawca');
    $studentId = (int)g('student_id');
    if (!isHomeroomOf($studentId))
        err('Ocenę zachowania ustala wychowawca klasy', 403);
    if (!in_array(g('conduct'), CONDUCT_LEVELS))
        err('Nieprawidłowa ocena zachowania');
    q('UPDATE students SET conduct = ? WHERE id = ?', [g('conduct'), $studentId]);
    out(['ok' => 1]);
}
