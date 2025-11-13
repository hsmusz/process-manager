<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Nova\Metrics;

use Illuminate\Support\Carbon;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;
use Movecloser\ProcessManager\Models\Process;

class MaxProcessAttempts extends Trend
{
    public function cacheFor(): Carbon
    {
        return now()->addMinutes(5);
    }

    public function calculate(NovaRequest $request): TrendResult
    {
        return $this->maxByDays($request, Process::class, Process::ATTEMPTS)
            ->showLatestValue();
    }

    public function name(): string
    {
        return 'Max process attempts';
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges(): array
    {
        return [
            7 => '7 Days',
            30 => '30 Days',
            60 => '60 Days',
            90 => '90 Days',
        ];
    }
}
