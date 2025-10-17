<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Mail;
use Movecloser\ProcessManager\Contracts\ProcessesRepository;
use Movecloser\ProcessManager\Contracts\ProcessTask;
use Movecloser\ProcessManager\Enum\ProcessStatus;
use Movecloser\ProcessManager\Exceptions\ProcessEndedException;
use Movecloser\ProcessManager\Exceptions\ProcessException;
use Movecloser\ProcessManager\Facades\ProcessLogger;
use Movecloser\ProcessManager\Lockdown\Mail\Lockdown;
use Movecloser\ProcessManager\Models\Process;
use Movecloser\ProcessManager\Models\ProcessStep;
use Movecloser\ProcessManager\Processes\AbstractProcess;
use ReflectionClass;
use Throwable;

class ProcessManager implements Contracts\ProcessManager
{
    protected const int MAX_RETRIES = 50;
    protected const int RETRY_AFTER = 60; // in seconds

    protected ?string $nextStep = null;
    private Contracts\Process|Contracts\ProcessSteps $process;

    public function __construct(
        protected readonly Process $model,
        protected readonly ProcessesRepository $processes,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function handle(): void
    {
        $this->setStatus(ProcessStatus::IN_PROGRESS);
        $this->log('Process | handling process ' . $this->model->id);

        try {
            $this->restoreProcess();
            $this->processLoop();
        } catch (Throwable $e) {
            // allow for processes to be skipped if logic allows that
            if ($e instanceof ProcessEndedException) {
                $this->persistStep($e->getStep(), ProcessStatus::INFO, $e->getMessage(), $e->getDetails());
                $this->setStatus(ProcessStatus::SKIPPED);
            } else {
                try {
                    $this->handleException($e);
                    $this->log('Process | error in process ' . $this->model->id, $e);
                } catch (Throwable $e) {
                    $this->log('Process | unable to handle exception in process ' . $this->model->id . ': ' . $e->getMessage(), $e);

                    // failsafe for any unhandled exceptions
                    $this->setStatus(ProcessStatus::EXCEPTION);
                }

                throw $e;
            }
        }

        $this->log('Process | finished process ' . $this->model->id . ':' . $this->model->status->value);
    }

    /**
     * @throws \Exception
     */
    protected function handleException(Throwable $e): void
    {
        $this->process->handleException($e);

        $status = ProcessStatus::RETRY;
        $retry = $this->model->attempts < self::MAX_RETRIES;
        if (!$retry) {
            $status = ProcessStatus::ERROR;
        }

        if ($e instanceof ProcessException) {
            $retry = false;
            $status = $e->status() ?? ProcessStatus::ERROR;
        }

        $details = [];
        if (method_exists($e, 'getDetails')) {
            $details = $e->getDetails();
        }

        $this->persistStep($this->nextStep, $status, $e->getMessage(), $details);

        if ($retry) {
            $this->setStatus(ProcessStatus::RETRY);
            $multiply = max($this->model->attempts - 3, 1);
            $this->model->retry_after = Carbon::now()->addSeconds(self::RETRY_AFTER * $multiply);
            $this->model->save();
        } else {
            $this->setStatus($status);

            if (empty(config('process-manager.notify_on_lockdown'))) {
                return;
            }

            Mail::to(config('process-manager.notify_on_lockdown'))
                ->send(
                    new Lockdown(
                        config('app.name') . ' Microservice - PROCESS MANAGER LOCKDOWN!',
                        $e->getMessage(),
                        Lockdown::LOCKDOWN_TYPE_HARD,
                        $details,
                    )
                );
        }
    }

    /**
     * @throws \Exception
     */
    protected function processLoop(): void
    {
        $this->process->boot();

        foreach ($this->process->getSteps() as $step => $handler) {
            if ($this->process->isAfter($this->nextStep, $step)) {
                continue;
            }

            $this->log('Process | processing process ' . $this->model->id . ' step ' . $step);

            $this->process->beforeNextStep();

            if (method_exists($this->process, $handler)) {
                $result = $this->process->{$handler}();
            } elseif (class_exists($handler)) {
                $reflection = new ReflectionClass($handler);
                if (!$reflection->implementsInterface(ProcessTask::class)) {
                    throw new ProcessException('Step task handler does not implement Contracts\ProcessTask Interface', [
                        'step' => $step,
                        'handler' => $handler,
                    ]);
                }

                $result = (new $handler($this->process))->handle();
            } else {
                throw new ProcessException('Unknown step to process', ['step' => $step]);
            }

            $this->success($step, $result->message, $result->data);

            $this->prepareNextStep($step);
        }

        $this->setStatus(ProcessStatus::SUCCESS);
    }

    /**
     * @throws \Exception
     */
    protected function restoreProcess(): void
    {
        $this->process = new $this->model->process(
            new Processable(
                $this->model->processable_type,
                $this->model->processable_id,
                $this->model->meta
            ),
            $this->model->version
        );
        $this->process->validate();

        $this->nextStep = $this->getNextStep();

        if (empty($this->nextStep)) {
            throw new ProcessException('No valid step found to resume Process');
        }

        $this->model->incrementAttempts();
    }

    /**
     * @throws \Exception
     */
    protected function success(string $step, string $msg, array $details = []): void
    {
        $this->persistStep($step, ProcessStatus::SUCCESS, $msg, $details);
    }

    /**
     * @throws \Movecloser\ProcessManager\Exceptions\ProcessException
     */
    private function getNextStep(): ?string
    {
        if ($this->model->steps()->where('step', AbstractProcess::FINISH)->exists()) {
            throw new ProcessException('Process already finished.', [], ProcessStatus::INFO);
        }

        $processStep = $this->model->steps()
            ->orderBy('id', 'desc')
            ->first();

        if (!$processStep) {
            return AbstractProcess::START;
        }

        if (in_array($processStep->status, [ProcessStatus::ERROR, ProcessStatus::RETRY, ProcessStatus::EXCEPTION])) {
            return $processStep->step;
        }

        return $this->process->next($processStep->step);
    }

    private function log(string $msg, ?Throwable $e = null): void
    {
        // todo: validate channel is configured
        logger()->channel('process-manager')->info($msg);
        if ($e) {
            logger()->channel('process-manager')->error($e->getMessage());
            logger()->channel('process-manager')->error($e->getTraceAsString());
        }
    }

    /**
     * @throws Exception
     */
    private function persistStep(
        ?string $step,
        ProcessStatus $status,
        string $message,
        array $details = [],
    ): void {
        $detailsJson = json_encode($details, JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT);
        if ($detailsJson === false) {
            throw new Exception('Could not encode details to JSON.');
        }

        $processStep = new ProcessStep();
        $processStep->setAttribute(ProcessStep::STEP, $step);
        $processStep->setAttribute(ProcessStep::STATUS, $status);
        $processStep->setAttribute(ProcessStep::MESSAGE, $message);
        $processStep->setAttribute(ProcessStep::DETAILS, $detailsJson);
        $processStep->setAttribute(ProcessStep::LOGS, json_encode(ProcessLogger::dump(), JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT));
        $processStep->process()->associate($this->model);

        $processStep->save();
    }

    private function prepareNextStep(string $currentStep): void
    {
        $this->nextStep = $this->process->next($currentStep);
    }

    private function setStatus(ProcessStatus $status): void
    {
        $this->model->status = $status;
        $this->model->save();
    }
}
