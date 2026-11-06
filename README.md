# Dziennik elektroniczny – liceum ogólnokształcące

Stack: HTML + vanilla JS (front), PHP 8 (API w `api.php`), SQLite (plik `data/dziennik.sqlite`, tworzony automatycznie).

## Uruchomienie
Wymagania: PHP 8.0+ z rozszerzeniami `pdo_sqlite` i `mbstring`.

    cd dziennik
    php -S localhost:8000

Otwórz http://localhost:8000. Przy pierwszym wywołaniu powstaje baza z danymi demo.
Konta demo (hasło `haslo123`): admin@, dyrektor@, sekretariat@, nauczyciel@, wychowawca@, rodzic@, uczen@ `szkola.local`.
Na Apache/Nginx zadbaj, by katalog `data/` nie był dostępny z internetu (jest `.htaccess` dla Apache). Przed wdrożeniem zmień hasła demo.

## Zakres (tylko zgłoszenia „W toku” i „Gotowe”)
- TEST-3/TEST-1 Fundamenty, logowanie: struktura projektu, SQLite, logowanie
- TEST-16 Uwierzytelnianie, role, uprawnienia: logowanie, reset hasła (token w `data/mail.log`, gdy brak poczty), 7 ról, uprawnienia egzekwowane w `api.php`, blokada konta po 5 błędach, wygasanie sesji po 30 min, logi dostępu. Opcjonalne 2FA nie jest zaimplementowane.
- TEST-4 Zarządzanie danymi szkoły: klasy, uczniowie, nauczyciele, rodzice, powiązania (w tym zakaz dostępu), przedmioty, przydziały, wyszukiwarka, przenoszenie uczniów z historią
- TEST-144 Dane szkoły i plan lekcji: plan tygodniowy klasy
- TEST-37 Dziennik: tematy lekcji, 5 statusów frekwencji, zgłaszanie i zatwierdzanie usprawiedliwień, historia miesięczna, powiadomienia o nieobecności, statystyki i lista poniżej progu, braki we frekwencji

Frekwencja w statystykach = (obecny + spóźniony) / wszystkie wpisy.

## Dodane w tej wersji
- Dane demo dla liceum (klasy 1A/1B, przedmioty licealne).
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
- Uwaga: jeśli masz starą bazę `data/dziennik.sqlite`, usuń ją – schemat doczyta się od nowa.
