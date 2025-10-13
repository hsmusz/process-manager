<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Lockdown;

use Illuminate\Console\Command;
use Movecloser\ProcessManager\Console\Commands\ProcessManager;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class CommandsStatus extends Command
{
    public const string COMMAND_STATUS_IDLE = 'Idle';
    public const string COMMAND_STATUS_WORKING = 'Working';
    public const string COMMAND_STATUS_DISABLED = 'DISABLED';
    public const string COMMAND_STATUS_LOCKED = 'LOCKED';
    public const string COMMAND_STATUS_ERROR = 'ERROR';

    protected $description = 'Get Lockable commands status';
    protected $signature = 'lock:commands:status';

    public static function checkCommandStatus(string $lockKey): string
    {
        if (CommandLock::isLocked($lockKey)) {
            if (CommandLock::isOutdatedLock($lockKey) && CommandLock::notified($lockKey)) {
                return self::COMMAND_STATUS_LOCKED;
            } else {
                return self::COMMAND_STATUS_WORKING;
            }
        }

        if (CommandLock::commandDisabled($lockKey)) {
            return self::COMMAND_STATUS_DISABLED;
        }

        if (CommandLock::hasError($lockKey)) {
            return self::COMMAND_STATUS_ERROR;
        }

        return self::COMMAND_STATUS_IDLE;
    }

    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        $this->info(sprintf('1. All commands: %s', CommandLock::allCommandsDisabled() ? 'DISABLED' : 'Enabled'));
        $this->info(sprintf('2. Process Manager: %s', self::checkCommandStatus(ProcessManager::lockKey())));
        $style = new OutputFormatterStyle('yellow');
        $this->output->getFormatter()->setStyle('warning', $style);
    }
}
