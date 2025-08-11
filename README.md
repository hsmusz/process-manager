Laravel component to handle processses in microwervices

```php 
  # config/filesystems.php
  'disks' => [
    ...     
    // Command Locks
    'locks' => [
        'driver' => 'local',
        'root' => storage_path('app/locks'),
        'throw' => false,
    ],
  ],
  
  # config/logging.php
  'channels' => [
    ...     
    // Process Manager debug logs
    'process-manager' => [
        'driver' => 'single',
        'path' => storage_path('logs/process-manager.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'replace_placeholders' => true,
    ],
  ],
```

Example of Process Class

```php
class DefaultProcess extends AbstractProcess implements Process
{
    protected const array STEPS = [
        'step1' => 'handleTask',
        'step2' => \App\ProcessManager\Tasks\HandleTask2::class,
    ];

    public static int $version = 1;

    public function handleTask(): ProcessResult
    {
        return new ProcessResult('Task completed');
    }
}

```
