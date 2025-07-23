<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Processes;

use Movecloser\ProcessManager\Contracts\Process;
use Movecloser\ProcessManager\ProcessResult;
use Throwable;

class DefaultProcess extends AbstractProcess implements Process
{
    protected const array STEPS = [
        'task' => 'handleTask',
    ];

    public static int $version = 1;

    public function boot(): void
    {
        // do nothing
    }

    public function handleException(Throwable $e): void
    {
        // do nothing
    }

    public function handleFinish(): ProcessResult
    {
        return new ProcessResult('Process finished');
    }

    public function handleTask(): ProcessResult
    {
        return new ProcessResult('Task completed');
    }
}
