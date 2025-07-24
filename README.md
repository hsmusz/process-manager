Laravel component to handle processses in microwervices

```php 
  # config/filesystems.php
  disks' => [
    ...     
    // Command Locks
    'locks' => [
        'driver' => 'local',
        'root' => storage_path('app/locks'),
        'throw' => false,
    ],
  ],
```

Example of Process Class

```php
class DefaultProcess extends AbstractProcess implements Process
{
    protected const array STEPS = [
        'task' => 'handleTask',
        'task2' => \App\ProcessManager\Tasks\HandleTask2::class,
    ];

    public static int $version = 1;

    public function handleTask(): ProcessResult
    {
        return new ProcessResult('Task completed');
    }
}

```
