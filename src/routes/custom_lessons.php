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

    $weekdayInput = g('weekday');
    $lessonNoInput = g('lesson_no');
    $hasWeekday = $weekdayInput !== null && $weekdayInput !== '';
    $hasLessonNo = $lessonNoInput !== null && $lessonNoInput !== '';
    if ($hasWeekday !== $hasLessonNo)
        err('Podaj jednocześnie dzień i numer lekcji');
    if ($hasWeekday) {
        $weekday = filter_var($weekdayInput, FILTER_VALIDATE_INT);
        $lessonNo = filter_var($lessonNoInput, FILTER_VALIDATE_INT);
        if ($weekday === false || $weekday < 1 || $weekday > 5 || $lessonNo === false || $lessonNo < 1 || $lessonNo > 10)
            err('Nieprawidłowy dzień lub numer lekcji');
    }

    $p = db();
    $p->beginTransaction();
    try {
        if ($hasWeekday && one('SELECT 1 FROM schedule sc JOIN assignments x ON x.id = sc.assignment_id WHERE sc.weekday = ? AND sc.lesson_no = ? AND (x.class_id = ? OR x.teacher_id = ?)', [$weekday, $lessonNo, $classId, $U['id']])) {
            $p->rollBack();
            err('Ta godzina jest już zajęta (klasa lub nauczyciel)');
        }

        q('INSERT OR IGNORE INTO subjects(name) VALUES(?)', [$name]);
        $subjectId = (int)one('SELECT id FROM subjects WHERE name = ?', [$name])['id'];
        q('INSERT OR IGNORE INTO teacher_subjects VALUES(?,?)', [$U['id'], $subjectId]);
        q('INSERT OR IGNORE INTO assignments(teacher_id, class_id, subject_id) VALUES(?,?,?)', [$U['id'], $classId, $subjectId]);
        $assignmentId = (int)one('SELECT id FROM assignments WHERE teacher_id = ? AND class_id = ? AND subject_id = ?', [$U['id'], $classId, $subjectId])['id'];
        if ($hasWeekday)
            q('INSERT INTO schedule(assignment_id, weekday, lesson_no) VALUES(?,?,?)', [$assignmentId, $weekday, $lessonNo]);
        logit('custom_lesson_save', $name);
        $p->commit();
    } catch (Throwable $e) {
        if ($p->inTransaction())
            $p->rollBack();
        throw $e;
    }

    out(['ok' => 1]);
}
