# Dziennik elektroniczny – ZSTI Gliwice

Stack: HTML + vanilla JS (front), PHP 8 (API w `api.php`), SQLite.

## Uruchomienie
Wymagania: PHP 8.0+ z rozszerzeniami `pdo_sqlite` i `mbstring`.

    cd strona_dla_szkoly
    php -S localhost:8000

Otwórz http://localhost:8000. Przy pierwszym wywołaniu powstaje baza z danymi demonstracyjnymi. Baza i logi resetu hasła są domyślnie przechowywane poza katalogiem publicznym, w ukrytym katalogu obok katalogu projektu. W razie potrzeby ustaw `APP_DATA_DIR` na bezwzględną ścieżkę poza katalogiem publicznym. Istniejąca baza `data/dziennik.sqlite` i `data/mail.log` zostaną automatycznie przeniesione przy pierwszym żądaniu API.

Konta demo (hasło `haslo123`): admin@, dyrektor@, sekretariat@, nauczyciel@, wychowawca@, rodzic@, uczen@ `szkola.local`.
Katalog `data/` ma dodatkową blokadę `.htaccess` dla Apache. Przed wdrożeniem zmień hasła demo i skonfiguruj HTTPS oraz pocztę do resetu hasła.

## Zakres (tylko zgłoszenia „W toku” i „Gotowe”)
- TEST-3/TEST-1 Fundamenty, logowanie: struktura projektu, SQLite, logowanie
- TEST-16 Uwierzytelnianie, role, uprawnienia: logowanie, reset hasła (token w lokalnym logu, gdy brak poczty), 7 ról, uprawnienia egzekwowane w `api.php`, blokada konta po 5 błędach, wygasanie sesji po 30 min, logi dostępu. Opcjonalne 2FA nie jest zaimplementowane.
- TEST-4 Zarządzanie danymi szkoły: klasy, uczniowie, nauczyciele, rodzice, powiązania (w tym zakaz dostępu), przedmioty, przydziały, wyszukiwarka, przenoszenie uczniów z historią
- TEST-144 Dane szkoły i plan lekcji: plan tygodniowy klasy
- TEST-37 Dziennik: tematy lekcji, 5 statusów frekwencji, zgłaszanie i zatwierdzanie usprawiedliwień, historia miesięczna, powiadomienia o nieobecności, statystyki i lista poniżej progu, braki we frekwencji

Frekwencja w statystykach = (obecny + spóźniony) / wszystkie wpisy.

## Dodane w tej wersji
- Dane startowe do demonstracji dziennika.
- CMS strony publicznej: sekcje, menu, ogłoszenia, kalendarz, galeria, dokumenty, jadłospis i newsletter.
- Osobna podstrona „Szkolenie” z krótkimi tutorialami dla każdej z 7 ról.
- Komunikacja wewnętrzna oraz widok RODO z eksportem danych i historią zgód.
- Migracja uczniów z JSON/CSV, zgłoszenia błędów i sugestii.
- Pulpit rodzica/ucznia: wybór powiązanego dziecka, dzisiejszy plan, ostatnie oceny, frekwencja i uwagi; uprawnienia do danych dziecka są sprawdzane przez API.
- Rodzice i uczniowie mogą przeglądać oceny i uwagi. Nowa ocena wysyła powiadomienie do ucznia i nieograniczonych opiekunów.
- Konta zakłada administrator lub sekretariat; publiczna samodzielna rejestracja nie jest dostępna.
- Dane demonstracyjne można bezpiecznie dodać do istniejącej bazy poleceniem `php scripts/seed_demo.php`. Tworzy oznaczoną klasę `DEMO - 1A`, przykładowe konta i rekordy w dostępnych modułach oraz po dwóch oznaczonych uczniów w każdej klasie. Seeder można uruchamiać wielokrotnie bez powielania danych; wszystkie sztuczne rekordy mają nazwę lub opis `DEMO`.
- Konta demo: `demo.nauczyciel@szkola.local`, `demo.nauczyciel.grupa@szkola.local`, `demo.rodzic@szkola.local`, `demo.uczen@szkola.local` (hasło `demo12345`).
- Przedmiot `pades` jest zmieniany na `Projektowanie aplikacji desktopowych` przy uruchomieniu seedera, o ile nazwa docelowa nie istnieje jeszcze w bazie.
- Własne lekcje: zakładka „Lekcja i frekwencja” → „Dodaj własną lekcję” (zapis w bazie, opcjonalnie wpis do planu).
- Oceny i uwagi (zakładka „Oceny i uwagi”), ocena zachowania (API `conduct_save`).
- Raporty: TEST-29 świadectwo, TEST-30 arkusz ocen, TEST-31 zestawienie klasy, TEST-32 statystyki szkoły,
  TEST-33 wyróżnieni (średnia ≥ 4,75, zachowanie co najmniej bardzo dobre, brak „1”), TEST-34 zagrożenia,
  TEST-35 wydruk dziennika lekcyjnego, TEST-36 eksport CSV do SIO. Wydruk: przycisk „Drukuj”.
- Ocena końcowa z przedmiotu = ostatnia roczna, a bez niej zaokrąglona średnia ocen cząstkowych.
- Struktura kodu:
  - `api.php` – punkt wejścia (sesja, routing)
  - `src/` – `config.php`, `db.php`, `schema.php`, `seed.php`, `helpers.php`, `grading.php`
  - `src/routes/` – akcje API pogrupowane tematycznie (auth, school, journal, grades, reports, export, custom_lessons)
  - `js/core.js` – narzędzia i stan, `js/views/*.js` – po jednym pliku na widok, `js/routes.js` – lista widoków, logowanie, nawigacja
- Dane z `data/dziennik.sqlite` są zachowywane i przenoszone poza katalog publiczny; nie usuwaj ich, jeśli chcesz zachować plan i historię.
- Testy: `php tests/smoke.php` oraz `php tests/auth_integration.php` (izolowana baza tymczasowa).
