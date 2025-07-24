<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Processes;

use Movecloser\ProcessManager\Contracts\ProcessSteps;
use Movecloser\ProcessManager\Exceptions\ProcessException;
use Movecloser\ProcessManager\Processable;
use Movecloser\ProcessManager\ProcessResult;
use Throwable;

abstract class AbstractProcess implements ProcessSteps
{
    use HasProcessSteps;

    public const string START = '__START__';
    public const string FINISH = '__FINISH__';
    public static int $version = 0;

    public function __construct(
        public readonly Processable $processable,
        protected int $bootVersion,
    ) {
    }

    public function beforeNextStep(): void
    {
        // do nothing
    }

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

    public function handleStart(): ProcessResult
    {
        return new ProcessResult('Process started');
    }

    /**
     * @throws \Exception
     */
    public function validate(): void
    {
        if (!is_null($this->bootVersion) && static::$version !== $this->bootVersion) {
            throw new ProcessException(sprintf('Invalid version detected. Please upgrade to version %d', static::$version));
        }
    }
}
