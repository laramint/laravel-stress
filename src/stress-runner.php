<?php

// Standalone subprocess script invoked by StressTestRunner.
//
// Usage:  php stress-runner.php /path/to/autoload.php /tmp/config.json /tmp/result.json
// The script reads config from the config file, runs the Guzzle pool, and
// writes the result to the result file using an atomic rename so the poll
// endpoint never reads a partial write.

declare(strict_types=1);

$autoload = $argv[1] ?? null;
$configFile = $argv[2] ?? null;
$resultFile = $argv[3] ?? null;

if (! $autoload || ! file_exists($autoload)) {
    exit(1);
}
if (! $configFile || ! file_exists($configFile)) {
    exit(1);
}
if (! $resultFile) {
    exit(1);
}

require $autoload;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

$config = json_decode((string) file_get_contents($configFile), true);
@unlink($configFile);

if (! is_array($config)) {
    exit(1);
}

$count = (int) ($config['count'] ?? 1);
$concurrency = (int) ($config['concurrency'] ?? 1);
$timeout = (float) ($config['timeout'] ?? 10.0);
$method = strtoupper((string) ($config['method'] ?? 'GET'));
$url = (string) ($config['url'] ?? '');
$headers = (array) ($config['headers'] ?? []);
$body = $config['body'] ?? null;

$client = new Client(['timeout' => $timeout, 'http_errors' => false]);
$results = [];
$wallStart = hrtime(true);

$requests = function () use ($count, $method, $url, $headers, $body) {
    for ($i = 0; $i < $count; $i++) {
        $start = hrtime(true);
        yield $start => new Request($method, $url, $headers, $body ?: null);
    }
};

$pool = new Pool($client, $requests(), [
    'concurrency' => $concurrency,
    'fulfilled' => function (ResponseInterface $response, mixed $index) use (&$results) {
        $results[] = [
            'status' => $response->getStatusCode(),
            'ms' => (hrtime(true) - $index) / 1e6,
        ];
    },
    'rejected' => function (mixed $reason, mixed $index) use (&$results) {
        $results[] = [
            'status' => 0,
            'ms' => (hrtime(true) - $index) / 1e6,
            'error' => $reason instanceof Throwable ? $reason->getMessage() : (string) $reason,
        ];
    },
]);

$pool->promise()->wait();

$wallMs = (hrtime(true) - $wallStart) / 1e6;
$total = count($results);

if ($total === 0) {
    $payload = json_encode([
        'status' => 'done',
        'result' => [
            'total' => 0, 'succeeded' => 0, 'failed' => 0,
            'successRate' => 0.0, 'errorRate' => 0.0, 'throughput' => 0.0,
            'timing' => ['min' => 0.0, 'max' => 0.0, 'avg' => 0.0, 'p50' => 0.0, 'p95' => 0.0, 'p99' => 0.0],
            'statusDistribution' => [], 'errors' => [], 'wallTimeMs' => round($wallMs, 2),
        ],
    ]);
    atomicWrite($resultFile, (string) $payload);
    exit(0);
}

$ms = array_column($results, 'ms');
sort($ms);

$succeeded = count(array_filter($results, fn ($r) => $r['status'] >= 200 && $r['status'] < 300));
$failed = $total - $succeeded;
$statusDist = [];
$errors = [];

foreach ($results as $r) {
    $key = (string) $r['status'];
    $statusDist[$key] = ($statusDist[$key] ?? 0) + 1;
    if ($r['status'] === 0 && isset($r['error'])) {
        $errors[] = $r['error'];
    }
}

$n = count($ms);
$p = fn (float $pct) => round($ms[(int) floor(($n - 1) * $pct)], 2);
$throughput = $wallMs > 0 ? round($total / ($wallMs / 1000), 2) : 0.0;

$payload = json_encode([
    'status' => 'done',
    'result' => [
        'total' => $total,
        'succeeded' => $succeeded,
        'failed' => $failed,
        'successRate' => round($succeeded / $total * 100, 2),
        'errorRate' => round($failed / $total * 100, 2),
        'throughput' => $throughput,
        'timing' => [
            'min' => round($ms[0], 2),
            'max' => round($ms[$n - 1], 2),
            'avg' => round(array_sum($ms) / $n, 2),
            'p50' => $p(0.50),
            'p95' => $p(0.95),
            'p99' => $p(0.99),
        ],
        'statusDistribution' => $statusDist,
        'errors' => array_values(array_slice(array_unique($errors), 0, 5)),
        'wallTimeMs' => round($wallMs, 2),
    ],
]);

atomicWrite($resultFile, (string) $payload);

// ── Helpers ───────────────────────────────────────────────────────────────────

function atomicWrite(string $path, string $content): void
{
    $tmp = $path . '.tmp';
    file_put_contents($tmp, $content);
    rename($tmp, $path);
}
