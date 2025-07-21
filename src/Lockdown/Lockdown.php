<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Lockdown;

use Exception;
use Illuminate\Support\Facades\Mail;
use Movecloser\ProcessManager\Lockdown\Interfaces\Lock;
use Movecloser\ProcessManager\Lockdown\Mail\Lockdown as LockdownMail;
use Throwable;

class Lockdown
{
    private Lock $lock;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $this->lock = LockFactory::make();
    }

    public static function make($command): self
    {
        $self = new self();
        $self->setCommand($command);

        return $self;
    }

    /**
     * @throws \Exception
     */
    public function handle(): void
    {
        if ($this->lock->exists()) {
            $this->lock->outputLockdownMessage();
            throw new Exception('Lockdown.');
        }
    }

    public function lock(string $msg, array $data = [], Throwable $exception = null): void
    {
        $this->lock->engageLockdown($msg, $data, $exception);
        $this->lock->outputLockdownMessage();

        Mail::to(config('integrator.notify_on_lockdown'))->send(
            new LockdownMail($msg, [], config('app.name') . ' Microservice - LOCKDOWN!')
        );
    }

    public function resolve(): void
    {
        $this->lock->resolve();
    }

    public function setCommand(string $command): void
    {
        $this->lock->setCommand($command);
    }

    public function skip(): void
    {
        $this->lock->skip();
    }
}
