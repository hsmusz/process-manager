<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Nova\Resources;

use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Movecloser\ProcessManager\Nova\HideAllActions;
use Movecloser\ProcessManager\Nova\Resource;

class ProcessStep extends Resource
{
    use HideAllActions;

    public static $displayInNavigation = false;
    public static string $model = \Movecloser\ProcessManager\Models\ProcessStep::class;
    public static $perPageViaRelationship = 100;
    public static $search = ['id'];
    public static $title = 'id';

    public function fields(NovaRequest $request): array
    {
        return [
            DateTime::make('Started', 'created_at')
                ->displayUsing(fn($date) => $date?->toDateTimeString()),

            Text::make('step'),

            Text::make('status'),

            Text::make('message'),

            Text::make('details'),

        ];
    }
}
