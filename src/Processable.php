<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager;

use Movecloser\ProcessManager\Contracts\ProcessesRepository;

readonly class Processable
{
    public function __construct(
        public string $type,
        public mixed $id,
        public array $meta = [],
        public string $channel = ProcessesRepository::CHANNEL_DEFAULT,
    ) {
    }

}
