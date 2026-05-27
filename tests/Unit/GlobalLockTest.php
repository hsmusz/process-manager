<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\Storage;
use Movecloser\ProcessManager\Lockdown\GlobalLock;
use Tests\TestCase;

class GlobalLockTest extends TestCase
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

    public function test_all_disabled_returns_true_when_all_commands_disabled_file_exists(): void
    {
        Storage::disk($this->disk)->put('all-commands.disabled', '');

        $this->assertTrue(GlobalLock::allDisabled());
    }

    public function test_all_disabled_returns_false_when_no_disabled_file(): void
    {
        $this->assertFalse(GlobalLock::allDisabled());
    }

    public function test_is_disabled_returns_true_when_command_disabled_file_exists(): void
    {
        Storage::disk($this->disk)->put('test-command.disabled', '');

        $this->assertTrue(GlobalLock::isDisabled('test-command'));
    }

    public function test_is_disabled_returns_false_when_no_command_disabled_file(): void
    {
        $this->assertFalse(GlobalLock::isDisabled('test-command'));
    }

    public function test_is_disabled_returns_true_when_all_commands_are_disabled(): void
    {
        Storage::disk($this->disk)->put('all-commands.disabled', '');

        $this->assertTrue(GlobalLock::isDisabled('any-command'));
    }

    public function test_is_disabled_with_kebab_case_key(): void
    {
        Storage::disk($this->disk)->put('process-manager.disabled', '');

        $this->assertTrue(GlobalLock::isDisabled('process-manager'));
    }

    public function test_is_disabled_respects_exact_key_match(): void
    {
        Storage::disk($this->disk)->put('process-manager.disabled', '');

        $this->assertFalse(GlobalLock::isDisabled('other-command'));
    }

    public function test_all_disabled_after_deleting_file(): void
    {
        Storage::disk($this->disk)->put('all-commands.disabled', '');
        $this->assertTrue(GlobalLock::allDisabled());

        Storage::disk($this->disk)->delete('all-commands.disabled');
        $this->assertFalse(GlobalLock::allDisabled());
    }
}
