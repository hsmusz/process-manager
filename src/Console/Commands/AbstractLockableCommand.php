<?php

namespace Movecloser\ProcessManager\Console\Commands;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Movecloser\ProcessManager\Lockdown\CommandLock;
use Movecloser\ProcessManager\Support\LockHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

abstract class AbstractLockableCommand extends \Illuminate\Console\Command
{
    use LockHelper;

    public const string COMMAND_LOCK_OPTIONS = '
                                {--skip-lock : (Deprecated) Use --skip-global-lock instead}
                                {--skip-global-lock : Skip global lock (.disabled) — allow running disabled commands}
                                {--skip-command-lock : Skip command lock (.lock) — allow overlapping execution}
                                {--remove-lock : (Deprecated) Use --remove-command-lock instead}
                                {--remove-command-lock : Remove existing command lock before starting}
                                ';

    public function error($string, $verbosity = null): void
    {
        CommandLock::error($this->getLockKey(), $string);
        $this->line($string, 'error', $verbosity);
    }

    /**
     * @throws \Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->commandDisabled()) {
            $this->info(sprintf('[%s] %s command disabled', static::class, now()->toDateTimeString()));

            return static::INVALID;
        }

        $this->info(sprintf('[%s] %s starting...', static::class, now()->toDateTimeString()));

        $this->bootLock();

        $method = method_exists($this, 'handle') ? 'handle' : '__invoke';

        try {
            return (int) $this->laravel->call([$this, $method]);
        } catch (Throwable $e) {
            CommandLock::error($this->getLockKey(), $e->getMessage());

            if($this->shouldNotifyOnError() && !empty(config('app.notify_on_error'))) {
                Mail::raw(
                    implode(PHP_EOL . '------------' . PHP_EOL, [
                        $e->getMessage(),
                        $e->getTraceAsString(),
                        $e->getPrevious()?->getTraceAsString(),
                    ]),
                    static function (Message $message) {
                        $message->to(config('app.notify_on_error'))
                            ->subject(sprintf('[%s] %s | Microservice - command aborted: %s', config('app.env'), config('app.name'), static::class))
                            ->from(config('mail.from.address'), config('mail.from.name'));
                    }
                );
            }

            throw $e;
        }
    }

    protected function shouldNotifyOnError(): bool
    {
        // Notify about broken commands only on production
        //
        return app()->isProduction();
    }

}
