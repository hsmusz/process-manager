<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Contracts;

use Movecloser\ProcessManager\ProcessResult;
use Throwable;

interface Process
{
    public function beforeNextStep(): void;

    public function boot(): void;

    public function handleException(Throwable $e): void;

    public function handleFinish(): ProcessResult;

    public function handleStart(): ProcessResult;

    public function validate(): void;
}
