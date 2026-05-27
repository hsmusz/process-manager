<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Movecloser\ProcessManager\Support\LockHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Tests\TestCase;

class LockHelperTest extends TestCase
{
    private string $disk = 'locks';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filesystems.disks.locks', [
            'driver' => 'local',
            'root' => storage_path('app/locks'),
            'throw' => false,
        ]);

        Storage::fake($this->disk);
    }

    private function makeCommand(array $options = []): Command
    {
        $command = new class extends Command {
            use LockHelper;

            protected const ?string COMMAND_LOCK_KEY = 'test-command';

            protected $signature = 'test:command';

            public function __construct()
            {
                $this->lockKey = 'test-command';
                parent::__construct();
            }

            public function handle(): int
            {
                return self::SUCCESS;
            }
        };

        $input = new ArrayInput(
            $options,
            new InputDefinition([
                new InputOption('skip-global-lock', null, InputOption::VALUE_NONE),
                new InputOption('skip-command-lock', null, InputOption::VALUE_NONE),
                new InputOption('remove-command-lock', null, InputOption::VALUE_NONE),
            ])
        );

        $reflection = new \ReflectionProperty($command, 'input');
        $reflection->setAccessible(true);
        $reflection->setValue($command, $input);

        return $command;
    }

    private function invokeProtectedMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($object, ...$args);
    }

    public function test_command_disabled_returns_false_when_no_disabled_file(): void
    {
        $command = $this->makeCommand();

        $this->assertFalse($this->invokeProtectedMethod($command, 'commandDisabled'));
    }

    public function test_command_disabled_returns_true_when_disabled_file_exists(): void
    {
        Storage::disk($this->disk)->put('test-command.disabled', '');

        $command = $this->makeCommand();

        $this->assertTrue($this->invokeProtectedMethod($command, 'commandDisabled'));
    }

    public function test_remove_command_lock_always_removes(): void
    {
        Storage::disk($this->disk)->put('test-command.lock', 'lock-data');

        $command = $this->makeCommand();

        $this->invokeProtectedMethod($command, 'removeCommandLock');

        $this->assertFalse(Storage::disk($this->disk)->exists('test-command.lock'));
    }
}
