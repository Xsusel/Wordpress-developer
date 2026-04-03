# WP Deweloper Gov Reporter

## 1. Wstep

Wtyczka **WP Deweloper Gov Reporter** automatyzuje obowiazki wynikajace z nowelizacji ustawy deweloperskiej (od 11.07.2025), nakladajace na deweloperow wymog codziennego udostepniania cen mieszkan do systemu `dane.gov.pl` oraz publikowania pelnej historii cen na wlasnych stronach internetowych.

**Wersja:** 2.0.0
**Autor:** Jakub Wcislo
**Wymagania:** WordPress 5.8+, PHP 7.4+
**Opcjonalnie:** Elementor 3.0+ (dla widgetow)

---

## 2. Jak dziala raportowanie do dane.gov.pl?

**WAZNE:** System dane.gov.pl dziala w modelu PULL (pobierania), nie PUSH (wysylania):

1. Wtyczka **generuje plik XML** z cenami wszystkich lokali (codziennie o 01:00 przez WP-Cron lub recznie).
2. Plik XML i jego **suma kontrolna MD5** sa dostepne pod stalymi adresami URL na Twoim serwerze.
3. **Rejestrujesz** te adresy URL w dane.gov.pl (mail na `kontakt@dane.gov.pl` z danymi firmy).
4. **System dane.gov.pl automatycznie pobiera** dane z Twojego serwera raz dziennie.

### Przykladowe URL (po wygenerowaniu):
- XML: `https://twojastrona.pl/wp-content/uploads/dgr-gov-reports/oferta.xml`
- MD5: `https://twojastrona.pl/wp-content/uploads/dgr-gov-reports/oferta.xml.md5`

---

## 3. Glowne Funkcjonalnosci

1. **Zarzadzanie Oferta (Inwestycje i Lokale):** Dedykowane typy wpisow (Custom Post Types).
2. **Historia Cen:** Automatyczne rejestrowanie kazdej zmiany ceny lokalu z data.
3. **Generowanie XML dla dane.gov.pl:** Automatyczne tworzenie pliku XML i MD5, walidacja danych przed generowaniem, powiadomienia email o bledach.
4. **Cennik Lokali (Shortcody):** Indywidualne shortcody per lokal z cenami netto/brutto, stan surowy/deweloperski, metraz.
5. **Prezentacja na WWW:** Shortcody, widgety Elementora (historia cen, szczegoly lokalu, lista lokali z filtrami AJAX).
6. **Panel Administracyjny:** Dashboard widget, kolumny admina, Quick Edit, Bulk Actions, filtrowanie po inwestycji.
7. **Dyrektywa Omnibus:** Wyswietlanie najnizszej ceny z ostatnich 30 dni.

---

## 4. Architektura Techniczna

### 4.1. Struktura plikow

```
wp-deweloper-gov-reporter/
├── wp-deweloper-gov-reporter.php          # Glowny plik wtyczki
├── admin/
│   └── class-dgr-admin.php                # Panel admina, ustawienia, dashboard
├── assets/
│   ├── css/
│   │   ├── dgr-admin.css                  # Style admina
│   │   └── dgr-frontend.css               # Style frontendu (karty, tabele, shortcody)
│   └── js/
│       ├── dgr-admin.js                   # JS admina (NIP lookup)
│       └── dgr-frontend.js               # JS frontendu (AJAX filtrowanie, wykresy)
├── includes/
│   ├── class-dgr-post-types.php           # Rejestracja CPT
│   ├── class-dgr-metaboxes.php            # Meta boxy z polami cenowymi
│   ├── class-dgr-price-history.php        # Sledzenie historii cen
│   ├── class-dgr-api-connector.php        # Generowanie XML
│   ├── class-dgr-admin-columns.php        # Kolumny admina, Quick Edit, Bulk
│   └── elementor/
│       ├── class-dgr-elementor.php        # Rejestracja widgetow Elementora
│       └── widgets/
│           ├── widget-price-history.php   # Widget wykresu cen
│           ├── widget-unit-details.php    # Widget szczegolow lokalu
│           └── widget-unit-list.php       # Widget listy lokali
└── public/
    └── class-dgr-public.php               # Shortcody i AJAX
```

### 4.2. Struktura Danych (Custom Post Types)

#### Inwestycja (`dgr_investment`)
| Pole | Meta Key | Typ | Opis |
|------|----------|-----|------|
| Adres | `_dgr_investment_address` | text | Adres inwestycji |
| ID Gov | `_dgr_investment_id` | text | Identyfikator urzedowy |
| NIP | `_dgr_investment_nip` | text | NIP dewelopera (10 cyfr) |

