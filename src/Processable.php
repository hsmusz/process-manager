<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager;

readonly class Processable
{
    public function __construct(
        public string $type,
        public mixed $id,
        public array $meta = [],
    ) {
    }

}
