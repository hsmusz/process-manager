<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager;

use InvalidArgumentException;
use Movecloser\ProcessManager\Interfaces\ProcessesRepository;
use Movecloser\ProcessManager\Interfaces\ProcessManager;

class ProcessManagerFactory
{
    protected static array $managers = [];

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \Exception
     */
    public static function make(string $type): ProcessManager
    {
        if (!in_array($type, static::$managers)) {
            throw new InvalidArgumentException(sprintf('The manager type "%s" does not exist.', $type));
        }

        $steps = static::$managers[$type];

        return new $type(new $steps(), app()->make(ProcessesRepository::class));
    }

    public static function registerManagers(array $managers): void
    {
        // @todo: validate if key => value are Manager => Steps

        static::$managers = array_merge(static::$managers, $managers);
    }
}
