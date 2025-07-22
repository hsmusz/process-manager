<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Movecloser\ProcessManager\Exceptions\ProcessManagerException;
use Movecloser\ProcessManager\Interfaces\ProcessesRepository;
use Movecloser\ProcessManager\Lockdown\CommandLock;
use Movecloser\ProcessManager\ProcessManagerFactory;
use Throwable;

class ProcessManager extends Command
{
    public const string COMMAND_LOCK_KEY = 'process-manager';

    private const int DAILY_COOLDOWN = 10; // stop processing after END OF DAY minus 10 minutes
    private const int WORKER_LIFETIME = 5; // in minutes

    protected $description = 'Handling of new processes ';
    protected $signature = 'app:process:work {--remove-lock}';

    private ProcessesRepository $processes;

    /**
     * Execute the console command.
     *
     * @throws \Exception
     */
    public function handle(ProcessesRepository $processes): int
    {
        if ($this->option('remove-lock')) {
            CommandLock::removeLock(self::COMMAND_LOCK_KEY);
        }

        if (CommandLock::commandDisabled(self::COMMAND_LOCK_KEY)) {
            $this->info('Command disabled');

            return self::INVALID;
        }

        $this->processes = $processes;

        if ($this->processes->hasTimeoutProcess()) {
            $this->alert('PROCESS TIMEOUT DETECTED - MARKING PROCESS FOR RETRY.');
            $this->processes->restartTimoutProcess();
            CommandLock::removeLock(self::COMMAND_LOCK_KEY);
        }

        // use simplified lock, to disable overlapping process workers
        if (CommandLock::isLocked(self::COMMAND_LOCK_KEY)) {
            $this->info('Locked - other process worker is running.');

            return self::SUCCESS;
        }

        if ($this->processes->isRunning()) {
            $this->error('Other process is running');

            return self::FAILURE;
        }

        CommandLock::lock(self::COMMAND_LOCK_KEY);

        $lifetime = Carbon::now()->addMinutes(self::WORKER_LIFETIME)->endOfMinute()->subSeconds(30);
        while (Carbon::now()->lt($lifetime)) {
            if (Carbon::now()->isAfter(Carbon::now()->endOfDay()->subMinutes(self::DAILY_COOLDOWN))) {
                $this->info('Processing disabled until midnight.');
                break;
            }

            $status = $this->startNextProcess();
            if (self::SUCCESS !== $status) {
                break;
            }

            sleep(1);
        }

        CommandLock::removeLock(self::COMMAND_LOCK_KEY);

        $this->info('Finished.');

        return self::SUCCESS;
    }

    private function startNextProcess(): int
    {
        $process = $this->processes->nextAvailableProcess();

        if (!$process) {
            return self::INVALID;
        }

        try {
            if ($this->processes->isFatalErrorInProcesses($process->id)) {
                throw new ProcessManagerException('Fatal error in processes, fix it first!');
            }

            $this->info(sprintf('Handling process: %s', $process->id));
            $manager = ProcessManagerFactory::make($process->type);
            $manager->handle($process);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
