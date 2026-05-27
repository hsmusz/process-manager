<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Movecloser\ProcessManager\Contracts\ProcessesRepository;
use Movecloser\ProcessManager\Lockdown\CommandLock;
use Tests\TestCase;

class CommandLockIntegrationTest extends TestCase
{
    private string $disk = 'locks';
    private string $lockKey = 'process-manager-default';
    private string $lockFilename = 'process-manager-default.lock';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filesystems.disks.locks', [
            'driver' => 'local',
            'root' => storage_path('app/locks'),
            'throw' => false,
        ]);

        Storage::fake($this->disk);

        $this->mockProcessesRepository();
    }

    private function mockProcessesRepository(): void
    {
        $mock = $this->createMock(ProcessesRepository::class);
        $mock->method('hasTimeoutProcess')->willReturn(false);
        $mock->method('isRunning')->willReturn(false);
        $mock->method('nextAvailableProcess')->willReturn(null);

        $this->app->instance(ProcessesRepository::class, $mock);
    }

    public function test_work_command_creates_and_removes_lock(): void
    {
        $this->artisan('process-manager:work')
            ->assertSuccessful();

        $this->assertFalse(
            Storage::disk($this->disk)->exists($this->lockFilename),
            'Lock should have been removed after command finishes'
        );
    }

    public function test_work_command_respects_disabled_without_skip_global_lock(): void
    {
        Storage::disk($this->disk)->put('all-commands.disabled', '');

        $this->artisan('process-manager:work')
            ->assertExitCode(Command::INVALID);
    }

    public function test_work_command_skip_global_lock_bypasses_disabled(): void
    {
        Storage::disk($this->disk)->put('all-commands.disabled', '');

        $this->artisan('process-manager:work', ['--skip-global-lock' => true])
            ->assertSuccessful();
    }

    public function test_work_command_skip_lock_bc_alias_bypasses_disabled(): void
    {
        Storage::disk($this->disk)->put('all-commands.disabled', '');

        $this->artisan('process-manager:work', ['--skip-lock' => true])
            ->assertSuccessful();
    }

    public function test_work_command_respects_command_lock(): void
    {
        CommandLock::lock($this->lockKey);

        $this->artisan('process-manager:work')
            ->assertSuccessful();

        $this->assertTrue(
            Storage::disk($this->disk)->exists($this->lockFilename),
            'Lock should still exist when command exits early due to existing lock'
        );
    }

    public function test_work_command_skip_command_lock_bypasses_lock(): void
    {
        CommandLock::lock($this->lockKey);

        $this->artisan('process-manager:work', ['--skip-command-lock' => true])
            ->assertSuccessful();

        $this->assertFalse(
            Storage::disk($this->disk)->exists($this->lockFilename),
            'Lock should have been removed after command finishes with --skip-command-lock'
        );
    }

    public function test_work_command_remove_command_lock_clears_lock(): void
    {
        CommandLock::lock($this->lockKey);

        $this->artisan('process-manager:work', ['--remove-command-lock' => true])
            ->assertSuccessful();

        $this->assertFalse(
            Storage::disk($this->disk)->exists($this->lockFilename),
            'Lock should have been removed by --remove-command-lock'
        );
    }

    public function test_work_command_remove_lock_bc_alias_clears_lock(): void
    {
        CommandLock::lock($this->lockKey);

        $this->artisan('process-manager:work', ['--remove-lock' => true])
            ->assertSuccessful();

        $this->assertFalse(
            Storage::disk($this->disk)->exists($this->lockFilename),
            'Lock should have been removed by --remove-lock BC alias'
        );
    }

    public function test_work_command_skip_all_locks_bypasses_everything(): void
    {
        Storage::disk($this->disk)->put('all-commands.disabled', '');
        CommandLock::lock($this->lockKey);

        $this->artisan('process-manager:work', [
            '--skip-global-lock' => true,
            '--skip-command-lock' => true,
        ])->assertSuccessful();

        $this->assertFalse(
            Storage::disk($this->disk)->exists($this->lockFilename),
            'Lock should have been removed after command finishes with both skip flags'
        );
    }

    public function test_commands_status_shows_enabled_when_no_disabled(): void
    {
        $this->artisan('lock:commands:status')
            ->assertSuccessful();
    }

    public function test_commands_status_shows_disabled_when_disabled_file_exists(): void
    {
        Storage::disk($this->disk)->put('all-commands.disabled', '');

        $this->artisan('lock:commands:status')
            ->assertSuccessful();
    }

    public function test_command_disabled_by_key_file(): void
    {
        Storage::disk($this->disk)->put($this->lockKey . '.disabled', '');

        $this->artisan('process-manager:work')
            ->assertExitCode(Command::INVALID);

        $this->artisan('process-manager:work', ['--skip-global-lock' => true])
            ->assertSuccessful();
    }
}
