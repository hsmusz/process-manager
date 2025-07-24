<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Nova\Dashboards;

use Hapheus\NovaSingleValueCard\NovaSingleValueCard;
use Laravel\Nova\Dashboards\Main as Dashboard;
use Movecloser\ProcessManager\Console\Commands\ProcessManager;
use Movecloser\ProcessManager\Lockdown\CommandLock;
use Movecloser\ProcessManager\Lockdown\CommandsStatus;

class Main extends Dashboard
{
    public function cards(): array
    {
        return [
            new NovaSingleValueCard('All commands', CommandLock::allCommandsDisabled() ? 'DISABLED' : 'Enabled'),
            new NovaSingleValueCard('Process Manager', CommandsStatus::checkCommandStatus(ProcessManager::class)),
        ];
    }
}
