<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

class ReProcess extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public $name = 'Restart Process';

    public function handle(ActionFields $fields, Collection $models): ActionResponse|Action
    {
        if (count($models) > 1 || $models->isEmpty()) {
            return Action::danger('Please select only one process to re-process');
        }

        /** @var \Movecloser\ProcessManager\Models\Process $process */
        $process = $models->first();
        if ($process->hasFinished()) {
            return Action::danger('Process already finished');
        }

        if (!$process->canBeRestarted()) {
            return Action::danger('This process does not need restarting.');
        }

        $process->restart();

        return Action::message('Action executed successfully!');
    }
}
