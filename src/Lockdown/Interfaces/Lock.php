<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Lockdown\Interfaces;

use Throwable;

interface Lock
{
    public function engageLockdown(string $msg, array $data = [], Throwable $e = null): void;

    public function exists(): bool;

    public function outputLockdownMessage(): void;

    public function resolve(): void;

    public function setCommand(string $command);

    public function skip(): void;
}
