<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Exceptions;

use Exception;
use Movecloser\ProcessManager\Enum\ProcessStatus;

/**
 * @author Hubert Smusz <hubert.smusz@movecloser.pl>
 */
class ProcessException extends Exception
{
    private array $details;
    private ?ProcessStatus $status;

    public function __construct($message = '', $details = [], ?ProcessStatus $status = null)
    {
        parent::__construct($message);

        $this->details = $details;
        $this->status = $status;
    }

    public function details(): array
    {
        return $this->details;
    }

    public function status(): ?ProcessStatus
    {
        return $this->status;
    }

}
