<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Repositories;

use Carbon\Carbon;
use Movecloser\ProcessManager\Enum\ProcessStatus;
use Movecloser\ProcessManager\Interfaces\ProcessesRepository as Contract;
use Movecloser\ProcessManager\Models\Process;

class ProcessesRepository implements Contract
{
    private const int PROCESS_TTL = 60 * 60; // 1 hour

    public function hasTimeoutProcess(): bool
    {
        return Process::where('status', ProcessStatus::IN_PROGRESS)
            ->where('updated_at', '<', now()->subSeconds(self::PROCESS_TTL))
            ->exists();
    }

    public function isFatalErrorInProcesses(int $id): bool
    {
        return Process::query()
            ->whereIn('status', [
                ProcessStatus::ERROR,
                ProcessStatus::EXCEPTION,
            ])
            ->whereNot('id', $id)
            ->exists();
    }

    public function isRunning(): bool
    {
        return Process::where('status', ProcessStatus::IN_PROGRESS)
            ->exists();
    }

    public function nextAvailableProcess(): ?Process
    {
        $process = Process::whereIn('status', [ProcessStatus::PENDING, ProcessStatus::RETRY])
            ->orderBy('id')
            ->first();

        if (!$process
            || (ProcessStatus::RETRY === $process->status && Carbon::now()->isBefore($process->retry_after))
        ) {
            return null;
        }

        return $process;
    }

    public function restartTimoutProcess(): void
    {
        $process = Process::where('status', ProcessStatus::IN_PROGRESS)->firstOrFail();
        $process->restart();
    }
}
