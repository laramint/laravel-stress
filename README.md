# laravel-stress

Fire-and-forget HTTP stress testing engine for Laravel development.

Spawns Guzzle request pools in a **background subprocess** so they never deadlock against PHP's single-threaded built-in server (`php artisan serve`). Works transparently with multi-threaded servers (Nginx, Apache, Herd, Valet) via an in-process fallback.

## Installation

```bash
composer require laramint/laravel-stress
```

## Usage

```php
use LaraMint\LaravelStress\StressTestRunner;

$runner = new StressTestRunner;

// Background (non-blocking) — returns a job ID to poll
$jobId = $runner->startBackground([
    'method'      => 'GET',
    'url'         => 'http://127.0.0.1:8000/api/users',
    'count'       => 50,
    'concurrency' => 5,
    'timeout'     => 10,
    'headers'     => ['Authorization' => 'Bearer token'],
    'body'        => null,
]);

// Poll the result file written by the subprocess
$resultFile = sys_get_temp_dir() . '/lb_st_res_' . $jobId . '.json';
// { "status": "running" }  →  keep polling
// { "status": "done", "result": { ... } }  →  done

// Synchronous (blocking) — works with multi-threaded servers
$result = $runner->run([...]);
```

### Result shape

```json
{
  "total": 50,
  "succeeded": 48,
  "failed": 2,
  "successRate": 96.0,
  "errorRate": 4.0,
  "throughput": 12.5,
  "timing": { "min": 42.1, "avg": 95.3, "p50": 88.0, "p95": 210.4, "p99": 380.1, "max": 420.7 },
  "statusDistribution": { "200": 48, "500": 2 },
  "errors": [],
  "wallTimeMs": 4000
}
```

## License

MIT

