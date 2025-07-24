<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Exceptions;

use Exception;
use Throwable;

/**
 * @author Hubert Smusz <hubert.smusz@movecloser.pl>
 */
class ProcessStepException extends Exception
{
    private array $details;

    public function __construct(Throwable|string $exception, $details = [])
    {
        if ($exception instanceof Throwable) {
            if (method_exists($exception, 'getDetails')) {
                $details = $exception->getDetails();
            }
            parent::__construct($exception->getMessage(), $exception->getCode(), $exception);
        } else {
            parent::__construct($exception);
        }

        $this->details = $details;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

}
