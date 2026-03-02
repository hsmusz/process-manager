<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Repositories;

use Carbon\Carbon;
use Movecloser\ProcessManager\Contracts\ProcessesRepository as Contract;
use Movecloser\ProcessManager\Enum\ProcessStatus;
use Movecloser\ProcessManager\Models\Process;
use Movecloser\ProcessManager\Processable;

class ProcessesRepository implements Contract
{

    private const int PROCESS_TTL = 60 * 60; // 1 hour

    // TODO: Add Channel management via Factory Setup ?

    public static function createProcess(string $process, Processable $processable): Process
    {
        return Process::create([
            Process::STATUS => ProcessStatus::PENDING,
            Process::PROCESS => $process,
            Process::PROCESSABLE_TYPE => $processable->type,
            Process::PROCESSABLE_ID => $processable->id,
            Process::VERSION => $process::$version,
            Process::META => $processable->meta,
            Process::CHANNEL => $processable->channel,
        ]);
    }

    public static function hasProcessFor(string $process, Processable $processable): bool
    {
        return Process::query()
            ->where([
                'process' => $process,
                'processable_type' => $processable->type,
                'processable_id' => $processable->id,
                'channel' => $processable->channel,
            ])
            ->exists();
    }

    public function find(int $id): Process
    {
        return Process::findOrFail($id);
    }

    public function hasTimeoutProcess(string $channel): bool
    {
        return Process::where('status', ProcessStatus::IN_PROGRESS)
            ->where('updated_at', '<', now()->subSeconds(self::PROCESS_TTL))
            ->where('channel', $channel)
            ->exists();
    }

    public function isFatalErrorInProcesses(string $channel, int $id): bool
    {
        return Process::query()
            ->whereIn('status', [
                ProcessStatus::ERROR,
                ProcessStatus::EXCEPTION,
            ])
            ->whereNot('id', $id)
            ->where('channel', $channel)
            ->exists();
    }

    public function isRunning(string $channel): bool
    {
        return Process::where('status', ProcessStatus::IN_PROGRESS)
            ->where('channel', $channel)
            ->exists();
    }

    public function nextAvailableProcess(string $channel, bool $restart = false): ?Process
    {
        $statuses = [ProcessStatus::PENDING, ProcessStatus::RETRY];
        if ($restart) {
            $statuses[] = ProcessStatus::ERROR;
            $statuses[] = ProcessStatus::EXCEPTION;
        }

        $process = Process::whereIn('status', $statuses)
            ->where('channel', $channel)
            ->orderBy('id')
            ->first();

        if (!$process
            || (!$restart && ProcessStatus::RETRY === $process->status && Carbon::now()->isBefore($process->retry_after))
        ) {
            return null;
        }

        return $process;
    }

    public function restartTimeoutProcess(string $channel): void
    {
        $process = Process::where('status', ProcessStatus::IN_PROGRESS)
            ->where('channel', $channel)
            ->firstOrFail();

        $process->restart();
    }
}
