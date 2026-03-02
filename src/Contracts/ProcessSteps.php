<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Contracts;

interface ProcessSteps
{
    public function getSteps(): array;

    public function isAfter(string $step, string $after): bool;

    public function next(string $step): ?string;
}
