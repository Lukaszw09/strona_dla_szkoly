<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/config.php';
require dirname(__DIR__) . '/src/db.php';

seedDemoFixture(db());
seedDemoAllClasses(db());

echo "Dane demonstracyjne gotowe.\n";
echo "Konta dodatkowe (hasło: demo12345):\n";
echo "- demo.nauczyciel@szkola.local\n";
echo "- demo.nauczyciel.grupa@szkola.local\n";
echo "- demo.rodzic@szkola.local\n";
echo "- demo.uczen@szkola.local\n";
echo "Dla każdej klasy dodano oznaczonych uczniów oraz przykładową frekwencję i oceny.\n";
echo "Przedmiot „pades” został zmieniony na „Projektowanie aplikacji desktopowych”, jeśli nie istniał już pod tą nazwą.\n";
