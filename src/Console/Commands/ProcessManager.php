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
    protected $signature = 'process-manager:work
                                {processId? : (optional) Run any process that is NOT finished - do not check for error/retry timeout}
                                {--channel=default : Channel to run process on}
                                {--single : Run only one process}
                                {--restart : Allow running process marked as error, exception, pending retry}
                                {--force : force restart a process - works only with single process ID}
                                {--remove-lock : Make process manager disregard any previous lock}
                                {--skip-lock : Skip global lockdown}
                                ';
    private string $channel;
    private ProcessesRepository $processes;

    /**
     * Execute the console command.
     *
     * @throws \Exception
     */
    public function handle(ProcessesRepository $processes): int
    {
        if ($this->commandDisabled()) {
            $this->info('Command disabled');

            return self::INVALID;
        }

        if ($this->option('remove-lock')) {
            $this->removeCommandLock();
        }

        $this->processes = $processes;
        $this->channel = $this->option('channel');

        if ($this->processes->hasTimeoutProcess($this->channel)) {
            $this->alert('PROCESS TIMEOUT DETECTED - MARKING PROCESS FOR RETRY.');
            $this->processes->restartTimeoutProcess($this->channel);
            $this->removeCommandLock();
        }

        // use simplified lock, to disable overlapping process workers
        if (CommandLock::isLocked($this->getLockKey())) {
            $this->info('Locked - other process worker is running.');

            return self::SUCCESS;
        }

        if ($this->option('force') && empty($this->argument('processId'))) {
            $this->error('Force restart option works only with single process ID');

            return self::FAILURE;
        }

        if ($this->processes->isRunning($this->channel) && !$this->option('force')) {
            $this->error('Other process is running');

            return self::FAILURE;
        }

        CommandLock::lock($this->getLockKey());

        $singleProcess = $this->option('single') || !empty($this->argument('processId'));
        $lifetime = Carbon::now()->addMinutes(self::WORKER_LIFETIME)->endOfMinute()->subSeconds(30);
        while (Carbon::now()->lt($lifetime)) {
            if (Carbon::now()->isAfter(Carbon::now()->endOfDay()->subMinutes(self::DAILY_COOLDOWN))) {
                $this->info('Processing disabled until midnight.');
                break;
            }

            $status = $this->startNextProcess();
            if ($singleProcess) {
                break;
            }

            if (self::SUCCESS !== $status) {
                break;
            }

            sleep(1);
        }

        $this->removeCommandLock();

        $this->info('Finished.');

        return self::SUCCESS;
    }

    public function lockKeyArgument(): string
    {
        return $this->option('channel');
    }

    /**
     * @throws \Movecloser\ProcessManager\Exceptions\ProcessManagerException
     */
    private function startNextProcess(): int
    {
        if ($this->argument('processId')) {
            $process = $this->processes->find((int) $this->argument('processId'));
            if ($process->hasFinished() && !$this->option('force')) {
                $this->error('Process already finished.');

                return self::INVALID;
            }
        } else {
            $process = $this->processes->nextAvailableProcess($this->channel, $this->option('restart'));
        }

        if (!$process) {
            return self::INVALID;
        }

        try {
            if ($this->processes->isFatalErrorInProcesses($this->channel, $process->id) && !$this->option('restart')) {
                throw new ProcessManagerException('Fatal error in processes, fix it first!');
            }

            $this->info(sprintf('Handling process: %s', $process->id));
            $manager = ProcessManagerFactory::make($process, $this->channel);
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
