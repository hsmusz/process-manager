<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Exceptions;

use Exception;
use Movecloser\ProcessManager\Enum\ProcessStatus;

/**
 * @author Hubert Smusz <hubert.smusz@movecloser.pl>
 *
 * Used to skip process and mark it as finished without an error
 */
class ProcessEndedException extends Exception
{


}
