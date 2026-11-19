<?php
// Punkt wejścia API: sesja, routing akcji
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
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$A = $_GET['action'] ?? '';

// sesja wygasa po 30 min bezczynności
if (isset($_SESSION['last']) && time() - $_SESSION['last'] > SESSION_TTL) {
    session_unset();
}
$U = $_SESSION['u'] ?? null;
if ($U)
    $_SESSION['last'] = time();

try {
    // trasy z instrukcją switch (kończą się out()/err(), jeśli akcja pasuje)
    foreach (['route_auth', 'route_school', 'route_journal'] as $route) {
        $route($A);
    }
    // pozostałe akcje to funkcje act_<nazwa>() z src/routes/*.php
    $handler = 'act_' . preg_replace('/\W/', '', $A);
    if (!function_exists($handler))
        err('Nieznana akcja', 404);
    $handler();
} catch (PDOException $e) {
    err('Błąd bazy danych', 500);
}
