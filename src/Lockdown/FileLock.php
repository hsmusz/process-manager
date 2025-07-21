<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Lockdown;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Movecloser\ProcessManager\Lockdown\Interfaces\Lock;
use Throwable;

class FileLock implements Lock
{
    private const string LOCK_FILE = 'error.lock';
    private readonly string $command;

    public function engageLockdown(string $msg, array $data = [], Throwable $e = null): void
    {
        $msg = "COMMAND: $this->command \n\n" .
            "MESSAGE: $msg \n\n" .
            'LOCK TIME: ' . Carbon::now()->format('Y-m-d H:i:s') .
            'DATA: ' . json_encode($data) . " \n\n" .
            'EXCEPTION: ' . $e?->getMessage() . " \n\n" .
            'LINE: ' . $e?->getFile() . ':' . $e?->getLine() . "\n\n" .
            json_encode($e?->getTrace() ?? [], JSON_PRETTY_PRINT);

        Storage::put($this->getLockFilename(), $msg);
    }

    public function exists(): bool
    {
        return Storage::exists($this->getLockFilename());
    }

    public function outputLockdownMessage(): void
    {
        dump($this->getLock());
        logger()->error(
            sprintf('LOCKDOWN. File found: storage/app/%s | %s', $this->getLockFilename(), $this->getLock())
        );
    }

    public function resolve(): void
    {
        Storage::delete($this->getLockFilename());
    }

    public function setCommand(string $command): void
    {
        $this->command = $command;
    }

    public function skip(): void
    {
        Storage::delete($this->getLockFilename());
    }

    private function getLock(): ?string
    {
        return Storage::get($this->getLockFilename());
    }

    private function getLockFilename(): string
    {
        return self::LOCK_FILE . '.' . $this->command;
    }
}
