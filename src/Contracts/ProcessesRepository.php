<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Contracts;

use Movecloser\ProcessManager\Models\Process;
use Movecloser\ProcessManager\Processable;

interface ProcessesRepository
{
    public static function createProcess(string $process, Processable $processable): Process;

    public static function hasProcessFor(string $process, Processable $processable): bool;

    public function hasTimeoutProcess(): bool;

    public function isFatalErrorInProcesses(int $id): bool;

    public function isRunning(): bool;

    public function nextAvailableProcess(bool $forceRetry = false, bool $allowFix = false): ?Process;

    public function find(int $id): Process;

    public function restartTimoutProcess(): void;

}
