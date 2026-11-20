<?php
// Funkcje pomocnicze: wejście/wyjście API, baza, uprawnienia
function g($k, $d = null) {
    global $in;
    return $in[$k] ?? $_GET[$k] ?? $d;
}
function out($d, $c = 200) {
    http_response_code($c);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}
function err($m, $c = 400) {
    out(['error' => $m], $c);
}
function q($s, $a = []) {
    $x = db()->prepare($s);
    $x->execute($a);
    return $x;
}
function all($s, $a = []) {
    return q($s, $a)->fetchAll();
}
function one($s, $a = []) {
    return q($s, $a)->fetch();
}
function logit($act, $det = '') {
    global $U;
    q("INSERT INTO access_log(user_id,action,detail,ip,created) VALUES(?,?,?,?,datetime('now','localtime'))", [$U['id'] ?? null, $act, $det, $_SERVER['REMOTE_ADDR'] ?? '']);
}
function notify($uid, $t) {
    q("INSERT INTO notifications(user_id,text,created) VALUES(?,?,datetime('now','localtime'))", [$uid, $t]);
}
function need(...$roles) {
    global $U, $A;
    if (!$U)
        err('Zaloguj się', 401);
    if ($roles && !in_array($U['role'], $roles)) {
        logit('DENIED', $A);
        err('Brak uprawnień', 403);
    }
}
function isT() {
    global $U;
    return in_array($U['role'], ['nauczyciel', 'wychowawca']);
}
function homeIds() {
    global $U;
    return array_column(all('SELECT id FROM classes WHERE homeroom_id=?', [$U['id']]), 'id');
}
function myClassIds() {
    global $U;
    return array_unique(array_merge(homeIds(), array_column(all('SELECT class_id FROM assignments WHERE teacher_id=?', [$U['id']]), 'class_id')));
}
function canStudent($sid) {
    global $U;
    $s = one('SELECT * FROM students WHERE id=?', [$sid]);
    if (!$s)
        return false;
    $r = $U['role'];
    if (in_array($r, MGMT))
        return true;
    if ($r == 'rodzic')
        return (bool)one('SELECT 1 FROM parent_links WHERE parent_id=? AND student_id=? AND restricted=0', [$U['id'], $sid]);
    if ($r == 'uczen')
        return $s['user_id'] == $U['id'];
    return in_array($s['class_id'], myClassIds());
}
function needStudent($sid) {
    if (!canStudent($sid)) {
        logit('DENIED', 'student ' . $sid);
        err('Brak dostępu do tego ucznia', 403);
    }
}
function isHomeroomOf($sid) {
    $s = one('SELECT class_id FROM students WHERE id=?', [$sid]);
    return $s && in_array($s['class_id'], homeIds());
}
function validDate($d) {
    if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))
        return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    return $date !== false && $date->format('Y-m-d') === $d;
}
function meOut() {
    global $U;
    out(['user' => $U, 'unread' => $U ? (int)one('SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0', [$U['id']])['c'] : 0]);
}
