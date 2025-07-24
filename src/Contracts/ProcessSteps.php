<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Contracts;

use Movecloser\ProcessManager\ProcessResult;
use Throwable;

interface ProcessSteps
{
    public function getSteps(): array;

    public function isAfter(string $before, string $after): bool;

    public function next(string $step): ?string;
}
