# Komponent Laravel do obsługi procesów w środowisku mikroserwisów

## Instalacja

1. **Filesystem** – wymagany dysk `locks` dla mechanizmu blokad:

```php
# config/filesystems.php
'disks' => [
    'locks' => [
        'driver' => 'local',
        'root' => storage_path('app/locks'),
        'throw' => false,
    ],
],
```

2. **Logowanie** – wymagany kanał `process-manager`:

```php
# config/logging.php
'channels' => [
    'process-manager' => [
        'driver' => 'single',
        'path' => storage_path('logs/process-manager.log'),
        'level' => 'debug',
        'replace_placeholders' => true,
    ],
],
```

---

## Konfiguracja

Wszystkie poniższe wywołania umieszczamy w metodzie `boot()` service providera aplikacji.

### Rejestracja procesów

```php
ProcessManagerFactory::registerProcesses([
    NewOrderProcess::class => 'CREATE ORDER',
    NewCreditMemoProcess::class => 'REFUND ORDER',
    IssueCreditMemoProcess::class => 'ISSUE CREDIT MEMO',
]);
```

### Uprawnienia panelu Nova

Opcjonalne callbacki kontrolujące dostęp do zasobów w panelu Nova. Jeśli nie zostaną zdefiniowane, dostęp jest przyznawany domyślnie.

- **`setViewResolver`** – określa, czy użytkownik może przeglądać listę i szczegóły procesów.
- **`setManageResolver`** – określa, czy użytkownik może wykonywać akcje (restart, abort).

```php
ProcessManagerFactory::setViewResolver(function (\Illuminate\Http\Request $request): bool {
    return $request->user()?->hasRole('admin') ?? false;
});

ProcessManagerFactory::setManageResolver(function (\Illuminate\Http\Request $request): bool {
    return $request->user()?->hasPermission('process-manager.manage') ?? false;
});
```

### Rejestracja komend (Command Status Resolver)

```php
CommandStatusResolver::registerCommands([
    GetMagentoInvoices::class => 'Get Magento Invoices',
    GetMagentoProducts::class => 'Get Magento Products',
    ImportCostInvoicesToBooks::class => ['Import Cost Invoices to Books', ['service1', 'service2']],
    IssueAllegroMagentoInvoices::class => ['Issue Magento Allegro Invoices', [10, 24]],
]);
```

---

## Architektura

### Przepływ procesów

```
Process (PENDING) → process-manager:work CLI → ProcessManager::handle()
  → przywrócenie stanu → walidacja wersji → boot()
  → dla każdego kroku: beforeNextStep() → execute() → zapis wyniku
  → SUCCESS | RETRY | ERROR | EXCEPTION
```

Kroki definiowane są w `AbstractProcess::STEPS` jako `['etykieta' => 'nazwaMetody']` lub `['etykieta' => TaskClass::class]`. Klasy zadań muszą implementować `ProcessTask` i zwracać `ProcessResult`.

### Kluczowe klasy

| Klasa | Rola |
|---|---|
| `src/ProcessManager.php` | Silnik wykonania — logika retry, timeout, cooldown, pętla kroków |
| `src/Processes/AbstractProcess.php` | Klasa bazowa dla wszystkich procesów; definiuje STEPS, wersję, hooki |
| `src/ProcessManagerFactory.php` | Rejestr procesów i resolver uprawnień Nova |
| `src/Repositories/ProcessesRepository.php` | Zapytania DB — nextAvailableProcess, wykrywanie timeoutów |
| `src/Lockdown/CommandLock.php` | Mechanizm blokad via `storage/app/locks/*.{lock,disabled,error}` |
| `src/Models/Process.php` | Model Eloquent; polimorficzne `processable_type/id`, JSON `meta` |
| `src/Models/ProcessStep.php` | Rekord audytu kroku z kolumnami JSON `details` i `logs` |
| `src/ProcessLogger.php` | Fasada logowania (`ProcessLogger::`) — zapisuje do rekordu kroku |

### Cykl życia procesu

