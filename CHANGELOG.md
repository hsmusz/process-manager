# Changelog

Wszystkie istotne zmiany w projekcie. Najnowsze na górze.

Format: [Keep a Changelog](https://keepachangelog.com/pl/1.1.0/), wersjonowanie:
[SemVer](https://semver.org/lang/pl/). Typy wpisów: **hotfix** (patch z gałęzi master),
**poprawka** (patch z review), **wydanie** (minor/major - zbiera wszystkie patche od
poprzedniego wydania), **kandydat** (`X.Y.Z-rc.N`, wersja testowa; po wydaniu produkcyjnym
jej wpis zostaje włączony do wpisu wersji).

Każdy wpis ma sekcję **Wdrożenie** z krokami ręcznymi albo informacją, że ich nie ma.

## [1.5.1] — 2026-11-19 · hotfix

Zakres: `1.5.0..1.5.1` · 1 commit

### Zmienione
- Mail o procesie, który wciąż się ponawia (po `notify_on_retry_attempts` nieudanych próbach),
  trafia do `app.notify_on_error` zamiast do `notify_on_lockdown`. Mail o ostatecznym `ERROR`
  nadal idzie do `notify_on_lockdown`.

### Wdrożenie
- Bez kroków ręcznych. Odbiorców wyznacza `notify_on_error` w `config/app.php` aplikacji;
  pusta lista wyłącza mail.
