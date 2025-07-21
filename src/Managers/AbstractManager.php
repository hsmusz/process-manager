<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Managers;

use Movecloser\ProcessManager\Interfaces\ProcessesRepository;
use Movecloser\ProcessManager\Models\Process;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Mail;
use Throwable;

abstract class AbstractManager
{
    protected ?string $nextStep = null;
    protected ?Process $process;
    protected ?ProcessLoggerManager $processLoggerManager;

    public function __construct(
        protected readonly ProcessesRepository $processes,
        protected readonly StepsInterface $steps,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function handle(Process $process): void
    {
        $this->process = $process;

        logger()->info('Process | handling process ' . $this->process->id);

        try {
            $this->restoreProcessLogger($process);
            $this->bootProcess();
            $this->process();
        } catch (Throwable $e) {
            try {
                $this->handleException($e);
                logger()->info('Process | error in process ' . $this->process->id);
            } catch (Throwable $e) {
                logger()->info('Process | unable to handle exception in process ' . $this->process->id . ': ' . $e->getMessage());

                // failsafe for any unhandled exceptions
                $this->process->status = ProcessStatus::EXCEPTION;
                $this->process->save();
            }

            throw $e;
        }

        logger()->info('Process | finished process ' . $this->process->id);
    }

    abstract protected function bootProcess(): void;

    /**
     * @throws \Exception
     */
    protected function finish(): void
    {
        $this->finishProcess(ProcessStatus::SUCCESS);
    }

    /**
     * @throws Exception
     */
    protected function finishProcess(ProcessStatus $status): void
    {
        $this->processLoggerManager->finishProcess($status);
    }

    /**
     * @throws \Exception
     */
    protected function handleException(Throwable $e): void
    {
        $status = ProcessStatus::RETRY;
        $retry = $this->process->attempts < JobManagerService::MAX_RETRIES;
        if (!$retry) {
            $status = ProcessStatus::ERROR;
        }

        if ($e instanceof ProcessException) {
            $retry = false;
            $status = $e->status() ?? ProcessStatus::ERROR;
        }

        $details = [];
        if (method_exists($e, 'details')) {
            $details = $e->details();
        }

        $this->processLoggerManager->addStep(
            $e->getMessage(),
            $this->nextStep,
            $status,
            $details,
        );

        if ($retry) {
            $this->finishProcess(ProcessStatus::RETRY);
            $multiply = max($this->process->attempts - 3, 1);
            $this->process->retry_after = Carbon::now()->addSeconds(JobManagerService::RETRY_AFTER * $multiply);
            $this->process->save();
        } else {
            $this->finishProcess($status);

            Mail::to(config('integrator.notify_on_lockdown'))
                ->send(
                    new Lockdown(
                        $e->getMessage(),
                        $details,
                        'AELIA Microservice - PROCESS MANAGER LOCKDOWN!'
                    )
                );
        }
    }

    protected function nextStep(string $currentStep): void
    {
        $this->nextStep = $this->steps->next($currentStep);
    }

    /**
     * @throws \Exception
     */
    protected function process(): void
    {
        foreach ($this->steps->getValues() as $step) {
            if ($this->steps->isAfter($this->nextStep, $step)) {
                continue;
            }

            logger()->info('Process | processing process ' . $this->process->id . ' step ' . $step);

            $this->processStep($step);
            $this->nextStep($step);
        }
    }

    abstract protected function processStep(string $step): void;

    /**
     * @throws \Exception
     */
    protected function restoreProcessLogger(?Process $process): void
    {
        if (empty($process)) {
            throw new ProcessException('Missing existing process for restoring ProcessLogger');
        }

        $this->steps->validate();

        $this->processLoggerManager = new ProcessLoggerManager();
        $this->processLoggerManager->setProcess($process);
        $this->processLoggerManager->setSteps($this->steps);
        $this->nextStep = $this->processLoggerManager->getNextStep();

        if (empty($this->nextStep)) {
            throw new ProcessException('No valid step found to resume Process');
        }

        $process->incrementAttempts();
    }

    /**
     * @throws \Exception
     */
    protected function success(string $msg, string $step, array $details = []): void
    {
        $this->processLoggerManager->addStep(
            $msg,
            $step,
            ProcessStatus::SUCCESS,
            $details,
        );
    }
}
