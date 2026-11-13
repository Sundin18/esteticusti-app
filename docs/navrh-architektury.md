# EsteticUsti – návrh architektury a modulu EET2

Stav: schválený návrh, zatím neimplementováno. Datum: 12. 11. 2026.

Výchozí stav repozitáře: Laravel 13, Livewire 4, Flux + Flux Pro, Fortify (2FA, passkeys), MySQL, fronta `database`, locale `cs`. Žádná vlastní doménová logika.

## 1. Modulární monolit

Aplikace bude mít moduly (EET, později Uživatelé, Plány…). Bez externího balíčku; každý modul je složka s vlastním service providerem.

```
app/Modules/
  Eet/
    EetServiceProvider.php   # registrace rout, migrací, views, configu, navigace
    Actions/  Models/  Jobs/  Services/  Livewire/
    database/migrations/
    resources/views/
    routes/web.php
    config/eet.php
    tests/
app/Core/                    # sdílené: registr navigace, oprávnění
```

Pravidla:

- Modul se zapíná jedním řádkem v `bootstrap/providers.php`; mimo svou složku nic neupravuje.
- Sidebar (`resources/views/layouts/app/sidebar.blade.php`) se vykresluje z registru navigace v `app/Core`, do kterého si moduly registrují položky.
- Moduly spolu mluví přes akce a události (např. `Eet\Actions\EvidujTrzbu`, událost `TrzbaPotvrzena`).
- Prefixy: tabulky `eet_*`, routy `/eet/*`, názvy rout `eet.*`.
- Oprávnění: gate `eet.view` (každý přihlášený) a `eet.manage` (správce). Uživatel může mít více rolí současně (tabulky `roles` a `role_user`, enum `App\Core\Authorization\UserRole`: správce, recepce, terapeut); správcem je role `spravce`.

### Databáze

Jedna společná databáze pro všechny moduly.

- Tabulky mají prefix modulu; `users` (se sloupci `firstname` a `lastname`), `roles` a `role_user` jsou sdílené.
- Cizí klíče napříč moduly jsou povolené (`eet_trzby.user_id` → `users.id`).
- Modul zapisuje jen do vlastních tabulek; cizí data mění jen přes akce vlastníka.

### UI

Vše na komponentách Flux Pro, žádné vlastní CSS mimo Tailwind: `flux:sidebar`, `flux:table` (řazení, stránkování), `flux:modal`, `flux:date-picker`, `flux:chart`, `flux:toast`.

## 2. Modul EET2

### Zadání

- Tržby se zadávají formulářem v aplikaci.
- Jedna provozovna, jedna pokladna (aplikace), jedna číselná řada.
- Tržbu může zadat kterýkoli uživatel z `users`; ukládá se, kdo ji zadal.
- Účtenka v PDF na vyžádání (pro tisk), generuje se při kliknutí, neukládá se.

### Rozhraní EET 2.0 (popis rozhraní v1.2)

- HTTPS (TLS 1.2+), HTTP/1.1 POST, SOAP 1.1, WS-Security 1.1. Bez klientského TLS certifikátu; serverový se ověřuje.
- Cesta služby: `/eet/services/EETServiceSOAP/v4`. Adresy prostředí jsou v dokumentu „Přístupové a provozní informace“.
- `Content-Type: text/xml; charset=utf-8`, jen ASCII znaky 9, 10, 13, 32–126, max. 12 kB.
- Zpráva `<Trzba>` má `Hlavicka` a `Data`:

| Atribut | Povinný | Formát |
|---|---|---|
| `Hlavicka@uuid_zpravy` | ano | UUID v4, nové pro každé odeslání |
| `Hlavicka@dat_odesl` | ano | ISO 8601 s časovou zónou |
| `Hlavicka@prvni_zaslani` | ano | `true` / `false` |
| `Hlavicka@overeni` | ne | `true` = ověřovací režim |
| `Data@eic_popl` | ano | `^CZ[0-9]{8,10}$` |
| `Data@id_jednotky` | ano | `^[1-9][0-9]{0,8}$` |
| `Data@id_pokl` | ano | `^[0-9a-zA-Z\.,:;/#\-_ ]{1,20}$` |
| `Data@porad_cis` | ano | stejná sada znaků, 1–25 |
| `Data@dat_trzby` | ano | ISO 8601 s časovou zónou |
| `Data@celk_trzba` | ano | 2 desetinná místa, např. `1500.00` |

- Prázdné nepovinné atributy se vynechávají (prázdná hodnota je chyba).
- Podpis: jen `<soap:Body>`, Exclusive C14N, digest SHA-256, RSA-SHA256, certifikát X.509 v `BinarySecurityToken`.
- Úspěch: podepsaná odpověď s `<Potvrzeni pok="…">`, POK má 39 znaků; v testovacím prostředí končí `-ff` a má `test="true"`.
- Chyba: nepodepsaná odpověď `<Chyba kod="…">`. Kódy: `-1` dočasná chyba (odeslat později), `2` kódování, `3` XSD, `4` podpis, `6` EIČ, `7` velikost, `8` zpracování. Kód `0` = úspěch v ověřovacím režimu (bez POK).
- Varování (tržba přijata): `1` EIČ ≠ certifikát, `4` datum v budoucnosti (> 2 h), `5` datum daleko v minulosti, `6` formát `id_jednotky`.
- Opakování téže tržby: nové `uuid_zpravy`, `prvni_zaslani="false"`, ostatní údaje shodné.
- Zákon č. 180/2026 Sb.: mezní doba odezvy min. 2 s, při výpadku lhůta 48 h, certifikát z daňové informační schránky.
- Produkce přijímá tržby s datem od 1. 1. 2027 (zkušebně od 1. 11. 2026); testovací prostředí od 1. 7. 2026.

