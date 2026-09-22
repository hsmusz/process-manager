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

    public function test_error_stores_message_as_single_plain_text_line(): void
    {
        CommandLock::lock('test-command');
        CommandLock::error('test-command', "Api Error: <html>\r\n<center><h1>502 Bad Gateway</h1></center>\r\n</html>");

        $stored = CommandLock::getError('test-command');

        $this->assertStringNotContainsString('<', $stored);
        $this->assertStringNotContainsString("\r", $stored);
        $this->assertStringEndsWith('Api Error: 502 Bad Gateway', $stored);
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

    public function test_failed_last_execution_returns_true_when_error_comes_from_last_run(): void
    {
        Storage::disk($this->disk)->put('test-command.lock.execution', '2024-01-01 10:00:00');
        Storage::disk($this->disk)->put('test-command.error', '[2024-01-01 10:00:05] boom');

        $this->assertTrue(CommandLock::failedLastExecution('test-command'));
    }

    public function test_failed_last_execution_returns_false_when_errors_are_older_than_last_run(): void
    {
        Storage::disk($this->disk)->put('test-command.lock.execution', '2024-01-01 10:00:00');
        Storage::disk($this->disk)->put('test-command.error', '[2024-01-01 09:00:05] boom');

        $this->assertFalse(CommandLock::failedLastExecution('test-command'));
    }

    public function test_error_log_splits_entries_by_last_execution(): void
    {
        Storage::disk($this->disk)->put('test-command.lock.execution', '2024-01-01 10:00:00');
        Storage::disk($this->disk)->put('test-command.error', implode(PHP_EOL, [
            '[2024-01-01 08:00:00] old one',
            '[2024-01-01 09:00:00] old two',
            '[2024-01-01 10:00:05] fresh one',
        ]));

        $log = CommandLock::errorLog('test-command');

        $this->assertSame(['[2024-01-01 10:00:05] fresh one'], $log['current']);
        $this->assertSame([
            '[2024-01-01 08:00:00] old one',
            '[2024-01-01 09:00:00] old two',
        ], $log['previous']);
    }

    public function test_error_log_treats_all_entries_as_current_without_execution_date(): void
    {
        Storage::disk($this->disk)->put('test-command.error', '[2024-01-01 08:00:00] boom');

        $log = CommandLock::errorLog('test-command');

        $this->assertSame(['[2024-01-01 08:00:00] boom'], $log['current']);
        $this->assertEmpty($log['previous']);
    }

    public function test_error_log_is_empty_without_error_file(): void
    {
        $log = CommandLock::errorLog('test-command');

        $this->assertEmpty($log['current']);
        $this->assertEmpty($log['previous']);
    }
}
