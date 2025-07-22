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