Zdroje: 
- https://eet.gov.cz/assets/cs/cmsmedia/pro-vyvojare/EET_popis_rozhrani_v1.2.pdf
- https://eet.gov.cz/assets/cs/cmsmedia/pro-vyvojare/EET2_Prezentace_Seminar_pro_vyvojare_23-.pdf
- XSD a WSDL: `EETXMLSchema.xsd`, `EETServiceSOAP.wsdl` na eet.gov.cz (sekce pro vývojáře)

### Datový model

| Tabulka | Obsah |
|---|---|
| `eet_nastaveni` | EIČ, název a adresa provozovny, `id_jednotky`, `id_pokl`, prostředí (test / produkce), mezní doba odezvy, čítač pořadových čísel |
| `eet_certifikaty` | šifrovaný privátní klíč a certifikát (cast `encrypted`), platnost od–do, aktivní |
| `eet_trzby` | `porad_cis`, `dat_trzby`, `celk_trzba`, stav, `pok`, `testovaci`, `user_id`, volitelně polymorfní zdroj, `storno_trzby_id` |
| `eet_zpravy` | každý pokus: `trzba_id`, `uuid_zpravy`, `dat_odesl`, `prvni_zaslani`, XML požadavku a odpovědi, kód chyby, varování |

Unikátní index na `eet_trzby.porad_cis`. Částky jako `decimal(10,2)`.

### Stavy tržby

`ceka` → `potvrzena` (má POK) / `k_opakovani` (timeout, kód -1) / `zamitnuta` (kódy 2–8, nutný zásah)

### Průběh odeslání

1. `EvidujTrzbu` v DB transakci se zámkem (`lockForUpdate` na `eet_nastaveni`) přidělí pořadové číslo a uloží tržbu.
2. Synchronní pokus s nastaveným timeoutem; uživatel vidí POK.
3. Timeout nebo kód -1 → job `OdesliTrzbu` ve frontě s rostoucím odstupem (1, 5, 15, 30 min, pak po hodině) po dobu 48 h. Jeden pokus o odeslání dělá akce `PosliTrzbu`, kterou používá i „Odeslat znovu“.
4. Plánovaná úloha upozorní na tržby bez POK starší 24 h a na certifikát před vypršením.

### Vrstvy

- `EetClient` (rozhraní): `SoapEetClient`, `FakeEetClient` pro testy.
- Sestavení a podpis zprávy (WS-Security) i ověření podpisu odpovědi dělá knihovna `jakubsilny/eet2` (připnutá verze 1.0.0), schovaná za `SoapEetClient`. Používají se její `buildSignedMessage()`, `parseResponse()` a `verifyResponseSignature()`, ne `send()`.
- PHP `SoapClient` se nepoužívá (neumí WS-Security); HTTP přes Laravel `Http`, takže zůstává XML požadavku i odpovědi a testy jdou přes `Http::fake`.
- O opakování rozhoduje `EetChyba::jeDocasna()` (kód `-1` a neověřitelný podpis odpovědi), ne `isRetryable()` knihovny; kód `8` je trvalá chyba.
- Adresy prostředí jsou v `config/eet.php` modulu (výchozí z knihovny, přepsatelné přes `EET_URL_TEST` a `EET_URL_PRODUKCE`).
- Účtenka: `barryvdh/laravel-dompdf`; obsah: provozovna, EIČ, pořadové číslo, datum a čas, částka, POK.

### Obrazovky (Livewire + Flux Pro)

- **Přehled** `/eet`: dnešní tržby, nepotvrzené, stav spojení, platnost certifikátu.
- **Nová tržba** `/eet/trzby/nova`: částka, potvrzení s POK, tlačítko Účtenka.
- **Tržby** `/eet/trzby`: seznam s filtry, detail s historií pokusů, „Odeslat znovu“, Účtenka, Storno.
- **Nastavení** `/eet/nastaveni` (jen správce): údaje poplatníka, certifikát, test spojení (`overeni="true"`).

### Otevřené body

- Storno: specifikace ho nepopisuje; formát částky dovoluje záporné hodnoty, počítá se se stornem jako zápornou tržbou. Ověřit v metodice Finanční správy.
- Náležitosti účtenky, pokud se vydává: ověřit v zákoně.
- Automatická obnova certifikátu přes API CA EET: až později, zpočátku ruční nahrání.

## 3. Fáze

1. Kostra: `app/Core` (registr navigace), prázdný modul EET s providerem, položka v sidebaru, role uživatelů.
2. Nastavení, nahrání certifikátu, test spojení proti testovacímu prostředí.
3. Evidence tržby, fronta, opakování, seznam a detail, účtenka PDF.
4. Hlídání lhůt a certifikátu, storno, export.

Každá fáze s testy (PHPUnit, `FakeEetClient`, validace XML proti XSD) a průchodem `pint` a `phpstan`.
