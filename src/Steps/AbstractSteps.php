<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Steps;

use Movecloser\ProcessManager\Exceptions\ProcessException;

abstract class AbstractSteps
{
    protected const array CASES = [];
    public const string START = '__START__';
    public const string FINISH = '__FINISH__';
    public static int $version = 0;
    protected ?int $bootVersion = null;

    public function __construct(int $version = null)
    {
        $this->bootVersion = $version;
    }

    public function finish(): string
    {
        return self::FINISH;
    }

    public function getValues(): array
    {
        return static::CASES;
    }

    public function isAfter(string $before, string $after): bool
    {
        return self::getIndex($before) > self::getIndex($after);
    }

    public function next(string $val): ?string
    {
        $index = self::getIndex($val);

        return static::CASES[$index + 1] ?? null;
    }

    public function start(): string
    {
        return self::START;
    }

    /**
     * @throws \Exception
     */
    public function validate(): void
    {
        if (!is_null($this->bootVersion) && static::$version !== $this->bootVersion) {
            throw new ProcessException(sprintf('Invalid version detected. Please upgrade to version %d', static::$version));
        }
    }

    private static function getIndex(string $val): ?int
    {
        return array_search($val, static::CASES);
    }
}
