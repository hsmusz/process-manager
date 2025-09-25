<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Support;

use Illuminate\Support\Str;
use Movecloser\ProcessManager\Lockdown\CommandLock;
use Throwable;

trait LockHelper
{
    private ?string $lockKey;

    /**
     * @throws \Exception
     */
    protected function bootLock(): void
    {
        if ($this->shouldForceRemoveLock()) {
            CommandLock::removeLock($this->getLockKey());
        }

        if ($this->shouldUseLock()) {
            CommandLock::delayAndLock($this->getLockKey());
        }
    }

    protected function commandDisabled(): bool
    {
        return !$this->option('bypass-lock') && CommandLock::commandDisabled($this->getLockKey());
    }

    protected function removeCommandLock(): void
    {
        CommandLock::removeLock($this->getLockKey());
    }

    protected function getLockKey(): string
    {
        if (isset($this->lockKey)) {
            return $this->lockKey;
        }

        $param = null;
        if (method_exists($this, 'lockKeyArgument') && !empty($this->lockKeyArgument())) {
            $param = $this->argument($this->lockKeyArgument());
        }

        $this->lockKey = $this->lockKey($param);

        return $this->lockKey;
    }

    private function lockKey(?string $param = null): ?string
    {
        try {
            $key = static::COMMAND_LOCK_KEY;
        } catch (Throwable $e) {
            return null;
        }

        return $key . (!empty($param) ? ('-' . $param) : '');
    }

    private function shouldForceRemoveLock(): bool
    {
        return $this->option('remove-lock');
    }

    private function shouldUseLock(): bool
    {
        $useLock = true;
        if (app()->isLocal() && $this->option('skip-lock')) {
            $useLock = false;
        }

        return $useLock;
    }
}