#### Lokal (`dgr_unit`)
| Pole | Meta Key | Typ | Opis |
|------|----------|-----|------|
| Inwestycja | `_dgr_unit_parent_investment` | int | Post ID inwestycji nadrzednej |
| Numer Lokalu | `_dgr_unit_id` | text | Identyfikator lokalu (np. "M2.3.07") |
| Lokalizacja | `_dgr_unit_location_label` | text | Etykieta lokalizacji (np. "BOLMIN (25 KM OD KIELC)") |
| Cena Calkowita Brutto | `_dgr_unit_price_total` | float | Cena brutto PLN (do raportu gov) |
| Cena za m² Brutto | `_dgr_unit_price_m2` | float | Cena/m² brutto PLN (do raportu gov) |
| Stan Surowy - Netto | `_dgr_unit_price_shell_netto` | float | Cena netto za stan surowy zamkniety |
| Stan Surowy - Brutto | `_dgr_unit_price_shell_brutto` | float | Cena brutto za stan surowy zamkniety |
| Deweloperski - Netto | `_dgr_unit_price_developer_netto` | float | Cena netto za stan deweloperski |
| Deweloperski - Brutto | `_dgr_unit_price_developer_brutto` | float | Cena brutto za stan deweloperski |
| Powierzchnia | `_dgr_unit_area` | float | Powierzchnia uzytkowa w m² |
| Pokoje | `_dgr_unit_rooms` | int | Liczba pokoi |
| Pietro | `_dgr_unit_floor` | int | Numer pietra (0 = parter) |
| Status | `_dgr_unit_status` | text | Status lokalu (7 opcji) |
| Przynaleznosci | `_dgr_unit_dependencies` | JSON | Miejsca postojowe, komorki lokatorskie |
| Historia Cen | `_dgr_price_history` | array | Automatyczna historia zmian cen |

#### Statusy lokalu
| Wartosc | Opis |
|---------|------|
| `available` | Dostepny |
| `offer` | Oferta specjalna |
| `reserved` | Zarezerwowany |
| `reservation_agreement` | Umowa rezerwacyjna |
| `developer_agreement` | Umowa deweloperska |
| `sold` | Sprzedany |
| `transferred` | Przekazany |

### 4.3. Generowanie XML (WP-Cron)

Codziennie o 01:00 wtyczka:
1. Zbiera dane wszystkich opublikowanych lokali (paginacja po 100, bez memory issues).
2. Waliduje dane (brakujace ID, zerowe ceny, ujemne powierzchnie).
3. Generuje XML zgrupowany po inwestycjach.
4. Zapisuje `oferta.xml` i `oferta.xml.md5` w `wp-content/uploads/dgr-gov-reports/`.
5. W razie bledow - wysyla email do admina i loguje problem.

---

## 5. Shortcody - Dokumentacja

### 5.1. Karta Cenowa Lokalu

```
[dgr_lokal_karta id="123"]
[dgr_lokal_karta id="123" show_netto="no"]
```

Wyswietla pelna karte cenowa lokalu w stylu:

```
 BOLMIN (25 KM OD KIELC)         117,45 m²

 STAN SUROWY ZAMKNIETY           DEWELOPERSKI
 450 000 zl                      680 000 zl
 netto: 365 854 zl               netto: 552 846 zl
```

| Parametr | Domyslnie | Opis |
|----------|-----------|------|
| `id` | ID aktualnego postu | Post ID lokalu (`dgr_unit`) |
| `show_netto` | `yes` | Pokaz ceny netto pod brutto (`yes`/`no`) |

**Uzycie w Elementorze:** Wstaw widget "Shortcode" i wklej `[dgr_lokal_karta id="123"]`.

### 5.2. Pojedyncza Cena

```
[dgr_lokal_cena id="123" typ="deweloperski" vat="brutto"]
[dgr_lokal_cena id="123" typ="surowy" vat="netto"]
```

Zwraca sformatowana wartosc ceny (np. `680 000 zl`) - do wstawienia inline w tekscie.

| Parametr | Domyslnie | Opcje | Opis |
|----------|-----------|-------|------|
| `id` | ID aktualnego postu | | Post ID lokalu |
| `typ` | `deweloperski` | `surowy`, `deweloperski` | Typ ceny (stan surowy zamkniety / deweloperski) |
| `vat` | `brutto` | `netto`, `brutto` | Cena netto czy brutto |

**Przyklady:**
```
Cena deweloperska brutto: [dgr_lokal_cena id="123" typ="deweloperski" vat="brutto"]
Cena surowa netto: [dgr_lokal_cena id="123" typ="surowy" vat="netto"]
```

### 5.3. Metraz

```
[dgr_lokal_metraz id="123"]
```

