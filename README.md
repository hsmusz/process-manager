# Laravel Component for Handling Processes in a Microservice Environment

## Wymagania

### Process Manager: register Processes
```php
    ProcessManagerFactory::registerProcesses([
        NewOrderProcess::class => 'CREATE ORDER',
        NewCreditMemoProcess::class => 'REFUND ORDER',
        IssueCreditMemoProcess::class => 'ISSUE CREDIT MEMO',
    ]);
```

### Command Status Resolver: register Commands
```php
    CommandStatusResolver::registerCommands([
        GetMagentoInvoices::class => 'Get Magento Invoices',
        GetMagentoProducts::class => 'Get Magento Products',
        ImportCostInvoicesToBooks::class => ['Import Cost Invoices to Books', ['service1', 'service2']],
        IssueAllegroMagentoInvoices::class => ['Issue Magento Allegro Invoices', [10, 24]],
    ]);
```


## Procedury

### Zatrzymanie działania

_(W przypadku restartu aplikacji, bazy danych lub systemów zewnętrznych 
oraz przy planowanych przerwach w działaniu systemów)_

Aby bezpiecznie zatrzymać działanie serwisu, należy wyłączyć wszystkie komendy, 
dodając plik `all-commands.disabled` w katalogu `storage/app/locks/`:

```bash
$ touch storage/app/locks/all-commands.disabled
```

Następnie należy wejść na [stronę główną serwisu](https://service.aelia.pl/nova/dashboards/main) i sprawdzić, 
czy wszystkie komendy mają status *DISABLED* lub *LOCKED* (w przypadku błędów). Jeśli którakolwiek komenda 
jest w stanie *Working*, należy odczekać, aż zakończy swoje działanie.

Alternatywnie można użyć komendy CLI:

```bash
php artisan lock:commands:status
```

---

### Klucze blokad i szacowany czas pracy komend

Blokady są używane do uniemożliwienia wykonywania niektórych komend na czas określonych operacji. Typowe klucze:

- **Process Manager**: ~5 minut (`process-manager`)

---

### Wznowienie działania systemu

Aby wznowić system, usuń plik blokady `all-commands.disabled`:

```bash
$ rm storage/app/locks/all-commands.disabled
```

Po wejściu na [stronę główną serwisu](https://service.aelia.pl/nova/dashboards/main), upewnij się, 
że wszystkie komendy są w statusie `Idle` lub `Working`.

---

### Zatrzymanie działania pojedynczych komend

_(Dla tymczasowych napraw lub odłączenia komendy nadrzędnej od zależnych procesów, 
np.: *Get Orders* => *Process Manager*)._

Aby zatrzymać konkretną komendę, utwórz plik w katalogu `storage/app/locks/` odpowiadający kluczowi tej komendy 
i kończący się `.disabled`:

```bash
$ touch storage/app/locks/process-manager.disabled
```

Po tej operacji w panelu głównym serwisu dana komenda powinna mieć status `DISABLED`.

---

### Wznowienie działania pojedynczych komend

Aby wznowić działanie wybranej komendy, usuń plik blokady z katalogu `storage/app/locks/`:

```bash
$ rm storage/app/locks/process-manager.disabled
```

---

### Blokada komendy w wyniku niespodziewanej przerwy w pracy

Podczas działania systemu komendy automatycznie nakładają tymczasową blokadę (`.lock`), aby uniemożliwić ponowne 
uruchomienie do czasu zakończenia aktualnej instancji. W przypadku sytuacji wyjątkowych (np. brak dostępności 
usługi lub błędy systemowe), blokady mogą zostać oznaczone jako przedawnione.

W sytuacjach nieoczekiwanych problemów:

- Sprawdź status komendy na stronie głównej systemu (np. `LOCKED`).
- Przeanalizuj logi, aby określić przyczynę błędu.
- Gdy problem nie zagraża działaniu aplikacji, usuń blokady ręcznie:

```bash
$ rm storage/app/locks/process-manager.lock*
```

---

## Process Manager

Process Manager automatycznie wstrzymuje działanie na 10 minut przed końcem dnia, aby uniknąć kolizji w danych 
fiskalnych i księgowych. Każda instancja działa maksymalnie 5 minut i kończy pracę, jeśli kolejna operacja 
w kolejce nie została zaplanowana.

W przypadku błędów oznaczonych jako `ERROR` lub `RETRY` Process Manager przerywa działanie i podejmuje ponowne próby 
zgodnie z logiką `retry_after`.

---

### Przegląd procesów

Wszystkie procesy definiowane są w plikach *Process.php*, gdzie opisano ich logikę, statusy i kroki.

Każda zmiana w tych plikach wymaga zwiększenia wersji procesu (`$version`), aby zapewnić poprawne odtwarzanie 
zadań po aktualizacji.

---

### Historia procesów

Każdy proces posiada historię widoczną w [systemie](https://service.aelia.pl/nova/resources/processes). 
Rejestrowane są wszystkie kroki procesu oraz payload wysyłany do zewnętrznych systemów.

---

### Wznawianie procesów

Procesy w statusach `ERROR` i `RETRY` mogą być wznowione przez:

- Akcję na ekranie procesu ([example](https://service.aelia.pl/nova/resources/processes)).
- Komendę:

```bash
php artisan process-manager:restart-process {id}
```

---

### Porzucanie procesów

Nieukończony proces można porzucić, klikając ikonę obok przycisku „retry” (ikona może być ukryta). 
Procesy porzucone są pomijane w kolejnych iteracjach.

---

### Instrukcje ponawiania uszkodzonych procesów

W przypadku problemów z procesami, administrator powinien przeanalizować błędy i zdecydować, czy wznowienie 
nie spowoduje dodatkowych problemów. Jeśli jest to bezpieczne, można użyć:

```bash
php artisan process-manager:work {id}
```

---

## Instalacja

Instrukcja dodania wymaganych konfiguracji:

1. **Filesystem**:

```php
# config/filesystems.php
'disks' => [
    ...
    'locks' => [
        'driver' => 'local',
        'root' => storage_path('app/locks'),
        'throw' => false,
    ],
],
```

2. **Logowanie**:

```php
# config/logging.php
'channels' => [
    ...
    'process-manager' => [
        'driver' => 'single',
        'path' => storage_path('logs/process-manager.log'),
        'level' => 'debug',
        'replace_placeholders' => true,
    ],
],
```

---

### Przykład klasy procesu

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