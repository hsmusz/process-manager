<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Lockdown;

use Movecloser\ProcessManager\Lockdown\Interfaces\Lock;
use Throwable;

class NoLock implements Lock
{
    protected static array $lock = [];

    public function engageLockdown(string $msg, array $data = [], Throwable $e = null): void
    {
        self::$lock = [
            'msg' => $msg,
            'data' => $data,
            'exception' => $e,
        ];
    }

    public function exists(): bool
    {
        return !empty($this->getLock());
    }

    public function outputLockdownMessage(): void
    {
        dump(self::$lock);
        logger()->error('LOCKDOWN', self::$lock);
    }

    public function resolve(): void
    {
        self::$lock = [];
    }

    public function setCommand(string $command): void
    {
        // do nothing
    }

    public function skip(): void
    {
        self::$lock = [];
    }

    private function getLock(): ?array
    {
        return self::$lock;
    }
}
