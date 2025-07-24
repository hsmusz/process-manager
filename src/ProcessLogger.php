<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager;

class ProcessLogger implements Contracts\ProcessLogger
{
    private array $bag = [];

    public function dump(): array
    {
        $bag = $this->bag;
        $this->bag = [];

        return $bag;
    }

    public function log(string $action, array $payload): void
    {
        $this->bag[] = [
            'action' => $action,
            'payload' => $payload,
        ];
    }
}
