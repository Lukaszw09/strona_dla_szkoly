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
    q("INSERT INTO grades(student_id, subject_id, teacher_id, value, kind, date) VALUES(?,?,?,?,?,date('now'))", [$studentId, $subjectId, $U['id'], $value, $kind]);
    logit('grade_save', "$studentId/$subjectId=$value");
    out(['ok' => 1]);
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
