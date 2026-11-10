<?php
// Logika ocen: ocena końcowa, średnie, wyróżnienia, uprawnienia do klas

/** Role mogące oglądać raporty całej klasy lub szkoły. */
function canSeeClass($classId) {
    global $U;
    if (in_array($U['role'], MGMT))
        return true;
    return $U['role'] == 'wychowawca' && in_array($classId, homeIds());
}

function needClass($classId) {
    if (!canSeeClass($classId)) {
        logit('DENIED', 'class ' . $classId);
        err('Brak dostępu do tej klasy', 403);
    }
}

/** Ocena końcowa z przedmiotu: ostatnia roczna, a gdy jej brak - zaokrąglona średnia ocen cząstkowych. */
function finalGrade($grades) {
    $annual = array_values(array_filter($grades, fn($g) => $g['kind'] == 'roczna'));
    if ($annual)
        return (int)end($annual)['value'];
    $partial = array_filter($grades, fn($g) => $g['kind'] == 'cząstkowa');
    if (!$partial)
        return null;
    return (int)round(array_sum(array_column($partial, 'value')) / count($partial));
}

/** Zwraca ['przedmiot' => ocena końcowa|null] oraz średnią ze wszystkich ocen końcowych. */
function studentSummary($studentId) {
    $rows = all('SELECT g.*, s.name subject FROM grades g JOIN subjects s ON s.id = g.subject_id WHERE g.student_id = ? ORDER BY g.id', [$studentId]);
    $bySubject = [];
    foreach ($rows as $row)
        $bySubject[$row['subject']][] = $row;

    $finals = [];
    foreach ($bySubject as $subject => $list)
        $finals[$subject] = finalGrade($list);

    $values = array_filter($finals, fn($v) => $v !== null);
    $avg = $values ? round(array_sum($values) / count($values), 2) : null;
    return ['finals' => $finals, 'avg' => $avg, 'failed' => array_keys(array_filter($values, fn($v) => $v < 2))];
}

function studentRow($studentId) {
    $s = one('SELECT s.*, c.name class FROM students s LEFT JOIN classes c ON c.id = s.class_id WHERE s.id = ?', [$studentId]);
    if (!$s)
        err('Nie znaleziono ucznia', 404);
    return $s;
}

function hasHonors($student, $summary) {
    return $summary['avg'] !== null && $summary['avg'] >= HONORS_MIN_AVG && !$summary['failed'] && in_array($student['conduct'], HONORS_CONDUCTS);
}

function classStudents($classId) {
    return all('SELECT s.*, c.name class FROM students s LEFT JOIN classes c ON c.id = s.class_id WHERE s.class_id = ? ORDER BY s.last, s.first', [$classId]);
}

function validPeriod() {
    $from = g('from');
    $to = g('to');
    if (!validDate($from) || !validDate($to) || $to < $from)
        err('Podaj poprawny okres');
    return [$from, $to];
}
