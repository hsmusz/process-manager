<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Nova\Actions;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Movecloser\ProcessManager\Enum\ProcessStatus;

class AbortProcess extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public function handle(ActionFields $fields, Collection $models): ActionResponse|Action
    {
        if (count($models) > 1 || $models->isEmpty()) {
            return Action::danger('Please select only one process to abort');
        }

        /** @var \Movecloser\ProcessManager\Models\Process $process */
        $process = $models->first();
        if (!$process->canBeAborted()) {
            return Action::danger(
                sprintf('Invalid process status. Status %s cannot be aborted', $process->status->value)
            );
        }

        $process->status = ProcessStatus::ABORTED;
        $process->meta = array_merge($process->meta, [
            'aborted' => [
                'timestamp' => Carbon::now(),
                'user' => auth()->id(),
                'ip' => request()->ip(),
            ],
        ]);
        $process->save();

        return Action::message('Process aborted!');
    }
}
