<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Lockdown;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Movecloser\ProcessManager\Lockdown\Mail\Lockdown as LockdownMail;

class CommandLock
{
    public static function allCommandsDisabled(): bool
    {
        return self::storage()->exists('all-commands.disabled');
    }

    public static function commandDisabled(string $lockKey): bool
    {
        return self::allCommandsDisabled() || self::storage()->exists($lockKey . '.disabled');
    }

    /**
     * @throws \Exception
     */
    public static function delayAndLock(string $lockKey): void
    {
        $rand = rand(100000, 1000000);
        Log::info(
            sprintf('Command [%s]: Sleeping %d microseconds (%s seconds)', $lockKey, $rand, round($rand / 1000000, 2))
        );
        usleep($rand);

        if (self::isLocked($lockKey)) {
            Log::error(sprintf('Command %s locked', $lockKey));

            if (self::isOutdatedLock($lockKey) && !self::notified($lockKey)) {
                $msg = sprintf('Found old soft-lock on command %s', $lockKey);
                self::storage()->put(self::getSoftLockNotificationFilename($lockKey), Carbon::now());

                if (!empty(config('process-manager.notify_on_soft_lock'))) {
                    Mail::to(config('process-manager.notify_on_soft_lock'))->send(
                        new LockdownMail($msg, [], config('app.name') . ' Microservice - soft lock detected')
                    );
                }
            }

            dd(sprintf('Command %s locked', $lockKey)); // exit without exception :)
        }

        self::lock($lockKey);
    }

    public static function isLocked(string $lockKey): bool
    {
        return self::storage()->exists(self::getSoftLockFilename($lockKey));
    }

    public static function hasError(string $lockKey): bool
    {
        return self::storage()->exists(self::getErrorLockFilename($lockKey));
    }

    public static function removeError(string $lockKey): void
    {
        self::storage()->delete(self::getErrorLockFilename($lockKey));
    }

    public static function getError(string $lockKey): ?string
    {
        return self::storage()->get(self::getErrorLockFilename($lockKey));
    }

    public static function isOutdatedLock(string $lockKey): bool
    {
        $lockDate = Carbon::make(self::storage()->get(self::getSoftLockFilename($lockKey)));

        return Carbon::now()->isAfter($lockDate->addSeconds(config('process-manager.softlock_time')));
    }

    public static function lock(string $lockKey): void
    {
        self::storage()->put(self::getSoftLockFilename($lockKey), Carbon::now());
    }

    public static function error(string $lockKey, string $msg): void
    {
        self::storage()->put(self::getErrorLockFilename($lockKey), Carbon::now() . ' ' . $msg);
    }

    public static function notified(string $lockKey): bool
    {
        return self::storage()->exists(self::getSoftLockNotificationFilename($lockKey));
    }

    public static function removeLock(string $lockKey): void
    {
        self::storage()->delete(self::getSoftLockFilename($lockKey));
        self::storage()->delete(self::getSoftLockNotificationFilename($lockKey));
    }

    private static function getSoftLockFilename(string $lockKey): string
    {
        return Str::kebab($lockKey) . '.lock';
    }

    private static function getErrorLockFilename(string $lockKey): string
    {
        return Str::kebab($lockKey) . '.error';
    }

    private static function getSoftLockNotificationFilename(string $lockKey): string
    {
        return self::getSoftLockFilename($lockKey) . '.notified';
    }

    private static function storage(): Filesystem
    {
        // todo: make configurable | validate default disk is configured
        return Storage::disk('locks');
    }
}
