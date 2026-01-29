# WP Deweloper Gov Reporter (Plan Projektu)

## 1. Wstęp
Wtyczka **WP Deweloper Gov Reporter** ma na celu automatyzację obowiązków wynikających z nowelizacji przepisów (wchodzących w życie 11 lipca 2025 r.), nakładających na deweloperów wymóg codziennego raportowania cen mieszkań do portalu rządowego `dane.gov.pl` oraz publikowania pełnej historii cen na własnych stronach internetowych.

## 2. Główne Funkcjonalności
1.  **Zarządzanie Ofertą (Inwestycje i Lokale):** Dedykowane typy wpisów (Custom Post Types) do zarządzania bazą nieruchomości.
2.  **Historia Cen:** Automatyczne rejestrowanie każdej zmiany ceny lokalu wraz z datą zmiany.
3.  **Integracja z API dane.gov.pl:** Automatyczne generowanie i wysyłanie raportów dziennych (format JSON/XML).
4.  **Prezentacja na WWW:** Shortcod’y i bloki Gutenberga do wyświetlania historii cen oraz szczegółów oferty na stronie frontowej.
5.  **Logi i Powiadomienia:** Rejestr wysyłek do API oraz alerty e-mail w przypadku błędów raportowania.

## 3. Architektura Techniczna

### 3.1. Struktura Danych (Custom Post Types)
Wtyczka zarejestruje dwa typy wpisów:
*   **Inwestycja (`investment`)**:
    *   Pola: Nazwa, Adres, ID Inwestycji (nadane przez urząd), NIP dewelopera.
*   **Lokal (`unit`)**:
    *   Rodzic: Przypisany do Inwestycji.
    *   Pola (Meta Fields):
        *   `unit_id` (wewnętrzny numer)
        *   `price_total` (cena całkowita brutto)
        *   `price_m2` (cena za m²)
        *   `area` (powierzchnia)
        *   `rooms` (liczba pokoi)
        *   `floor` (piętro)
        *   `status` (dostępny, zarezerwowany, sprzedany)
        *   `dependencies` (lista przynależności np. garaż - JSON)
    *   **Historia Cen (`price_history`)**: Pole typu Repeater lub serializowana tablica przechowująca historię zmian: `[{date: '2025-07-11', price: 500000}, ...]`.

### 3.2. Integracja z API (Specyfikacja)
Raportowanie będzie odbywać się codziennie (zalecana godzina nocna, np. 01:00) za pomocą zadania CRON.

**Przykładowy format danych (JSON) zgodny z wytycznymi:**
```json
{
  "inwestycja_id": "INV-001",
  "lokal_id": "M12",
  "powierzchnia_m2": 56.7,
  "pokoje": 3,
  "kondygnacja": 2,
  "cena_m2": 8900,
  "cena_calkowita": 504630,
  "status": "dostępny",
  "przynaleznosci": [
    {"typ": "miejsce_postojowe", "cena": 45000},
    {"typ": "komorka_lokatorska", "cena": 18000}
  ],
  "historia_cen": [
    {"data": "2025-05-01", "cena_m2": 8700},
    {"data": "2025-07-15", "cena_m2": 8900}
  ]
}
```

### 3.3. Automatyzacja (WP-Cron)
Zadanie `wp_dew_gov_daily_report`:
1.  Pobiera wszystkie aktywne lokale.
2.  Formatuje dane do struktury JSON.
3.  Wysyła zapytanie POST na endpoint API (konfigurowalny w ustawieniach).
4.  Zapisuje status wysyłki w logach wtyczki.

## 4. Plan Wdrożenia (Roadmapa)

### Faza 1: Struktura i Zarządzanie Danymi
*   [ ] Inicjalizacja wtyczki (boilerplate).
*   [ ] Rejestracja CPT `investment` i `unit`.
*   [ ] Dodanie pól niestandardowych (Meta Boxes) dla cen, metrażu i statusów.
*   [ ] Implementacja mechanizmu "Hook on Save": przy zapisie lokalu sprawdź, czy cena się zmieniła -> jeśli tak, dopisz do historii cen.

### Faza 2: Integracja API
*   [ ] Stworzenie strony ustawień (API Key, Endpoint URL, dane dewelopera).
*   [ ] Implementacja klasy `ReportGenerator` tworzącej JSON.
*   [ ] Implementacja klasy `ApiConnector` do komunikacji z `dane.gov.pl`.

### Faza 3: Automatyzacja
*   [ ] Konfiguracja WP-Cron (Harmonogram zadań).
*   [ ] Obsługa błędów (Retries) i logowanie wyników wysyłki.

### Faza 4: Frontend (Prezentacja)
*   [ ] Shortcode `[price_history]` wyświetlający wykres lub tabelę zmian cen.
*   [ ] Shortcode `[unit_details]` wyświetlający wymagane prawem informacje.

### Faza 5: Testy i Weryfikacja
*   [ ] Walidacja danych przed wysyłką (np. ujemne ceny, brak metrażu).
*   [ ] Testy obciążeniowe (dla dużej liczby lokali).

## 5. Instrukcja Instalacji (Wstępna)
1.  Pobierz wtyczkę i zainstaluj w WordPress.
2.  W menu "Deweloper Gov" przejdź do "Ustawienia".
3.  Wprowadź klucz API uzyskany z `dane.gov.pl` oraz identyfikator inwestycji.
4.  Uzupełnij bazę lokali w zakładce "Lokale" (lub zaimportuj CSV - opcja w przyszłości).
5.  Plugin automatycznie rozpocznie wysyłkę danych każdego dnia o 01:00.
