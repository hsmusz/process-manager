<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Nova\Resources;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Panel;
use Movecloser\ProcessManager\Enum\ProcessStatus;
use Movecloser\ProcessManager\Models\Process as Model;
use Movecloser\ProcessManager\Nova\Actions\AbortProcess;
use Movecloser\ProcessManager\Nova\Actions\ReProcess;
use Movecloser\ProcessManager\Nova\HideAllActions;
use Movecloser\ProcessManager\Nova\Resource;
use Movecloser\ProcessManager\ProcessManagerFactory;

class Process extends Resource
{
    use HideAllActions;

    public static $group = 'Process Manager';
    public static string $model = Model::class;
    public static $search = [
        'processable_id',
        'meta',
    ];
    public static array $sort = [
        'id' => 'desc',
    ];

    public function actions(NovaRequest $request): array
    {
        return [
            ReProcess::make()->icon('arrow-path')
                ->onlyOnDetail(),

            AbortProcess::make()->icon('bolt-slash')
                ->onlyOnDetail(),
        ];
    }

    public function authorizedToView(Request $request): bool
    {
        return true;
    }

    /**
     * @throws \Laravel\Nova\Exceptions\HelperNotSupported
     */
    public function fields(NovaRequest $request): array
    {
        $logs = $this->resource->steps->pluck('logs')
            ->map(function ($log) {
                return json_decode($log ?? '', true); // Decode each log
            })->filter()->flatten(1)->toArray();

        return [
            Text::make('Process', Model::PROCESS)
                ->displayUsing(fn($type) => ProcessManagerFactory::map($type))
                ->sortable()
                ->readonly(),

            Text::make('Processable', Model::PROCESSABLE_ID)
                ->resolveUsing(fn() => $this->meta['display']['processable_id'] ?? $this->resource->processable_id)
                ->sortable()
                ->readonly()
                ->copyable(),

            ...$this->extraColumns(),

            Badge::make('status')->map([
                ProcessStatus::PENDING->value => 'info',
                ProcessStatus::IN_PROGRESS->value => 'info',
                ProcessStatus::INFO->value => 'warning',
                ProcessStatus::WARNING->value => 'warning',
                ProcessStatus::ABORTED->value => 'warning',
                ProcessStatus::RETRY->value => 'warning',
                ProcessStatus::SUCCESS->value => 'success',
                ProcessStatus::ERROR->value => 'danger',
                ProcessStatus::EXCEPTION->value => 'danger',
            ])
                ->filterable(),

            DateTime::make('Created', 'created_at')
                ->displayUsing(fn($date) => $date?->toDateTimeString())
                ->onlyOnIndex()
                ->readonly()
                ->sortable(),

            DateTime::make('Updated', 'updated_at')
                ->displayUsing(fn($date) => $date?->toDateTimeString())
                ->onlyOnIndex()
                ->readonly()
                ->sortable(),

            Number::make('Attempts', Model::ATTEMPTS),

            DateTime::make('Retry after', Model::RETRY_AFTER)
                ->displayUsing(fn($date) => $date?->toDateTimeString())
                ->hideFromIndex(),

            Text::make('Aborted by', 'meta')
                ->displayUsing(fn() => sprintf(
                    '%s, %s',
                    ProcessManagerFactory::resolveUser($this->meta['aborted']['user']),
                    Carbon::parse($this->meta['aborted']['timestamp'])->setTimezone(config('app.timezone'))->toDateTimeString()
                ))
                ->canSee(fn() => !empty($this->meta['aborted'] ?? []))
                ->hideFromIndex(),

            Text::make('ERROR', 'meta')
                ->displayUsing(fn() => $this->meta['exception'] ?? null)
                ->canSee(fn() => !empty($this->meta['exception'] ?? null))
                ->hideFromIndex(),

            HasMany::make('Steps', 'steps', ProcessStep::class),

            Panel::make(
                'Logs',
                array_map(
                    fn($val) => Code::make(
                        $val['action'] ?? '?',
                        fn() => str_replace("\\", '', json_encode($val['payload'] ?? [], JSON_PRETTY_PRINT))
                    ),
                    $logs
                )
            ),
        ];
    }

    public function filters(NovaRequest $request): array
    {
        return [
        ];
    }

    public function menu(Request $request): MenuItem
    {
        $counts = static::$model::whereNotIn('status', [
            ProcessStatus::ABORTED,
            ProcessStatus::SUCCESS,
        ])
            ->groupBy('status')
            ->select(['status', DB::raw('count(*) as count')])
            ->pluck('count', 'status');

        $type = 'info';
        if ($counts[ProcessStatus::IN_PROGRESS->value] ?? 0) {
            $type = 'success';
        }
        if ($counts[ProcessStatus::RETRY->value] ?? 0) {
            $type = 'warning';
        }
        if (($counts[ProcessStatus::ERROR->value] ?? 0) || ($counts[ProcessStatus::EXCEPTION->value] ?? 0)) {
            $type = 'danger';
        }

        return parent::menu($request)->withBadge((string) $counts->sum(), $type);
    }

    public function extraColumns(): array
    {
        $columns = [];
        foreach (ProcessManagerFactory::extraColumns() as $columnLabel => $key) {
            $columns[] = Text::make($columnLabel, fn() => Arr::get($this->meta, 'display.' . $key))->readonly()->copyable();
        }

        return $columns;
    }

}
