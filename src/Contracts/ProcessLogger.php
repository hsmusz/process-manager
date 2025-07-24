<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Contracts;

interface ProcessLogger
{
    public function dump(): array;

    public function log(string $action, array $payload): void;
}
