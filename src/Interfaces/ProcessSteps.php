<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Interfaces;

interface ProcessSteps
{
    public function finish();

    public function getValues();

    public function isAfter(?string $nextStep, mixed $step);

    public function next(mixed $step);

    public function start();

    public function validate();
}
