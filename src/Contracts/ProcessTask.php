<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Contracts;

use Movecloser\ProcessManager\ProcessResult;

interface ProcessTask
{
    public function handle(): ProcessResult;
}
