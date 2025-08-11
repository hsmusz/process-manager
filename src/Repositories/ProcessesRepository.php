<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Repositories;

use Carbon\Carbon;
use Exception;
use Movecloser\ProcessManager\Contracts\ProcessesRepository as Contract;
use Movecloser\ProcessManager\Enum\ProcessStatus;
use Movecloser\ProcessManager\Models\Process;
use Movecloser\ProcessManager\Processable;
use Throwable;

class ProcessesRepository implements Contract
{
    private const int PROCESS_TTL = 60 * 60; // 1 hour

    /**
     * @throws \Exception
     */
    public static function createProcess(string $process, Processable $processable): Process
    {
        try {
            return Process::create([
                Process::STATUS => ProcessStatus::PENDING,
                Process::PROCESS => $process,
                Process::PROCESSABLE_TYPE => $processable->type,
                Process::PROCESSABLE_ID => $processable->id,
                Process::VERSION => $process::$version,
                Process::META => $processable->meta,
            ]);
        } catch (Throwable $e) {
            throw new Exception(sprintf('Error while creating process, Error: %s', $e->getMessage()));
        }
    }

    public static function hasProcessFor(string $process, Processable $processable): bool
    {
        return Process::query()
            ->where([
                'process' => $process,
                'processable_type' => $processable->type,
                'processable_id' => $processable->id,
            ])
            ->exists();
    }

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

    public function nextAvailableProcess(bool $forceRetry = false, bool $allowFix = false): ?Process
    {
        $statuses = [ProcessStatus::PENDING, ProcessStatus::RETRY];
        if($allowFix) {
            $statuses[] = ProcessStatus::ERROR;
            $statuses[] = ProcessStatus::EXCEPTION;
        }
        $process = Process::whereIn('status', $statuses)
            ->orderBy('id')
            ->first();

        if (!$process
            || (ProcessStatus::RETRY === $process->status && Carbon::now()->isBefore($process->retry_after) && !$forceRetry)
        ) {
            return null;
        }

        return $process;
    }

    public function find(int $id): Process
    {
        return Process::findOrFail($id);
    }

    public function restartTimoutProcess(): void
    {
        $process = Process::where('status', ProcessStatus::IN_PROGRESS)->firstOrFail();
        $process->restart();
    }
}
