<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Console\Commands;

use Illuminate\Console\Command;
use Movecloser\ProcessManager\Contracts\ProcessesRepository;

class RestartProcess extends Command
{
    protected $description = 'Restart Process (change status only)';
    protected $signature = 'process-manager:restart-process {processId} {--force}';

    private ProcessesRepository $processes;

    /**
     * Execute the console command.
     *
     * @throws \Exception
     */
    public function handle(ProcessesRepository $processes): int
    {
        $process = $processes->find((int) $this->argument('processId'));
        if (!$process->canBeRestarted() && !$this->option('force')) {
            $this->error('Process cannot be restarted');

            return self::FAILURE;
        }

        $process->restart();

        return self::SUCCESS;
    }
}
