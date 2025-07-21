<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Lockdown;

use Movecloser\ProcessManager\Lockdown\Enum\LockdownStatus;
use Movecloser\ProcessManager\Lockdown\Interfaces\Lock;
use Movecloser\ProcessManager\Lockdown\Models\Lockdown as LockdownModel;
use Throwable;

readonly class DBLock implements Lock
{
    private string $command;

    public function engageLockdown(string $msg, array $data = [], Throwable $e = null): void
    {
        LockdownModel::create([
            'command' => $this->command,
            'message' => $msg,
            'data' => $data,
            'exception' => $e,
            'status' => LockdownStatus::ACTIVE,
        ]);
    }

    public function exists(): bool
    {
        return !empty($this->getLock());
    }

    public function outputLockdownMessage(): void
    {
        $lock = $this->getLock();
        dump($lock->toArray());
        logger()->error(sprintf('LOCKDOWN. DB Entry found: %s', $lock->created_at), $lock->toArray());
    }

    public function resolve(): void
    {
        $this->getLock()->update([
            'status' => LockdownStatus::RESOLVED,
        ]);
    }

    public function setCommand(string $command): void
    {
        $this->command = $command;
    }

    public function skip(): void
    {
        $this->getLock()->update([
            'status' => LockdownStatus::SKIPPED,
        ]);
    }

    private function getLock(): LockdownModel|null
    {
        return LockdownModel::where([
            'command' => $this->command,
            'status' => LockdownStatus::ACTIVE,
        ])->first();
    }
}
