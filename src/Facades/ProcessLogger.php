<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Facades;

use Illuminate\Support\Facades\Facade;

class ProcessLogger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Movecloser\ProcessManager\Interfaces\ProcessLogger::class;
    }
}
