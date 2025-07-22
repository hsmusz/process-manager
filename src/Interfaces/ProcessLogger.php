<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Interfaces;

interface ProcessLogger
{
    public function dump(): array;

    public function log(string $action, array $payload): void;

    public function start();
}
