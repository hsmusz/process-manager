<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Contracts;

interface ProcessManager
{
    public function handle(): void;
}
