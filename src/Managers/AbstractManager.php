<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Managers;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Mail;
use Movecloser\ProcessManager\Enum\ProcessStatus;
use Movecloser\ProcessManager\Exceptions\ProcessException;
use Movecloser\ProcessManager\Facades\ProcessLogger;
use Movecloser\ProcessManager\Interfaces\ProcessesRepository;
use Movecloser\ProcessManager\Interfaces\Process;
use Movecloser\ProcessManager\Lockdown\Mail\Lockdown;
use Movecloser\ProcessManager\Models\Process as ProcessModel;
use Movecloser\ProcessManager\Models\ProcessStep;
use Movecloser\ProcessManager\Processable;
use Movecloser\ProcessManager\Processes\AbstractProcess;
use Throwable;

abstract class AbstractManager
{
    protected const int MAX_RETRIES = 50;
    protected const int RETRY_AFTER = 60; // in seconds

    protected ?string $nextStep = null;
    protected ?ProcessModel $process;

    public function __construct(
        protected readonly Process $instruction,
        protected readonly ProcessesRepository $processes,
    ) {
    }

    public static function hasProcessFor(Processable $processable): bool
    {
        return ProcessModel::query()
            ->where([
                'manager' => static::class,
                'processable_type' => $processable->type,
                'processable_id' => $processable->id,
            ])
            ->exists();
    }

    /**
     * @throws \Exception
     */
    public function createProcess(
        Processable $processable,
        int $version,
    ): self {
        try {
            $this->process = ProcessModel::create([
                ProcessModel::STATUS => ProcessStatus::READY,
                ProcessModel::MANAGER => static::class,
                ProcessModel::PROCESSABLE_TYPE => $processable->type,
                ProcessModel::PROCESSABLE_ID => $processable->id,
                ProcessModel::VERSION => $version,
                ProcessModel::META => $processable->meta,
            ]);

            return $this;
        } catch (Exception $e) {
            throw new Exception(sprintf('Error while creating process, Error: %s', $e->getMessage()));
        }
    }

    /**
     * @throws Exception
     */
    public function finishProcess(ProcessStatus $status): void
    {
        $this->process->status = $status;
        $this->process->save();
    }

    public function getNextStep(): ?string
    {
        if ($this->process->steps()->where('step', AbstractProcess::FINISH)->exists()) {
            throw new ProcessException('Process already finished.', [], ProcessStatus::INFO);
        }

        $stepProcess = $this->process->steps()
            ->orderBy('id', 'desc')
            ->first();

        if (!$stepProcess) {
            return $this->instruction->start();
        }

        if (in_array($stepProcess->status, [ProcessStatus::ERROR, ProcessStatus::RETRY, ProcessStatus::EXCEPTION])) {
            return $stepProcess->step;
        }

        return $this->instruction->next($stepProcess->step);
    }

    /**
     * @throws \Throwable
     */
    public function handle(ProcessModel $process): void
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
     * @throws \Exception
     */
    protected function handleException(Throwable $e): void
    {
        $status = ProcessStatus::RETRY;
        $retry = $this->process->attempts < self::MAX_RETRIES;
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

        $this->addStep(
            $e->getMessage(),
            $this->nextStep,
            $status,
            $details,
        );

        if ($retry) {
            $this->finishProcess(ProcessStatus::RETRY);
            $multiply = max($this->process->attempts - 3, 1);
            $this->process->retry_after = Carbon::now()->addSeconds(self::RETRY_AFTER * $multiply);
            $this->process->save();
        } else {
            $this->finishProcess($status);

            Mail::to(config('integrator.notify_on_lockdown'))
                ->send(
                    new Lockdown(
                        $e->getMessage(),
                        $details,
                        config('app.name') . ' Microservice - PROCESS MANAGER LOCKDOWN!'
                    )
                );
        }
    }

    protected function prepareNextStep(string $currentStep): void
    {
        $this->nextStep = $this->instruction->next($currentStep);
    }

    /**
     * @throws \Exception
     */
    protected function process(): void
    {
        foreach ($this->instruction->getValues() as $step) {
            if ($this->instruction->isAfter($this->nextStep, $step)) {
                continue;
            }

            logger()->info('Process | processing process ' . $this->process->id . ' step ' . $step);

            $this->processStep($step);
            $this->prepareNextStep($step);
        }
    }

    abstract protected function processStep(string $step): void;

    /**
     * @throws \Exception
     */
    protected function restoreProcessLogger(?ProcessModel $process): void
    {
        if (empty($process)) {
            throw new ProcessException('Missing existing process for restoring ProcessLogger');
        }

        $this->instruction->validate();
        $this->nextStep = $this->getNextStep();

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
        $this->addStep(
            $msg,
            $step,
            ProcessStatus::SUCCESS,
            $details,
        );
    }

    /**
     * @throws Exception
     */
    private function addStep(
        string $message,
        ?string $step,
        ProcessStatus $status,
        array $details = [],
    ): void {
        $detailsJson = json_encode($details, JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT);
        if ($detailsJson === false) {
            throw new Exception('Could not encode details to JSON.');
        }

        if (!$step) {
            $step = $this->instruction->finish();
            $status = ProcessStatus::INFO;
        }

        $processStep = new ProcessStep();
        $processStep->setAttribute(ProcessStep::STEP, $step);
        $processStep->setAttribute(ProcessStep::STATUS, $status);
        $processStep->setAttribute(ProcessStep::MESSAGE, $message);
        $processStep->setAttribute(ProcessStep::DETAILS, $detailsJson);
        $processStep->setAttribute(ProcessStep::LOGS, json_encode(ProcessLogger::dump(), JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT));
        $processStep->process()->associate($this->process);

        $processStep->save();
    }
}
