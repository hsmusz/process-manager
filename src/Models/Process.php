<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Movecloser\ProcessManager\Enum\ProcessStatus;

class Process extends Model
{
    public const string ATTEMPTS = 'attempts';
    public const string MANAGER = 'manager';
    public const string META = 'meta';
    public const string PROCESSABLE_ID = 'processable_id';
    public const string PROCESSABLE_TYPE = 'processable_type';
    public const string RETRY_AFTER = 'retry_after';
    public const string STATUS = 'status';
    public const string VERSION = 'version';

    protected $casts = [
        self::META => 'array',
        self::STATUS => ProcessStatus::class,
        self::RETRY_AFTER => 'datetime',
    ];

    protected $fillable = [
        self::ATTEMPTS,
        self::MANAGER,
        self::META,
        self::PROCESSABLE_ID,
        self::PROCESSABLE_TYPE,
        self::RETRY_AFTER,
        self::STATUS,
        self::VERSION,
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
