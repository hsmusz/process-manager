<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager;

use InvalidArgumentException;
use Movecloser\ProcessManager\Contracts\ProcessesRepository;
use Movecloser\ProcessManager\Contracts\ProcessManager;
use Movecloser\ProcessManager\Models\Process;
use ReflectionClass;

class ProcessManagerFactory
{
    protected static array $processes = [];

    public static function make(Process $process): ProcessManager
    {
        if (!array_key_exists($process->process, static::$processes)) {
            throw new InvalidArgumentException(sprintf('The process "%s" is not registered.', $process->process));
        }

        return new \Movecloser\ProcessManager\ProcessManager(
            $process,
            app()->make(ProcessesRepository::class)
        );
    }

    public static function map(string $process): string
    {
        return static::$processes[$process] ?? $process;
    }

    /**
     * @param array<class-string<\Movecloser\ProcessManager\Contracts\Process>, string> $processes
     *
     * @throws \ReflectionException
     */
    public static function registerProcesses(array $processes): void
    {
        foreach ($processes as $process => $label) {
            if (!is_string($process) || !is_string($label)) {
                throw new InvalidArgumentException('Array must contain Process => Label map');
            }

            $reflection = new ReflectionClass($process);
            if (!$reflection->implementsInterface(Contracts\Process::class)) {
                throw new InvalidArgumentException(sprintf('The process "%s" must implement Contracts\Process interface.', $process));
            }

            if (array_key_exists($process, static::$processes)) {
                throw new InvalidArgumentException(sprintf('The process "%s" is already registered.', $process));
            }
        }

        static::$processes = array_merge(static::$processes, $processes);
    }
}
