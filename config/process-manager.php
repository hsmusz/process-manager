<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Lockdown configuration
    |--------------------------------------------------------------------------
    |
    | Notify on Process Manager lockdown - when retry amount has been reached,
    | or non-retriable exception has occurred.
    |
    | Array of ['email' => 'email@example.com', 'name' => 'John Doe']
    |
    */

    'notify_on_lockdown' => [

    ],

    /*
    |--------------------------------------------------------------------------
    | Soft lock configuration
    |--------------------------------------------------------------------------
    |
    | Notify on soft lock - when another instance of command has been run
    | before the previous instance has finished (within softlock_time in seconds).
    |
    | Array of ['email' => 'email@example.com', 'name' => 'John Doe']
    |
    */

    'softlock_time' => 30,
    'notify_on_soft_lock' => [

    ],

];