- **Statusy**: `PENDING` → `IN_PROGRESS` → `SUCCESS` / `ERROR` / `RETRY` / `EXCEPTION` / `SKIPPED`
- **Retry**: wykładniczy backoff (`attempts * 60s`), maksymalnie 50 prób
- **Timeout**: procesy `IN_PROGRESS` starsze niż 60 min automatycznie ustawiane na `RETRY`
- **Cooldown dzienny**: automatyczne zatrzymanie 10 min przed północą
- **Bezpieczeństwo wersji**: `$version` procesu walidowana przy przywracaniu stanu; niezgodność = wyjątek
- **Pomijanie kroku**: ustaw `skip_steps` w meta procesu, aby wskazać klucze kroków do pominięcia

### Blokady komend

Pliki blokad w `storage/app/locks/`:

| Plik | Działanie |
|---|---|
| `all-commands.disabled` | Wyłącza wszystkie komendy globalnie |
| `{klucz}.disabled` | Wyłącza konkretną komendę |
| `{klucz}.lock` | Blokada miękka (przedawniona po `softlock_time` sekund, domyślnie 30) |
| `{klucz}.error` | Śledzi błędy krytyczne |

Typowe klucze blokad: **`process-manager`** (~5 min).

### Komendy CLI

```bash
# Uruchomienie workera (opcje: --channel=, --single, --restart, --force, --remove-lock, --skip-lock)
php artisan process-manager:work [processId]

# Wznowienie konkretnego procesu
php artisan process-manager:restart-process {id}

# Wyświetlenie stanu blokad
php artisan lock:commands:status
```

### Multi-kanałowość

Procesy posiadają pole `channel`. Przekaż `--channel=nazwa` do CLI, aby izolować workery per kanał. Kanały konfigurowane w `config/process-manager.php`.

### Integracja Nova

`src/Nova/` zawiera zasoby (`Process`, `ProcessStep`), akcje (`ReProcess`, `AbortProcess`), metryki i dashboardy. Rejestrowane automatycznie przez service provider, konfigurowane w `config/nova.php`.

### Baza danych

Dwie tabele: `processes` i `process_steps`. Migracja w `database/migrations/`. Para polimorficzna `processable_type/id` łączy proces z dowolnym modelem Eloquent.

---

## Przykład klasy procesu

Każda zmiana logiki procesu wymaga zwiększenia `$version`, aby zapewnić poprawne odtwarzanie stanu po aktualizacji.

```php
class DefaultProcess extends AbstractProcess implements Process
{
    protected const array STEPS = [
        'step1' => 'handleTask',
        'step2' => \App\ProcessManager\Tasks\HandleTask2::class,
    ];

    public static int $version = 1;

    public function handleTask(): ProcessResult
    {
        return new ProcessResult('Task completed');
    }
}
```

---

## Procedury operacyjne

### Zatrzymanie działania

_(W przypadku restartu aplikacji, bazy danych lub systemów zewnętrznych oraz przy planowanych przerwach)_

```bash
$ touch storage/app/locks/all-commands.disabled
```

Następnie sprawdź, czy wszystkie komendy mają status `DISABLED` lub `LOCKED`:

```bash
php artisan lock:commands:status
```

Jeśli którakolwiek komenda jest w stanie `Working`, odczekaj, aż zakończy działanie.

---

### Wznowienie działania systemu

```bash
$ rm storage/app/locks/all-commands.disabled
```

Upewnij się, że wszystkie komendy wróciły do statusu `Idle` lub `Working`.

---

### Zatrzymanie pojedynczej komendy

_(Dla tymczasowych napraw lub odłączenia komendy nadrzędnej od zależnych procesów)_

```bash
$ touch storage/app/locks/process-manager.disabled
```

---

### Wznowienie pojedynczej komendy

```bash
$ rm storage/app/locks/process-manager.disabled
```

---

### Usunięcie przedawnionej blokady miękkiej

Gdy komenda wyświetla status `LOCKED` po nieoczekiwanej przerwie w pracy:

```bash
$ rm storage/app/locks/process-manager.lock*
```

---

### Wznawianie procesów w błędzie

Procesy w statusach `ERROR` i `RETRY` można wznowić:

- Akcją `Restart Process` na ekranie szczegółów procesu w panelu Nova.
- Komendą CLI:

```bash
php artisan process-manager:restart-process {id}
```

Przed wznowieniem przeanalizuj logi, aby upewnić się, że ponowne wykonanie nie spowoduje problemów.

---

### Porzucanie procesów

Nieukończony proces można porzucić akcją `Abort` w panelu Nova. Procesy porzucone (`ABORTED`) są pomijane w kolejnych iteracjach workera.