Zwraca sformatowana powierzchnie (np. `117,45 m²`) - do uzycia inline.

| Parametr | Domyslnie | Opis |
|----------|-----------|------|
| `id` | ID aktualnego postu | Post ID lokalu |

### 5.4. Historia Cen

```
[dgr_price_history id="123"]
[dgr_price_history id="123" view="chart"]
[dgr_price_history id="123" view="table"]
```

Wyswietla wykres (Chart.js) i/lub tabele z historia cen z ostatnich 12 miesiecy.

| Parametr | Domyslnie | Opcje | Opis |
|----------|-----------|-------|------|
| `id` | ID aktualnego postu | | Post ID lokalu |
| `view` | `both` | `chart`, `table`, `both` | Tryb wyswietlania |

### 5.5. Szczegoly Lokalu

```
[dgr_unit_details id="123"]
[dgr_unit_details id="123" show_omnibus="yes" show_floor="no"]
```

Wyswietla karte szczegolow lokalu (inwestycja, numer, powierzchnia, pokoje, pietro, status, cena, cena/m²) oraz powiazane dostepne lokale.

| Parametr | Domyslnie | Opis |
|----------|-----------|------|
| `id` | ID aktualnego postu | Post ID lokalu |
| `show_investment` | `yes` | Pokaz nazwe inwestycji |
| `show_unit_id` | `yes` | Pokaz numer lokalu |
| `show_area` | `yes` | Pokaz powierzchnie |
| `show_rooms` | `yes` | Pokaz liczbe pokoi |
| `show_floor` | `yes` | Pokaz pietro |
| `show_status` | `yes` | Pokaz status |
| `show_price_total` | `yes` | Pokaz cene calkowita |
| `show_price_m2` | `yes` | Pokaz cene za m² |
| `show_omnibus` | `no` | Pokaz najnizsza cene z 30 dni (Omnibus) |

### 5.6. Lista Lokali z Filtrami

```
[dgr_unit_list]
[dgr_unit_list investment_id="45"]
```

Wyswietla tabelke lokali z filtrami AJAX (pokoje, powierzchnia, status).

| Parametr | Domyslnie | Opis |
|----------|-----------|------|
| `investment_id` | (wszystkie) | Ogranicz do jednej inwestycji |

---

## 6. Widgety Elementora

Wtyczka rejestruje 3 widgety Elementora (wymagany Elementor 3.0+):

| Widget | Opis | Odpowiednik shortcodu |
|--------|------|-----------------------|
| **DGR Historia Cen** | Wykres + tabela historii cen | `[dgr_price_history]` |
| **DGR Szczegoly Lokalu** | Karta szczegolow z powiazanymi lokalami | `[dgr_unit_details]` |
| **DGR Lista Lokali** | Lista z filtrami AJAX | `[dgr_unit_list]` |

Widgety sa dostepne w panelu Elementora w kategorii "DGR Deweloper".

---

## 7. Panel Administracyjny

### 7.1. Dashboard Widget
- Liczba lokali: ogolna, dostepne, zarezerwowane, sprzedane
- Calkowita wartosc dostepnych lokali
- Data ostatniego raportu

### 7.2. Kolumny i filtrowanie
- Kolumny: Inwestycja, Nr lokalu, Status (badge kolorowy), Powierzchnia, Cena, Cena/m²
- Sortowanie po cenie i powierzchni
- Filtrowanie po inwestycji (dropdown)
- Quick Edit: cena i status inline

### 7.3. Bulk Actions
- Zmien na "Sprzedany"
- Zmien na "Zarezerwowany"
- Zmien na "Dostepny"

### 7.4. Metabox lokalu
W edycji lokalu widoczne sa:
- Wszystkie pola danych (inwestycja, numer, lokalizacja, ceny, powierzchnia, pokoje, pietro, status, przynaleznosci)
- **Sekcje cenowe:** Stan Surowy Zamkniety (netto/brutto) i Stan Deweloperski (netto/brutto)
- **Gotowe shortcody do skopiowania** (na dole metaboxa) - indywidualne dla kazdego lokalu

### 7.5. Ustawienia
- Nazwa firmy i NIP
- Przycisk "Generuj raport teraz"
- Adresy URL wygenerowanych plikow (XML, MD5)
- Status raportu i log

---

## 8. Instrukcja Instalacji

