<?php
// Raporty i dokumenty (TEST-29..35)
// ---------- TEST-29: świadectwo szkolne ----------

function act_report_certificate() {
    need('admin', 'dyrektor', 'sekretariat', 'wychowawca');
    $student = studentRow((int)g('student_id'));
    needClass($student['class_id']);
    $summary = studentSummary($student['id']);
    out([ 'student' => ['first' => $student['first'], 'last' => $student['last'], 'birth' => $student['birth'], 'class' => $student['class'], 'conduct' => $student['conduct']], 'grades' => $summary['finals'], 'avg' => $summary['avg'], 'promoted' => !$summary['failed'] && !empty($summary['finals']), 'honors' => hasHonors($student, $summary), 'school_year' => schoolYear(), ]);
}

function schoolYear() {
    $y = (int)date('Y');
    $start = (int)date('n') >= 9 ? $y : $y - 1;
    return $start . '/' . ($start + 1);
}

// ---------- TEST-30: arkusz ocen ucznia ----------

function act_report_sheet() {
    need('admin', 'dyrektor', 'sekretariat', 'wychowawca');
    $student = studentRow((int)g('student_id'));
    needClass($student['class_id']);
    $grades = all('SELECT g.value, g.kind, g.date, s.name subject FROM grades g JOIN subjects s ON s.id = g.subject_id WHERE g.student_id = ? ORDER BY s.name, g.id', [$student['id']]);
    $remarks = all('SELECT text, date FROM remarks WHERE student_id = ? ORDER BY id', [$student['id']]);
    $summary = studentSummary($student['id']);
    $attendance = one("SELECT COUNT(*) total, COALESCE(SUM(status IN('obecny','spóźniony')),0) present FROM attendance WHERE student_id = ?", [$student['id']]);
    out([ 'student' => ['first' => $student['first'], 'last' => $student['last'], 'class' => $student['class'], 'conduct' => $student['conduct']], 'grades' => $grades, 'finals' => $summary['finals'], 'avg' => $summary['avg'], 'remarks' => $remarks, 'attendance_pct' => $attendance['total'] ? round(100 * $attendance['present'] / $attendance['total'], 1) : null, ]);
}

// ---------- TEST-31: zestawienie ocen klasy ----------

function act_report_class() {
    need('admin', 'dyrektor', 'sekretariat', 'wychowawca');
    $classId = (int)g('class_id');
    needClass($classId);
    $rows = [];
    $subjects = [];
    foreach (classStudents($classId) as $s) {
        $summary = studentSummary($s['id']);
        $subjects = array_unique(array_merge($subjects, array_keys($summary['finals'])));
        $rows[] = ['student' => $s['last'] . ' ' . $s['first'], 'finals' => $summary['finals'], 'avg' => $summary['avg']];
    }
    sort($subjects);
    out(['subjects' => $subjects, 'rows' => $rows]);
}

// ---------- TEST-32: statystyki szkoły ----------

function act_report_school() {
    need('admin', 'dyrektor');
    $result = [];
    foreach (all('SELECT * FROM classes ORDER BY name') as $class) {
        $averages = [];
        foreach (classStudents($class['id']) as $s) {
            $avg = studentSummary($s['id'])['avg'];
            if ($avg !== null)
                $averages[] = $avg;
        }
        $att = one("SELECT COUNT(*) total, COALESCE(SUM(a.status IN('obecny','spóźniony')),0) present FROM attendance a JOIN students s ON s.id = a.student_id WHERE s.class_id = ?", [$class['id']]);
        $remarks = one('SELECT COUNT(*) c FROM remarks r JOIN students s ON s.id = r.student_id WHERE s.class_id = ?', [$class['id']]);
        $result[] = [ 'class' => $class['name'], 'students' => (int)one('SELECT COUNT(*) c FROM students WHERE class_id = ?', [$class['id']])['c'], 'avg' => $averages ? round(array_sum($averages) / count($averages), 2) : null, 'attendance_pct' => $att['total'] ? round(100 * $att['present'] / $att['total'], 1) : null, 'remarks' => (int)$remarks['c'], ];
    }
    out($result);
}

// ---------- TEST-33: uczniowie wyróżnieni ----------

function act_report_honors() {
    need('admin', 'dyrektor', 'sekretariat', 'wychowawca');
    $result = [];
    foreach (all('SELECT s.*, c.name class FROM students s JOIN classes c ON c.id = s.class_id ORDER BY c.name, s.last') as $s) {
        if (!canSeeClass($s['class_id']))
            continue;
        $summary = studentSummary($s['id']);
        if (hasHonors($s, $summary)) {
            $result[] = ['student' => $s['last'] . ' ' . $s['first'], 'class' => $s['class'], 'avg' => $summary['avg'], 'conduct' => $s['conduct']];
        }
    }
    usort($result, fn($a, $b) => $b['avg'] <=> $a['avg']);
    out($result);
}

// ---------- TEST-34: zagrożenia ocenami niedostatecznymi ----------

function act_report_threats() {
    need('admin', 'dyrektor', 'sekretariat', 'wychowawca');
    $classId = (int)g('class_id');
    needClass($classId);
    $result = [];
    foreach (classStudents($classId) as $s) {
        $summary = studentSummary($s['id']);
        if ($summary['failed']) {
            $result[] = ['student' => $s['first'] . ' ' . $s['last'], 'class' => $s['class'], 'subjects' => $summary['failed']];
        }
    }
    out($result);
}

// ---------- TEST-35: wydruk dziennika lekcyjnego ----------

function act_report_journal() {
    global $U;
    need('nauczyciel', 'wychowawca');
    [$from, $to] = validPeriod();
    $a = one('SELECT a.*, s.name subject, c.name class FROM assignments a JOIN subjects s ON s.id = a.subject_id JOIN classes c ON c.id = a.class_id WHERE a.id = ? AND a.teacher_id = ?', [g('assignment_id'), $U['id']]);
    if (!$a)
        err('To nie jest Twoja lekcja', 403);

    $lessons = all('SELECT id, date, lesson_no, topic FROM lessons WHERE assignment_id = ? AND date BETWEEN ? AND ? ORDER BY date, lesson_no', [$a['id'], $from, $to]);
    foreach ($lessons as &$l) {
        $l['absent'] = array_column(all("SELECT s.last || ' ' || s.first name FROM attendance t JOIN students s ON s.id = t.student_id WHERE t.lesson_id = ? AND t.status IN('nieobecny','usprawiedliwiony') ORDER BY s.last", [$l['id']]), 'name');
        $l['attendance_count'] = (int)one('SELECT COUNT(*) c FROM attendance WHERE lesson_id = ? AND status IN(\'obecny\',\'spóźniony\')', [$l['id']])['c'];
    }
    out(['class' => $a['class'], 'subject' => $a['subject'], 'from' => $from, 'to' => $to, 'lessons' => $lessons]);
}
