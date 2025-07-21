<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager;

use Movecloser\ProcessManager\Interfaces\ProcessManager;
use Movecloser\ProcessManager\Models\Process;
use Movecloser\ProcessManager\Enum\ProcessStatus;
use Exception;

class ProcessManagerFactory
{
    public const int MAX_RETRIES = 50;
    public const int RETRY_AFTER = 60; // in seconds

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Exception
     */
    public static function make(Process $process): ProcessManager
    {
        // @todo: move to Provider
        return match ($process->type) {
            default => throw new Exception('Unknown process type.')
        };

    }
}