1. Pobierz wtyczke i zainstaluj w WordPress.
2. W menu **"Deweloper Gov"** przejdz do **"Ustawienia"**.
3. Uzupelnij **Nazwe Firmy** i **NIP**.
4. Dodaj Inwestycje i Lokale (z cenami netto/brutto).
5. Kliknij **"Generuj raport teraz"** aby wygenerowac pierwszy XML.
6. Skopiuj wyswietlone **adresy URL** (XML i MD5).
7. Wyslij maila na `kontakt@dane.gov.pl` z: nazwa firmy, NIP, KRS, osoba kontaktowa, oba URL, czestotliwosc aktualizacji (codziennie).
8. Raport bedzie automatycznie aktualizowany codziennie o 01:00.

### Szybki start - wyswietlanie cen na stronie

1. Wejdz w edycje lokalu - na dole metaboxa znajdziesz **gotowe shortcody**.
2. Skopiuj wybrany shortcode.
3. Wklej go:
   - **Elementor:** w widget "Shortcode" lub "HTML"
   - **Gutenberg:** w blok "Shortcode"
   - **Klasyczny edytor:** bezposrednio w tresci

Przyklad - karta cenowa z lokalizacja, metrazem i cenami:
```
[dgr_lokal_karta id="123"]
```

Przyklad - tylko cena deweloperska brutto wstawiona w tekst:
```
Cena od [dgr_lokal_cena id="123" typ="deweloperski" vat="brutto"]
```

---

## 9. Klasy CSS do stylizacji

Shortcody generuja elementy z klasami BEM, ktore mozna nadpisac w motywie:

| Klasa | Element |
|-------|---------|
| `.dgr-lokal-karta` | Kontener karty cenowej |
| `.dgr-lokal-karta__header` | Naglowek (lokalizacja + metraz) |
| `.dgr-lokal-karta__location` | Etykieta lokalizacji |
| `.dgr-lokal-karta__area` | Wartosc metrazu |
| `.dgr-lokal-karta__prices` | Kontener cen |
| `.dgr-lokal-karta__price-block` | Blok jednej ceny (label + wartosc + netto) |
| `.dgr-lokal-karta__price-label` | Etykieta typu ceny |
| `.dgr-lokal-karta__price-value` | Wartosc ceny brutto |
| `.dgr-lokal-karta__price-netto` | Wartosc ceny netto |
| `.dgr-lokal-cena` | Inline cena (shortcode `dgr_lokal_cena`) |
| `.dgr-lokal-metraz` | Inline metraz (shortcode `dgr_lokal_metraz`) |
| `.dgr-unit-details` | Karta szczegolow lokalu |
| `.dgr-unit-list-wrapper` | Kontener listy lokali |
| `.dgr-status-badge` | Badge statusu (+ `.status-available`, `.status-sold` itp.) |
| `.dgr-price-history-wrapper` | Kontener historii cen |

---

## 10. Plan Wdrozenia (Roadmapa)

### Faza 1: Struktura i Zarzadzanie Danymi
* [x] Inicjalizacja wtyczki.
* [x] Rejestracja CPT `dgr_investment` i `dgr_unit`.
* [x] Meta boxy z walidacja numeryczna i JSON.
* [x] Mechanizm historii cen (hook on save).

### Faza 2: Generowanie XML dla dane.gov.pl
* [x] Generator XML z pelna walidacja danych.
* [x] Generowanie sumy kontrolnej MD5.
* [x] Serwowanie plikow pod stalymi URL.
* [x] Strona ustawien z panelem statusu i URL-ami.

### Faza 3: Automatyzacja
* [x] WP-Cron o stalej godzinie 01:00.
* [x] Lock zapobiegajacy duplikatom.
* [x] Przycisk "Generuj raport teraz".
* [x] Powiadomienia email o bledach.
* [x] Wykrywanie przestarzalych raportow (notice w adminie).

### Faza 4: Frontend (Prezentacja)
* [x] Shortcode `[dgr_price_history]` - wykres Chart.js + tabela.
* [x] Shortcode `[dgr_unit_details]` - karta lokalu z Omnibus.
* [x] Shortcode `[dgr_unit_list]` - lista z filtrami AJAX.
* [x] Widgety Elementora (3 widgety).
* [x] Shortcode `[dgr_lokal_karta]` - karta cenowa (lokalizacja, metraz, ceny netto/brutto).
* [x] Shortcode `[dgr_lokal_cena]` - pojedyncza cena inline (typ + vat).
* [x] Shortcode `[dgr_lokal_metraz]` - metraz inline.

### Faza 5: Bezpieczenstwo i Optymalizacja
* [x] Walidacja danych przed generowaniem XML.
* [x] Sanityzacja i escaping we wszystkich widokach.
* [x] ABSPATH guards na wszystkich plikach.
* [x] Paginacja zapytan (brak `posts_per_page => -1`).
* [x] Poprawne nonce/capability checks wszedzie.
* [ ] Testy obciazeniowe (dla duzej liczby lokali).
