<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Processes;

use Movecloser\ProcessManager\Exceptions\ProcessException;
use Movecloser\ProcessManager\Processable;
use Movecloser\ProcessManager\ProcessResult;
use Throwable;

trait HasProcessSteps
{
    protected const array STEPS = [];
    private array $steps;
    private array $stepsKeys;

    public function getSteps(): array
    {
        if (!isset($this->steps)) {
            $this->steps = [self::START => 'handleStart'] + static::STEPS + [self::FINISH => 'handleFinish'];
        }

        return $this->steps;
    }

    public function isAfter(string $step, string $after): bool
    {
        return $this->getIndex($step) > $this->getIndex($after);
    }

    public function next(string $step): ?string
    {
        $index = $this->getIndex($step);

        return $this->stepsKeys()[$index + 1] ?? null;
    }

    private function getIndex(string $val): ?int
    {
        return array_search($val, $this->stepsKeys());
    }

    private function stepsKeys(): array
    {
        if (!isset($this->stepsKeys)) {
            $this->stepsKeys = array_keys($this->getSteps());
        }

        return $this->stepsKeys;
    }
}
