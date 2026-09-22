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
use Movecloser\ProcessManager\Support\ErrorMessage;
use Throwable;

class CommandLock
{
    private const int KEEP_LAST_N_LINES = 10;

    public static function delayAndLock(string $lockKey): void
    {
        $rand = random_int(100000, 1000000);
        Log::info(
            sprintf('Command [%s]: Sleeping %d microseconds (%s seconds)',
                $lockKey,
                $rand,
                round($rand / 1000000, 2)
            )
        );
        usleep($rand);

        if (self::isLocked($lockKey)) {
            Log::error(sprintf('Command %s locked', $lockKey));

            if (self::isOutdatedLock($lockKey) && !self::notified($lockKey)) {
                self::storage()->put(self::getSoftLockNotificationFilename($lockKey), Carbon::now());

                if (!empty(config('process-manager.notify_on_soft_lock'))) {
                    Mail::to(config('process-manager.notify_on_soft_lock'))->send(
                        new LockdownMail(
                            config('app.name') . ' - soft lock detected',
                            sprintf('Found old soft-lock on command %s: %s', $lockKey, self::storage()->get($lockKey)),
                            LockdownMail::LOCKDOWN_TYPE_SOFTLY,
                            [],
                        )
                    );
                }
            }

            dd(sprintf('Command %s locked', $lockKey)); // exit without exception :)
        }

        self::lock($lockKey);
        self::markAsStarted($lockKey);
    }

    public static function error(?string $lockKey, string $msg): void
    {
        if (!$lockKey || !self::isLocked($lockKey)) {
            return;
        }

        $errors = self::retrieveErrorLog($lockKey);
        $errors[] = sprintf('[%s] %s', Carbon::now(), ErrorMessage::plain($msg));
        $errors = array_slice($errors, -10);

        self::storage()->put(self::getErrorLockFilename($lockKey), implode("\n", $errors));
    }

    /**
     * @return array{
     *     current: array<int, array{at: ?\Carbon\Carbon, message: string}>,
     *     previous: array<int, array{at: ?\Carbon\Carbon, message: string}>
     * }
     */
    public static function errorLog(string $lockKey): array
    {
        $lastExecution = self::lastExecution($lockKey);
        $log = ['current' => [], 'previous' => []];

        foreach (self::retrieveErrorLog($lockKey) as $entry) {
            $at = self::entryDate($entry);
            $current = !$lastExecution || $at?->greaterThanOrEqualTo($lastExecution);

            $log[$current ? 'current' : 'previous'][] = [
                'at' => $at,
                'message' => self::entryMessage($entry),
            ];
        }

        return $log;
    }

    public static function failedLastExecution(string $lockKey): bool
    {
        return !empty(self::errorLog($lockKey)['current']);
    }

    public static function getError(string $lockKey): ?string
    {
        return self::storage()->get(self::getErrorLockFilename($lockKey));
    }

    public static function hasError(string $lockKey): bool
    {
        return self::storage()->exists(self::getErrorLockFilename($lockKey));
    }

    public static function isLocked(string $lockKey): bool
    {
        return self::storage()->exists(self::getSoftLockFilename($lockKey));
    }

    public static function isOutdatedLock(string $lockKey): bool
    {
        $lockDate = Carbon::make(self::storage()->get(self::getSoftLockFilename($lockKey)));

        return Carbon::now()->isAfter($lockDate?->addSeconds(config('process-manager.softlock_time')));
    }

    public static function lastExecution(string $lockKey): ?Carbon
    {
        return Carbon::make(self::storage()->get(self::getExecutionFilename($lockKey)));
    }

    public static function lastExecutionDate(string $lockKey): string
    {
        return self::lastExecution($lockKey)?->format('Y-m-d H:i:s') ?? '';
    }

    public static function lock(string $lockKey): void
    {
        self::storage()->put(self::getSoftLockFilename($lockKey), Carbon::now());
    }

    public static function notified(string $lockKey): bool
    {
        return self::storage()->exists(self::getSoftLockNotificationFilename($lockKey));
    }

    public static function removeError(string $lockKey): void
    {
        $errors = self::retrieveErrorLog($lockKey);
        if (empty($errors)) {
            return;
        }

        $errors = array_slice($errors, 0 - self::KEEP_LAST_N_LINES);
        $errors = array_filter(
            $errors,
            static fn($val) => self::entryDate($val)?->isAfter(Carbon::now()->subHours(72)) ?? false
        );

        if (empty($errors)) {
            self::storage()->delete(self::getErrorLockFilename($lockKey));
        } else {
            self::storage()->put(self::getErrorLockFilename($lockKey), implode(PHP_EOL, $errors));
        }
    }

    public static function removeLock(string $lockKey): void
    {
        self::storage()->delete(self::getSoftLockFilename($lockKey));
        self::storage()->delete(self::getSoftLockNotificationFilename($lockKey));
    }

    private static function entryDate(string $entry): ?Carbon
    {
        if (!preg_match('/^\[([^\]]+)\]/', $entry, $matches)) {
            return null;
        }

        try {
            return Carbon::make($matches[1]);
        } catch (Throwable) {
            return null;
        }
    }

    private static function entryMessage(string $entry): string
    {
        return preg_replace('/^\[[^\]]+\]\s*/', '', $entry, 1) ?? $entry;
    }

    private static function getErrorLockFilename(string $lockKey): string
    {
        return Str::kebab($lockKey) . '.error';
    }

    private static function getExecutionFilename(string $lockKey): string
    {
        return self::getSoftLockFilename($lockKey) . '.execution';
    }

    private static function getSoftLockFilename(string $lockKey): string
    {
        return Str::kebab($lockKey) . '.lock';
    }

    private static function getSoftLockNotificationFilename(string $lockKey): string
    {
        return self::getSoftLockFilename($lockKey) . '.notified';
    }

    private static function markAsStarted(string $lockKey): void
    {
        self::storage()->put(self::getExecutionFilename($lockKey), Carbon::now());
    }

    private static function retrieveErrorLog(string $lockKey): array
    {
        $errorData = self::storage()->get(self::getErrorLockFilename($lockKey));
        if (!$errorData) {
            return [];
        }

        $entries = [];
        foreach (preg_split('/\R/u', $errorData) as $line) {
            if (trim($line) === '') {
                continue;
            }

            // wpisy sprzed 1.3.1 bywaja wielolinijkowe - linia bez [daty] to dalszy ciag poprzedniej
            if (empty($entries) || self::entryDate($line)) {
                $entries[] = $line;
                continue;
            }

            $entries[array_key_last($entries)] .= ' ' . trim($line);
        }

        return $entries;
    }

    private static function storage(): Filesystem
    {
        // todo: make configurable | validate default disk is configured
        return Storage::disk('locks');
    }
}
