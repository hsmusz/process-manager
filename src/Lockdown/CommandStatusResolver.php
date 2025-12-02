<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Lockdown;

class CommandStatusResolver
{
    public const string COMMAND_STATUS_IDLE = 'Idle';
    public const string COMMAND_STATUS_WORKING = 'Working';
    public const string COMMAND_STATUS_DISABLED = 'DISABLED';
    public const string COMMAND_STATUS_LOCKED = 'LOCKED';
    public const string COMMAND_STATUS_ERROR = 'ERROR';

    protected static array $commands = [];

    public static function checkCommandStatus(string $lockKey): string
    {
        if (CommandLock::isLocked($lockKey)) {
            if (CommandLock::isOutdatedLock($lockKey) && CommandLock::notified($lockKey)) {
                return self::COMMAND_STATUS_LOCKED;
            }

            return self::COMMAND_STATUS_WORKING;
        }

        if (CommandLock::commandDisabled($lockKey)) {
            return self::COMMAND_STATUS_DISABLED;
        }

        if (CommandLock::hasError($lockKey)) {
            return self::COMMAND_STATUS_ERROR;
        }

        return self::COMMAND_STATUS_IDLE;
    }

    public static function commands(): array
    {
        return static::$commands;
    }

    public static function registerCommands(array $commands): void
    {
        static::$commands = array_merge(static::$commands, $commands);
    }
}
