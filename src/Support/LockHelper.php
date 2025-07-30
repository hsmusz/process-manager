<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Support;

use Movecloser\ProcessManager\Lockdown\CommandLock;

trait LockHelper
{
    public static function lockKey(?string $param = null): ?string
    {
        try {
            $key = static::COMMAND_LOCK_KEY;
        } catch (\Throwable $e) {
            return null;
        }

        return $key . (!empty($param) ? ('-' . $param) : '');
    }

    /**
     * @throws \Exception
     */
    protected function bootLock(): void
    {
        $param = null;
        if (method_exists($this, 'lockKeyArgument') && !empty($this->lockKeyArgument())) {
            $param = $this->argument($this->lockKeyArgument());
        }

        if ($this->shouldForceRemoveLock()) {
            CommandLock::removeLock(static::lockKey($param));
        }

        if ($this->shouldUseLock()) {
            CommandLock::delayAndLock(static::lockKey($param));
        }
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
