<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Enum;

enum ProcessStatus: string
{
    case PENDING = 'PENDING';
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case ERROR = 'ERROR';
    case SUCCESS = 'SUCCESS';
    case ABORTED = 'ABORTED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case RETRY = 'RETRY';
    case EXCEPTION = 'EXCEPTION';
}
