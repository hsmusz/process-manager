<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Console\Commands;

use Illuminate\Console\Command;
use Movecloser\ProcessManager\Lockdown\CommandLock;
use Movecloser\ProcessManager\Lockdown\CommandStatusResolver;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class CommandsStatus extends Command
{
    protected $description = 'Get Lockable commands status';
    protected $signature = 'lock:commands:status';

    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        $this->info(sprintf('1. All commands: %s', CommandLock::allCommandsDisabled() ? 'DISABLED' : 'Enabled'));
        $this->info(sprintf('2. Process Manager: %s', CommandStatusResolver::checkCommandStatus(ProcessManager::lockKey())));
        $style = new OutputFormatterStyle('yellow');
        $this->output->getFormatter()->setStyle('warning', $style);

        $commands = CommandStatusResolver::commands();
        if (empty($commands)) {
            return;
        }

        $this->info('3. Registered commands:');

        foreach ($commands as $command => $title) {
            if (is_array($title)) {
                [$title, $params] = $title;
            } else {
                $params = [null];
            }

            foreach ($params as $param) {
                $this->info(sprintf("     %s\t %s", str_pad($title, 40), CommandStatusResolver::checkCommandStatus($command::lockKey($param))));
            }
        }
    }
}
