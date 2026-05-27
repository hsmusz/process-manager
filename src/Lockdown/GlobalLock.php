<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Lockdown;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GlobalLock
{
    public static function allDisabled(): bool
    {
        return self::storage()->exists('all-commands.disabled');
    }

    public static function isDisabled(string $lockKey): bool
    {
        return self::allDisabled() || self::storage()->exists(Str::kebab($lockKey) . '.disabled');
    }

    private static function storage(): Filesystem
    {
        return Storage::disk('locks');
    }
}
