<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Lockdown;

use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class CommandsStatus extends Command
{
    public const string COMMAND_STATUS_IDLE = 'Idle';
    public const string COMMAND_STATUS_WORKING = 'Working';
    public const string COMMAND_STATUS_DISABLED = 'DISABLED';
    public const string COMMAND_STATUS_LOCKED = 'LOCKED';
    public const array COMMANDS = [
    ];

    protected $description = 'Get Lockable commands status';
    protected $signature = 'lock:commands:status';

    public static function checkCommandStatus(string $class): string
    {
        if (CommandLock::isLocked($class::COMMAND_LOCK_KEY)) {
            if (CommandLock::isOutdatedLock($class::COMMAND_LOCK_KEY) && CommandLock::notified($class::COMMAND_LOCK_KEY)) {
                return self::COMMAND_STATUS_LOCKED;
            } else {
                return self::COMMAND_STATUS_WORKING;
            }
        }

        if (CommandLock::commandDisabled($class::COMMAND_LOCK_KEY)) {
            return self::COMMAND_STATUS_DISABLED;
        }

        return self::COMMAND_STATUS_IDLE;
    }

    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        $this->info(sprintf('1. All commands: %s', CommandLock::allCommandsDisabled() ? 'DISABLED' : 'Enabled'));
        $this->info(sprintf('2. Process Manager: %s', self::checkCommandStatus(ProcessManager::class)));
        $style = new OutputFormatterStyle('yellow');
        $this->output->getFormatter()->setStyle('warning', $style);
        $i = 3;
        foreach (self::COMMANDS as $command => $title) {
            $status = self::checkCommandStatus($command);
            $style = match ($status) {
                self::COMMAND_STATUS_LOCKED => 'error',
                self::COMMAND_STATUS_DISABLED => 'warning',
                default => 'info'
            };
            $this->line(sprintf('%d. %s: %s', ++$i, $title, $status), $style);
        }
    }
}
