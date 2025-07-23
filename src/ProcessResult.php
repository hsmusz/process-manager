<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager;

class ProcessResult
{
    public function __construct(
        public string $message,
        public array $data = [],
    ) {
    }

}
