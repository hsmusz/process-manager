<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Exceptions;

use Exception;

/**
 * @author Hubert Smusz <hubert.smusz@movecloser.pl>
 */
class ProcessStepException extends Exception
{
    private array $details;

    public function __construct($message = '', $details = [])
    {
        parent::__construct($message);

        $this->details = $details;
    }

    public function details(): array
    {
        return $this->details;
    }

}
