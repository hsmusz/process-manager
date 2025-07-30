<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Movecloser\ProcessManager\Contracts\ProcessesRepository;
use Movecloser\ProcessManager\Exceptions\ProcessManagerException;
use Movecloser\ProcessManager\Lockdown\CommandLock;
use Movecloser\ProcessManager\ProcessManagerFactory;
use Movecloser\ProcessManager\Support\LockHelper;
use Throwable;

class ProcessManager extends Command
{
    use LockHelper;

    protected const ?string COMMAND_LOCK_KEY = 'process-manager';

    private const int DAILY_COOLDOWN = 10; // stop processing after END OF DAY minus 10 minutes
    private const int WORKER_LIFETIME = 5; // in minutes

    protected $description = 'Handling of new processes ';
    protected $signature = 'process-manager:work {--retry} {--fix} {--remove-lock}';

    private ProcessesRepository $processes;

    /**
     * Execute the console command.
     *
     * @throws \Exception
     */
    public function handle(ProcessesRepository $processes): int
    {
        if ($this->option('remove-lock')) {
            CommandLock::removeLock(self::lockKey());
        }

        if (CommandLock::commandDisabled(self::lockKey())) {
            $this->info('Command disabled');

            return self::INVALID;
        }

        $this->processes = $processes;

        if ($this->processes->hasTimeoutProcess()) {
            $this->alert('PROCESS TIMEOUT DETECTED - MARKING PROCESS FOR RETRY.');
            $this->processes->restartTimoutProcess();
            CommandLock::removeLock(self::lockKey());
        }

        // use simplified lock, to disable overlapping process workers
        if (CommandLock::isLocked(self::lockKey())) {
            $this->info('Locked - other process worker is running.');

            return self::SUCCESS;
        }

        if ($this->processes->isRunning()) {
            $this->error('Other process is running');

            return self::FAILURE;
        }

        CommandLock::lock(self::lockKey());

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

        CommandLock::removeLock(self::lockKey());

        $this->info('Finished.');

        return self::SUCCESS;
    }

    private function startNextProcess(): int
    {
        $process = $this->processes->nextAvailableProcess($this->option('retry'), $this->option('fix'));

        if (!$process) {
            return self::INVALID;
        }

        try {
            if ($this->processes->isFatalErrorInProcesses($process->id) && !$this->option('fix')) {
                throw new ProcessManagerException('Fatal error in processes, fix it first!');
            }

            $this->info(sprintf('Handling process: %s', $process->id));
            $manager = ProcessManagerFactory::make($process);
            $manager->handle();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            if (method_exists($e, 'getDetails')) {
                $this->error(json_encode($e->getDetails(), JSON_PRETTY_PRINT));
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
