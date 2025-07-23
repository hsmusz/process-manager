<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Contracts;

use Movecloser\ProcessManager\Processable;
use Movecloser\ProcessManager\ProcessResult;
use Throwable;

interface Process
{
    public function boot(): void;

    public function getSteps(): array;

    public function handleException(Throwable $e): void;

    public function handleFinish(): ProcessResult;

    public function handleStart(): ProcessResult;

    public function isAfter(string $before, string $after): bool;

    public function next(string $val): ?string;

    public function processable(): Processable;

    public function validate(): void;
}
