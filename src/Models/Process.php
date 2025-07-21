<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Movecloser\ProcessManager\Enum\ProcessStatus;

class Process extends Model
{
    public const string META = 'meta';
    public const string STATUS = 'status';
    public const string TYPE = 'type';
    public const string VERSION = 'version';
    public const string ATTEMPTS = 'attempts';
    public const string RETRY_AFTER = 'retry_after';

    protected $casts = [
        self::META => 'array',
        self::STATUS => ProcessStatus::class,
        self::RETRY_AFTER => 'datetime',
    ];

    protected $fillable = [
        self::META,
        self::STATUS,
        self::TYPE,
        self::VERSION,
        self::ATTEMPTS,
        self::RETRY_AFTER,
    ];

    protected $table = 'processes';

    protected $with = ['steps'];

    public function canBeAborted(): bool
    {
        return in_array($this->status, [
            ProcessStatus::ERROR,
            ProcessStatus::INFO,
            ProcessStatus::WARNING,
            ProcessStatus::RETRY,
            ProcessStatus::EXCEPTION,
        ]);
    }

    public function canBeRestarted(): bool
    {
        return in_array($this->status, [ProcessStatus::ERROR, ProcessStatus::RETRY, ProcessStatus::EXCEPTION]);
    }

    public function hasFinished(): bool
    {
        return ProcessStatus::SUCCESS === $this->status;
    }

    public function incrementAttempts(): void
    {
        $this->attempts++;
        $this->save();
    }

    public function restart(): void
    {
        $this->status = ProcessStatus::RETRY;
        $this->retry_after = Carbon::now();
        $this->save();
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProcessStep::class);
    }
}
