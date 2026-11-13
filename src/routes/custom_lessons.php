<?php
// Lekcje własne zapisywane w bazie

/** Nauczyciel dodaje własną lekcję (przedmiot spoza listy) dla wybranej klasy; opcjonalnie wpisuje ją do planu. */
function act_custom_lesson_save() {
    global $U;
    need('nauczyciel', 'wychowawca');
    $name = trim(g('name', ''));
    $classId = (int)g('class_id');
    if ($name === '' || mb_strlen($name) > 60)
        err('Podaj nazwę lekcji (do 60 znaków)');
    if (!one('SELECT 1 FROM classes WHERE id = ?', [$classId]))
        err('Wybierz klasę');

    q('INSERT OR IGNORE INTO subjects(name) VALUES(?)', [$name]);
    $subjectId = (int)one('SELECT id FROM subjects WHERE name = ?', [$name])['id'];
    q('INSERT OR IGNORE INTO teacher_subjects VALUES(?,?)', [$U['id'], $subjectId]);
    q('INSERT OR IGNORE INTO assignments(teacher_id, class_id, subject_id) VALUES(?,?,?)', [$U['id'], $classId, $subjectId]);
    $assignmentId = (int)one('SELECT id FROM assignments WHERE teacher_id = ? AND class_id = ? AND subject_id = ?', [$U['id'], $classId, $subjectId])['id'];

    $weekday = (int)g('weekday');
    $lessonNo = (int)g('lesson_no');
    if ($weekday && $lessonNo) {
        $busy = one('SELECT 1 FROM schedule sc JOIN assignments x ON x.id = sc.assignment_id WHERE sc.weekday = ? AND sc.lesson_no = ? AND (x.class_id = ? OR x.teacher_id = ?)', [$weekday, $lessonNo, $classId, $U['id']]);
        if ($busy)
            err('Ta godzina jest już zajęta (klasa lub nauczyciel)');
        q('INSERT INTO schedule(assignment_id, weekday, lesson_no) VALUES(?,?,?)', [$assignmentId, $weekday, $lessonNo]);
    }
    logit('custom_lesson_save', $name);
    out(['ok' => 1]);
}
