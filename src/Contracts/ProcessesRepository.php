<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Contracts;

use Movecloser\ProcessManager\Models\Process;
use Movecloser\ProcessManager\Processable;

interface ProcessesRepository
{
    public static function createProcess(string $process, Processable $processable): Process;

    public static function hasProcessFor(string $process, Processable $processable): bool;
}
