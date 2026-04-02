# WP Deweloper Gov Reporter

## 1. Wstęp
Wtyczka **WP Deweloper Gov Reporter** automatyzuje obowiązki wynikające z nowelizacji ustawy deweloperskiej (od 11.07.2025), nakładające na deweloperów wymóg codziennego udostępniania cen mieszkań do systemu `dane.gov.pl` oraz publikowania pełnej historii cen na własnych stronach internetowych.

## 2. Jak działa raportowanie do dane.gov.pl?

**WAŻNE:** System dane.gov.pl działa w modelu PULL (pobierania), nie PUSH (wysyłania):

1. Wtyczka **generuje plik XML** z cenami wszystkich lokali (codziennie o 01:00 przez WP-Cron lub ręcznie).
2. Plik XML i jego **suma kontrolna MD5** są dostępne pod stałymi adresami URL na Twoim serwerze.
3. **Rejestrujesz** te adresy URL w dane.gov.pl (mail na `kontakt@dane.gov.pl` z danymi firmy).
4. **System dane.gov.pl automatycznie pobiera** dane z Twojego serwera raz dziennie.

### Przykładowe URL (po wygenerowaniu):
- XML: `https://twojastrona.pl/wp-content/uploads/dgr-gov-reports/oferta.xml`
- MD5: `https://twojastrona.pl/wp-content/uploads/dgr-gov-reports/oferta.xml.md5`

## 3. Główne Funkcjonalności
1. **Zarządzanie Ofertą (Inwestycje i Lokale):** Dedykowane typy wpisów (Custom Post Types).
2. **Historia Cen:** Automatyczne rejestrowanie każdej zmiany ceny lokalu z datą.
3. **Generowanie XML dla dane.gov.pl:** Automatyczne tworzenie pliku XML i MD5, walidacja danych przed generowaniem, powiadomienia email o błędach.
4. **Prezentacja na WWW:** Shortcody, bloki Gutenberga oraz Widgety Elementora (historia cen, szczegóły lokalu, lista lokali z filtrami AJAX).
5. **Panel Administracyjny:** Dashboard widget, kolumny admina, Quick Edit, Bulk Actions, filtrowanie po inwestycji, eksport CSV.
6. **Dyrektywa Omnibus:** Wyświetlanie najniższej ceny z ostatnich 30 dni.

## 4. Architektura Techniczna

### 4.1. Struktura Danych (Custom Post Types)
*   **Inwestycja (`dgr_investment`)**: Nazwa, Adres, ID Gov, NIP dewelopera.
*   **Lokal (`dgr_unit`)**: Przypisany do Inwestycji. Pola: numer lokalu, cena brutto, cena/m², powierzchnia, pokoje, piętro, status (7 statusów), przynależności (JSON), historia cen.

### 4.2. Generowanie XML (WP-Cron)
Codziennie o 01:00 wtyczka:
1. Zbiera dane wszystkich opublikowanych lokali (paginacja, bez memory issues).
2. Waliduje dane (brakujące ID, zerowe ceny, ujemne powierzchnie).
3. Generuje XML zgrupowany po inwestycjach.
4. Zapisuje `oferta.xml` i `oferta.xml.md5` w `wp-content/uploads/dgr-gov-reports/`.
5. W razie błędów — wysyła email do admina i loguje problem.

## 5. Plan Wdrożenia (Roadmapa)

### Faza 1: Struktura i Zarządzanie Danymi
*   [x] Inicjalizacja wtyczki.
*   [x] Rejestracja CPT `dgr_investment` i `dgr_unit`.
*   [x] Meta boxy z walidacją numeryczną i JSON.
*   [x] Mechanizm historii cen (hook on save).

### Faza 2: Generowanie XML dla dane.gov.pl
*   [x] Generator XML z pełną walidacją danych.
*   [x] Generowanie sumy kontrolnej MD5.
*   [x] Serwowanie plików pod stałymi URL.
*   [x] Strona ustawień z panelem statusu i URL-ami.

### Faza 3: Automatyzacja
*   [x] WP-Cron o stałej godzinie 01:00.
*   [x] Lock zapobiegający duplikatom.
*   [x] Przycisk "Generuj raport teraz".
*   [x] Powiadomienia email o błędach.
*   [x] Wykrywanie przestarzałych raportów (notice w adminie).

### Faza 4: Frontend (Prezentacja)
*   [x] Shortcode `[dgr_price_history]` — wykres Chart.js + tabela.
*   [x] Shortcode `[dgr_unit_details]` — karta lokalu z Omnibus.
*   [x] Shortcode `[dgr_unit_list]` — lista z filtrami AJAX.
*   [x] Widgety Elementora (3 widgety).

### Faza 5: Bezpieczeństwo i Optymalizacja
*   [x] Walidacja danych przed generowaniem XML.
*   [x] Sanityzacja i escaping we wszystkich widokach.
*   [x] ABSPATH guards na wszystkich plikach.
*   [x] Paginacja zapytań (brak `posts_per_page => -1`).
*   [x] Poprawne nonce/capability checks wszędzie.
*   [ ] Testy obciążeniowe (dla dużej liczby lokali).

## 6. Instrukcja Instalacji
1. Pobierz wtyczkę i zainstaluj w WordPress.
2. W menu **"Deweloper Gov"** przejdź do **"Ustawienia"**.
3. Uzupełnij **Nazwę Firmy** i **NIP**.
4. Dodaj Inwestycje i Lokale.
5. Kliknij **"Generuj raport teraz"** aby wygenerować pierwszy XML.
6. Skopiuj wyświetlone **adresy URL** (XML i MD5).
7. Wyślij maila na `kontakt@dane.gov.pl` z: nazwa firmy, NIP, KRS, osoba kontaktowa, oba URL, częstotliwość aktualizacji (codziennie).
8. Raport będzie automatycznie aktualizowany codziennie o 01:00.
