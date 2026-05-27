# Repository Guidelines

## Quick start

```bash
composer install
vendor/bin/phpunit
```

## Test

`vendor/bin/phpunit` — tests in `tests/Unit/`, PHPUnit 11.5 + Orchestra Testbench 10.

No lint/typecheck/CI config present in the repo.

## Package architecture

Laravel 12 package (`movecloser/process-manager`) — autoloaded from `src/`, PSR-4 `Movecloser\ProcessManager\`.

| Path | Role |
|------|------|
| `src/ProcessManagerServiceProvider.php` | Registers commands, Nova resources, publishes config/migrations |
| `src/Console/Commands/ProcessManager.php` | Main worker CLI: `process-manager:work` |
| `src/Console/Commands/AbstractLockableCommand.php` | Base class for lockable artisan commands (uses `LockHelper` trait) |
| `src/Console/Commands/CommandsStatus.php` | `lock:commands:status` |
| `src/Console/Commands/RestartProcess.php` | `process-manager:restart-process` |
| `src/Lockdown/CommandLock.php` | File-based lock mechanism (`storage/app/locks/*.lock`) |
| `src/Lockdown/GlobalLock.php` | Global/per-command `.disabled` check |
| `src/Lockdown/CommandStatusResolver.php` | Resolves Idle/Working/DISABLED/LOCKED/ERROR per lock key |
| `src/Support/LockHelper.php` | Trait: `bootLock()`, `commandDisabled()`, `removeCommandLock()` |
| `src/ProcessManager.php` | Execution engine: retry, timeout, step loop |
| `src/ProcessManagerFactory.php` | Static registry for process classes + Nova permission resolvers |
| `src/Models/Process.php` | Eloquent model, polymorphic `processable_type/id` |
| `src/Processes/AbstractProcess.php` | Base process class — define `STEPS` array + `$version` |

## Lock mechanism

Files in `storage/app/locks/` on disk `locks` (must be configured in `config/filesystems.php`):
- `all-commands.disabled` — global kill switch
- `{key}.disabled` — per-command disable
- `{key}.lock` — soft lock (default TTL: 30s via `softlock_time` config)
- `{key}.lock.notified` — notification sentinel
- `{key}.error` — error log (keeps last 10, prunes >72h)
- `{key}.lock.execution` — last execution timestamp

BC flag aliases: `--skip-lock` → `--skip-global-lock`, `--remove-lock` → `--remove-command-lock`.

## CLI commands

```bash
php artisan process-manager:work [processId] {--channel=} {--single} {--restart} {--force} {--skip-global-lock} {--skip-command-lock} {--remove-command-lock}
php artisan process-manager:restart-process {id}
php artisan lock:commands:status
```

## Nova integration

Nova resources (`Process`, `ProcessStep`), actions (`ReProcess`, `AbortProcess`), and dashboard with command status cards. Nova auth stored in `auth.json` (do not commit — already in `.gitignore`).

## Dev environment

Docker-based (`docker-compose.yml`, PHP 8.3 image). Helper script `./develop`:
- `./develop test` — runs phpunit with testdox
- `./develop artisan` — runs artisan inside container
- `./develop composer` — runs composer inside container

## Conventions

- `declare(strict_types=1)` everywhere
- All lockable CLI commands extend `AbstractLockableCommand` or use `LockHelper` trait
- Process classes must implement `Contracts\Process`, register via `ProcessManagerFactory::registerProcesses()`
- Bump `$version` on process class when logic changes (state recovery validation)

## Auth

`auth.json` contains Nova + GitHub tokens. Excluded via `.gitignore`. To install deps outside Docker, `auth.json` must exist.
