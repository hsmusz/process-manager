<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Movecloser\ProcessManager\Contracts\ProcessesRepository;
use Movecloser\ProcessManager\Enum\ProcessStatus;
use Movecloser\ProcessManager\Exceptions\ProcessException;
use Movecloser\ProcessManager\Lockdown\CommandLock;
use Movecloser\ProcessManager\Models\Process;
use Movecloser\ProcessManager\Processes\AbstractProcess;
use Movecloser\ProcessManager\ProcessManagerFactory;
use Tests\Fixtures\FakeProcess;
use Tests\TestCase;

class ProcessQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('logging.channels.process-manager', config('logging.channels.null'));
        config()->set('filesystems.disks.locks', [
            'driver' => 'local',
            'root' => storage_path('app/locks'),
            'throw' => false,
        ]);

        Storage::fake('locks');

        if ('FAKE' !== ProcessManagerFactory::map(FakeProcess::class)) {
            ProcessManagerFactory::registerProcesses([FakeProcess::class => 'FAKE']);
        }

        FakeProcess::$finished = 0;
        FakeProcess::$onStep = null;

        $this->travelTo(now()->setTime(12, 0));
    }

    public function test_failed_finish_step_is_retried_instead_of_ending_as_info(): void
    {
        $process = $this->createProcess(ProcessStatus::RETRY, [
            AbstractProcess::START => ProcessStatus::SUCCESS,
            FakeProcess::STEP => ProcessStatus::SUCCESS,
            AbstractProcess::FINISH => ProcessStatus::RETRY,
        ]);

        ProcessManagerFactory::make($process, 'default')->handle();

        $this->assertSame(ProcessStatus::SUCCESS, $process->fresh()->status);
        $this->assertSame(1, FakeProcess::$finished);
    }

    public function test_successfully_finished_process_is_not_run_again(): void
    {
        $process = $this->createProcess(ProcessStatus::RETRY, [
            AbstractProcess::START => ProcessStatus::SUCCESS,
            FakeProcess::STEP => ProcessStatus::SUCCESS,
            AbstractProcess::FINISH => ProcessStatus::SUCCESS,
        ]);

        try {
            ProcessManagerFactory::make($process, 'default')->handle();
            $this->fail('ProcessException expected');
        } catch (ProcessException) {
        }

        $this->assertSame(ProcessStatus::INFO, $process->fresh()->status);
        $this->assertSame(0, FakeProcess::$finished);
    }

    public function test_worker_refuses_process_from_another_channel(): void
    {
        $process = $this->createProcess(ProcessStatus::PENDING, [], 'tcpos');

        $this->artisan('process-manager:work', ['processId' => $process->id])
            ->expectsOutputToContain('belongs to channel [tcpos]')
            ->assertSuccessful();

        $this->assertSame(ProcessStatus::PENDING, $process->fresh()->status);
    }

    public function test_worker_stops_when_another_instance_runs_a_process_of_the_channel(): void
    {
        $this->createProcess(ProcessStatus::PENDING);
        $second = $this->createProcess(ProcessStatus::PENDING);
        FakeProcess::$onStep = fn() => $this->createProcess(ProcessStatus::IN_PROGRESS);

        $this->artisan('process-manager:work')->assertSuccessful();

        $this->assertSame(ProcessStatus::PENDING, $second->fresh()->status);
    }

    public function test_worker_stops_when_commands_get_disabled(): void
    {
        $this->createProcess(ProcessStatus::PENDING);
        $second = $this->createProcess(ProcessStatus::PENDING);
        FakeProcess::$onStep = static fn() => Storage::disk('locks')->put('all-commands.disabled', '');

        $this->artisan('process-manager:work')->assertSuccessful();

        $this->assertSame(ProcessStatus::PENDING, $second->fresh()->status);
    }

    public function test_timeout_restart_skips_fresh_in_progress_process(): void
    {
        $fresh = $this->createProcess(ProcessStatus::IN_PROGRESS);
        $stale = $this->createProcess(ProcessStatus::IN_PROGRESS);
        DB::table('processes')->where('id', $stale->id)->update(['updated_at' => now()->subHours(2)]);

        app(ProcessesRepository::class)->restartTimeoutProcess('default');

        $this->assertSame(ProcessStatus::IN_PROGRESS, $fresh->fresh()->status);
        $this->assertSame(ProcessStatus::RETRY, $stale->fresh()->status);
    }

    public function test_status_command_reports_every_channel(): void
    {
        config()->set('process-manager.channels', ['default' => 'Process Manager', 'tcpos' => 'TCPOS']);
        CommandLock::lock('process-manager-tcpos');

        $this->artisan('lock:commands:status')
            ->expectsOutputToContain('2. Process Manager: Idle')
            ->expectsOutputToContain('2. TCPOS: Working')
            ->assertSuccessful();
    }

    /**
     * @param array<string, \Movecloser\ProcessManager\Enum\ProcessStatus> $steps
     */
    private function createProcess(ProcessStatus $status, array $steps = [], string $channel = 'default'): Process
    {
        $process = Process::create([
            Process::PROCESS => FakeProcess::class,
            Process::PROCESSABLE_TYPE => 'fake',
            Process::PROCESSABLE_ID => '1',
            Process::STATUS => $status,
            Process::VERSION => FakeProcess::$version,
            Process::CHANNEL => $channel,
            Process::META => [],
            Process::RETRY_AFTER => now()->subMinute(),
        ]);

        foreach ($steps as $step => $stepStatus) {
            $process->steps()->forceCreate([
                'step' => $step,
                'status' => $stepStatus,
                'message' => '',
                'details' => '[]',
            ]);
        }

        return $process;
    }
}
