<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Lockdown;

use Exception;
use Movecloser\ProcessManager\Lockdown\Interfaces\Lock;

class LockFactory
{
    /**
     * @throws \Exception
     */
    public static function make(): Lock
    {
        return match (config('integrator.lockdown_method')) {
            'database' => new DBLock(),
            'file' => new FileLock(),
            'no-lock' => new NoLock(),
            default => throw new Exception('Unsupported lockdown method')
        };
    }
}
