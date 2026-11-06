<?php

require __DIR__ . '/src/config.php';
require __DIR__ . '/src/db.php';
require __DIR__ . '/src/helpers.php';
foreach (glob(__DIR__ . '/src/*.php') as $file) {
    require_once $file;
}
foreach (glob(__DIR__ . '/src/routes/*.php') as $file) {
    require_once $file;
}

session_set_cookie_params(['samesite' => 'Lax', 'httponly' => true]);
session_start();
header('Content-Type: application/json; charset=utf-8');

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$A = $_GET['action'] ?? '';


if (isset($_SESSION['last']) && time() - $_SESSION['last'] > SESSION_TTL) {
    session_unset();
}
$U = $_SESSION['u'] ?? null;
if ($U)
    $_SESSION['last'] = time();

try {

    foreach (['route_auth', 'route_school', 'route_journal'] as $route) {
        $route($A);
    }

    $handler = 'act_' . preg_replace('/\W/', '', $A);
    if (!function_exists($handler))
        err('Nieznana akcja', 404);
    $handler();
} catch (PDOException $e) {
    err('Błąd bazy danych', 500);
}

