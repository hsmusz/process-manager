<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Lockdown\Models;

use Illuminate\Database\Eloquent\Model;
use Movecloser\ProcessManager\Lockdown\Enum\LockdownStatus;
use Movecloser\ProcessManager\Lockdown\Serialize;

class Lockdown extends Model
{
    protected $casts = [
        'data' => 'array',
        'status' => LockdownStatus::class,
        'exception' => Serialize::class,
    ];

    protected $fillable = [
        'command',
        'message',
        'data',
        'exception',
        'status',
    ];

    protected $table = 'aelia_lockdowns';
}
