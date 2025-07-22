<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager;

class Processable
{
    public function __construct(
        public readonly string $type,
        public readonly mixed $id,
        public readonly array $meta = [],
    ) {
    }

}
