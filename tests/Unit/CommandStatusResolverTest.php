<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\Storage;
use Movecloser\ProcessManager\Lockdown\CommandStatusResolver;
use Tests\TestCase;

class CommandStatusResolverTest extends TestCase
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

    public function test_status_is_error_when_last_execution_failed(): void
    {
        Storage::disk($this->disk)->put('test-command.lock.execution', '2024-01-01 10:00:00');
        Storage::disk($this->disk)->put('test-command.error', '[2024-01-01 10:00:05] boom');

        $this->assertSame(
            CommandStatusResolver::COMMAND_STATUS_ERROR,
            CommandStatusResolver::checkCommandStatus('test-command')
        );
    }

    public function test_status_is_idle_when_only_older_executions_failed(): void
    {
        Storage::disk($this->disk)->put('test-command.lock.execution', '2024-01-01 10:00:00');
        Storage::disk($this->disk)->put('test-command.error', '[2024-01-01 09:00:05] boom');

        $this->assertSame(
            CommandStatusResolver::COMMAND_STATUS_IDLE,
            CommandStatusResolver::checkCommandStatus('test-command')
        );
    }

    public function test_disabled_wins_over_errors_from_last_execution(): void
    {
        Storage::disk($this->disk)->put('test-command.disabled', '');
        Storage::disk($this->disk)->put('test-command.lock.execution', '2024-01-01 10:00:00');
        Storage::disk($this->disk)->put('test-command.error', '[2024-01-01 10:00:05] boom');

        $this->assertSame(
            CommandStatusResolver::COMMAND_STATUS_DISABLED,
            CommandStatusResolver::checkCommandStatus('test-command')
        );
    }
}
