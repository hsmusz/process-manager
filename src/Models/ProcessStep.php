<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Models;

use Movecloser\ProcessManager\Enum\ProcessStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessStep extends Model
{
    public const string STATUS = 'status';
    public const string STEP = 'step';
    public const string MESSAGE = 'message';
    public const string DETAILS = 'details';
    public const string LOGS = 'logs';

    protected $casts = [
        self::LOGS => 'array',
        self::STATUS => ProcessStatus::class,
    ];

    protected $table = 'process_steps';

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }
}
