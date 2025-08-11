<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Exceptions;

use Exception;

/**
 * @author Hubert Smusz <hubert.smusz@movecloser.pl>
 *
 * Used to skip the process and mark it as finished without an error
 * The current step will be persisted as SKIPPED
 */
class ProcessEndedException extends Exception
{
    public function __construct(
        private string $step,
        string $msg,
        private array $details = [],
    ) {
        parent::__construct($msg);
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function getStep(): string
    {
        return $this->step;
    }
}
