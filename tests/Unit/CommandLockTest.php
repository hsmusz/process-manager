<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Movecloser\ProcessManager\Lockdown\CommandLock;
use Tests\TestCase;

class CommandLockTest extends TestCase
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

    public function test_lock_creates_lock_file(): void
    {
        CommandLock::lock('test-command');

        $this->assertTrue(Storage::disk($this->disk)->exists('test-command.lock'));
    }

    public function test_is_locked_returns_true_when_lock_exists(): void
    {
        CommandLock::lock('test-command');

        $this->assertTrue(CommandLock::isLocked('test-command'));
    }

    public function test_is_locked_returns_false_when_no_lock(): void
    {
        $this->assertFalse(CommandLock::isLocked('test-command'));
    }

    public function test_remove_lock_deletes_lock_file(): void
    {
        CommandLock::lock('test-command');
        CommandLock::removeLock('test-command');

        $this->assertFalse(Storage::disk($this->disk)->exists('test-command.lock'));
    }

    public function test_remove_lock_deletes_notification_file(): void
    {
        Storage::disk($this->disk)->put('test-command.lock.notified', Carbon::now()->toDateTimeString());
        CommandLock::removeLock('test-command');

        $this->assertFalse(Storage::disk($this->disk)->exists('test-command.lock.notified'));
    }

    public function test_has_error_returns_true_when_error_file_exists(): void
    {
        Storage::disk($this->disk)->put('test-command.error', '[2024-01-01] error message');

        $this->assertTrue(CommandLock::hasError('test-command'));
    }

    public function test_has_error_returns_false_when_no_error_file(): void
    {
        $this->assertFalse(CommandLock::hasError('test-command'));
    }

    public function test_get_error_returns_error_content(): void
    {
        Storage::disk($this->disk)->put('test-command.error', '[2024-01-01] error message');

        $this->assertStringContainsString('error message', CommandLock::getError('test-command'));
    }

    public function test_is_outdated_lock_returns_false_for_fresh_lock(): void
    {
        config()->set('process-manager.softlock_time', 30);
        CommandLock::lock('test-command');

        $this->assertFalse(CommandLock::isOutdatedLock('test-command'));
    }

    public function test_disabled_methods_no_longer_exist(): void
    {
        $this->assertFalse(method_exists(CommandLock::class, 'allCommandsDisabled'));
        $this->assertFalse(method_exists(CommandLock::class, 'commandDisabled'));
    }
}
