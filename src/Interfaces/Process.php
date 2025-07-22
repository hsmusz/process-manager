<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Interfaces;

interface Process
{
    public function finish(): string;

    public function getSteps(): array;

    public function isAfter(string $before, string $after): bool;

    public function next(string $val): ?string;

    public function start(): string;

    public function validate(): void;
}
