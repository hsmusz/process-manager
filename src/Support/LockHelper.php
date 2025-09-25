<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Support;

use Movecloser\ProcessManager\Lockdown\CommandLock;
use Throwable;

trait LockHelper
{
    private ?string $lockKey;

    public static function lockKey(?string $param = null): ?string
    {
        try {
            $key = static::COMMAND_LOCK_KEY;
        } catch (Throwable $e) {
            return null;
        }

        return $key . (!empty($param) ? ('-' . $param) : '');
    }

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
        if (empty($this->getLockKey()) || $this->option('skip-lock')) {
            return false;
        }

        return CommandLock::commandDisabled($this->getLockKey());
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

        $this->lockKey = static::lockKey($param);

        return $this->lockKey;
    }

    protected function removeCommandLock(): void
    {
        if ($this->getLockKey()) {
            CommandLock::removeLock($this->getLockKey());
        }
    }

    private function shouldForceRemoveLock(): bool
    {
        return $this->option('remove-lock');
    }

    private function shouldUseLock(): bool
    {
        $useLock = true;
        if ($this->option('skip-lock')) {
            $useLock = false;
        }

        return $useLock;
    }
}
