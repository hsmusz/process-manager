<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Closure;
use Movecloser\ProcessManager\Contracts\Process;
use Movecloser\ProcessManager\Processes\AbstractProcess;
use Movecloser\ProcessManager\ProcessResult;

class FakeProcess extends AbstractProcess implements Process
{
    public const string STEP = 'STEP';
    protected const array STEPS = [
        self::STEP => 'handleStep',
    ];

    public static int $version = 1;
    public static int $finished = 0;
    public static ?Closure $onStep = null;

    public function handleFinish(): ProcessResult
    {
        static::$finished++;

        return parent::handleFinish();
    }

    public function handleStep(): ProcessResult
    {
        if (static::$onStep) {
            (static::$onStep)();
        }

        return new ProcessResult('Step done');
    }
}
