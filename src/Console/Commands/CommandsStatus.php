<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Console\Commands;

use Illuminate\Console\Command;
use Movecloser\ProcessManager\Lockdown\CommandStatusResolver;
use Movecloser\ProcessManager\Lockdown\GlobalLock;

class CommandsStatus extends Command
{
    protected $description = 'Get Lockable commands status';
    protected $signature = 'lock:commands:status';

    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        $this->displaySystemStatus();
        $this->displayRegisteredCommands();
    }

    private function displayRegisteredCommands(): void
    {
        $commands = CommandStatusResolver::commands();

        if (empty($commands)) {
            return;
        }

        $this->info('3. Registered commands:');

        foreach ($commands as $commandClass => $config) {
            $this->renderCommandStatus($commandClass, $config);
        }
    }

    private function displaySystemStatus(): void
    {
        $allDisabled = GlobalLock::allDisabled() ? 'DISABLED' : 'Enabled';
        $this->info(sprintf('1. All commands: %s', $allDisabled));

        foreach (config('process-manager.channels', ['default' => 'Process Manager']) as $channel => $name) {
            $managerStatus = CommandStatusResolver::checkCommandStatus(ProcessManager::lockKey($channel));
            $this->info(sprintf('2. %s: %s', $name, $managerStatus));
        }
    }

    private function renderCommandStatus(string $commandClass, string|array $config): void
    {
        [$title, $params] = is_array($config) ? $config : [$config, [null]];

        foreach ($params as $param) {
            if (!empty($param)) {
                $param = (string) $param;
            }

            $lockKey = $commandClass::lockKey($param);
            $status = CommandStatusResolver::checkCommandStatus($lockKey);
            $suffix = $param ? " ($param)" : '';

            $this->warn(sprintf('     %s%s: %s', $title, $suffix, $status));
        }
    }
}
