<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager;

use InvalidArgumentException;
use Movecloser\ProcessManager\Contracts\ProcessesRepository;
use Movecloser\ProcessManager\Contracts\ProcessManager;
use Movecloser\ProcessManager\Models\Process;

class ProcessManagerFactory
{
    protected static array $processes = [];

    public static function make(Process $process): ProcessManager
    {
        if (!in_array($process->type, static::$processes)) {
            throw new InvalidArgumentException(sprintf('The manager type "%s" does not exist.', $process->type));
        }

        return new \Movecloser\ProcessManager\ProcessManager(
            $process,
            app()->make(ProcessesRepository::class)
        );
    }

    public static function registerManagers(array $managers): void
    {
        // @todo: validate for unique processes names

        static::$processes = array_merge(static::$processes, $managers);
    }
}
