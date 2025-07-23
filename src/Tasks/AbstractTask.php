<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Tasks;

use Movecloser\ProcessManager\Contracts\Process;

class AbstractTask
{
    public function __construct(
        protected Process $process,
    ) {
    }

}
