<?php
// Eksport danych do SIO (TEST-36)

function act_sio_export() {
    need('admin', 'dyrektor', 'sekretariat');
    logit('sio_export');
    header('Content-Type: text/csv; charset=utf-8');
    $lines = [['Lp', 'Nazwisko', 'Imie', 'PESEL', 'Data urodzenia', 'Adres', 'Oddzial']];
    foreach (all('SELECT s.*, c.name class FROM students s LEFT JOIN classes c ON c.id = s.class_id ORDER BY c.name, s.last, s.first') as $i => $s) {
        $lines[] = [$i + 1, $s['last'], $s['first'], $s['pesel'], $s['birth'], $s['address'], $s['class']];
    }
    $csv = "\xEF\xBB\xBF";
    // BOM, żeby Excel poprawnie czytał polskie znaki
    foreach ($lines as $fields) {
        $csv .= implode(';', array_map(fn($f) => '"' . str_replace('"', '""', (string)$f) . '"', $fields)) . "\r\n";
    }
    echo $csv;
    exit;
}
