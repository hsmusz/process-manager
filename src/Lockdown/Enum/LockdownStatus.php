<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Lockdown\Enum;

enum LockdownStatus: string
{
    case ACTIVE = 'ACTIVE';
    case RESOLVED = 'RESOLVED';
    case SKIPPED = 'SKIPPED';
}
