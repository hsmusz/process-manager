<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Processes;

use Movecloser\ProcessManager\Exceptions\ProcessException;
use Movecloser\ProcessManager\Processable;
use Movecloser\ProcessManager\ProcessResult;

abstract class AbstractProcess
{
    protected const array STEPS = [];
    public const string START = '__START__';
    public const string FINISH = '__FINISH__';
    public static int $version = 0;
    private array $steps;

    public function __construct(
        protected Processable $processable,
        protected int $bootVersion,
    ) {
    }

    public function getSteps(): array
    {
        if (!isset($this->steps)) {
            $this->steps = [self::START => 'handleStart'] + static::STEPS + [self::FINISH => 'handleFinish'];
        }

        return $this->steps;
    }

    public function handleFinish(): ProcessResult
    {
        return new ProcessResult('Process finished');
    }

    public function handleStart(): ProcessResult
    {
        return new ProcessResult('Start process');
    }

    public function isAfter(string $step, string $after): bool
    {
        return $this->getIndex($step) > $this->getIndex($after);
    }

    public function next(string $step): ?string
    {
        $index = $this->getIndex($step);
        $keys = array_keys($this->getSteps());

        return $this->getSteps()[$keys[$index + 1]] ?? null;
    }

    public function processable(): Processable
    {
        return $this->processable;
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

    private function getIndex(string $val): ?int
    {
        return array_search($val, array_keys($this->steps));
    }
}
