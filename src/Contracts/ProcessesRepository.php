<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Contracts;

use Movecloser\ProcessManager\Models\Process;
use Movecloser\ProcessManager\Processable;

interface ProcessesRepository
{
    public const string CHANNEL_DEFAULT = 'default';

    public static function createProcess(string $process, Processable $processable): Process;

    public static function hasProcessFor(string $process, Processable $processable): bool;

    public function find(int $id): Process;

    public function hasTimeoutProcess(string $channel): bool;

    public function isFatalErrorInProcesses(string $channel, int $id): bool;

    public function isRunning(string $channel): bool;

    public function nextAvailableProcess(string $channel, bool $restart = false): ?Process;

    public function restartTimeoutProcess(string $channel): void;

}
