<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Support;

use Movecloser\ProcessManager\Lockdown\CommandLock;
use Movecloser\ProcessManager\Lockdown\GlobalLock;
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

    protected function bootLock(): void
    {
        if (empty($this->getLockKey())) {
            return;
        }

        if ($this->option('remove-command-lock') || $this->option('remove-lock')) {
            CommandLock::removeLock($this->getLockKey());
        }

        if ($this->option('skip-command-lock')) {
            return;
        }

        CommandLock::delayAndLock($this->getLockKey());
        CommandLock::removeError($this->getLockKey());
    }

    protected function commandDisabled(): bool
    {
        if (empty($this->getLockKey())) {
            return false;
        }

        if ($this->hasOption('skip-lock') && $this->option('skip-lock')) {
            return false;
        }

        if ($this->hasOption('skip-global-lock') && $this->option('skip-global-lock')) {
            return false;
        }

        return GlobalLock::isDisabled($this->getLockKey());
    }

    protected function getLockKey(): ?string
    {
        if (isset($this->lockKey)) {
            return $this->lockKey;
        }

        $param = null;
        if (method_exists($this, 'lockKeyArgument') && !empty($this->lockKeyArgument())) {
            $param = $this->lockKeyArgument();
        }

        $this->lockKey = static::lockKey($param);

        return $this->lockKey;
    }

    protected function removeCommandLock(): void
    {
        if (empty($this->getLockKey())) {
            return;
        }

        CommandLock::removeLock($this->getLockKey());
    }
}
