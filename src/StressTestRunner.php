<?php

declare(strict_types=1);

namespace LaraMint\LaravelStress;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

class StressTestRunner
{
    /**
     * stress-runner.php lives next to this class file so the path is stable
     * regardless of where Composer installs the package.
     */
    private const RUNNER_SCRIPT = __DIR__ . '/stress-runner.php';

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Start the stress test in a background process and return a job ID that
     * the caller can poll.  Returns null when background execution is not
     * available, in which case the caller should fall back to run().
     *
     * Background execution is the REQUIRED strategy when the target URL is the
     * same server handling this request (e.g. `php artisan serve`).  Without it
     * the server thread is blocked waiting for its own HTTP responses.
     *
     * @param  array<string, mixed>  $config
     */
    public function startBackground(array $config): ?string
    {
        $this->validate($config);

        if (! $this->backgroundAvailable()) {
            return null;
        }

        $jobId = uniqid('', true);
        $tmpDir = sys_get_temp_dir();
        $configFile = $tmpDir . DIRECTORY_SEPARATOR . 'lb_st_cfg_' . $jobId . '.json';
        $resultFile = $tmpDir . DIRECTORY_SEPARATOR . 'lb_st_res_' . $jobId . '.json';

        file_put_contents($configFile, (string) json_encode($config));
        // Write "running" so poll endpoint can respond before subprocess starts
        file_put_contents($resultFile, json_encode(['status' => 'running']));

        $autoload = base_path('vendor/autoload.php');

        $cmd = implode(' ', [
            escapeshellarg(PHP_BINARY),
            escapeshellarg(self::RUNNER_SCRIPT),
            escapeshellarg($autoload),
            escapeshellarg($configFile),
            escapeshellarg($resultFile),
        ]);

        $started = false;

        // Unix/macOS — preferred: exec() with & truly detaches the subprocess
        if (PHP_OS_FAMILY !== 'Windows' && function_exists('exec')) {
            exec($cmd . ' > /dev/null 2>&1 &');
            $started = true;
        }

        // Unix/macOS — fallback: proc_open shell wrapper exits immediately
        if (! $started && PHP_OS_FAMILY !== 'Windows' && function_exists('proc_open')) {
            $devNull = [['file', '/dev/null', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']];
            $proc = proc_open(['sh', '-c', $cmd . ' > /dev/null 2>&1 &'], $devNull, $pipes);
            if (is_resource($proc)) {
                proc_close($proc); // sh exits immediately after forking the child
                $started = true;
            }
        }

        // Windows
        if (! $started && PHP_OS_FAMILY === 'Windows' && function_exists('pclose') && function_exists('popen')) {
            pclose(popen('start /B "" ' . $cmd, 'r'));
            $started = true;
        }

        if (! $started) {
            @unlink($configFile);
            @unlink($resultFile);

            return null;
        }

        return $jobId;
    }

    /**
     * Synchronous fallback — runs the Guzzle pool in-process.
     * Works correctly with multi-threaded servers (Nginx, Apache, Herd, Valet).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function run(array $config): array
    {
        $this->validate($config);

        return $this->runInProcess($config);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    /** @param  array<string, mixed>  $config */
    private function validate(array $config): void
    {
        $count = (int) ($config['count'] ?? 0);
        $concurrency = (int) ($config['concurrency'] ?? 0);

        if ($count < 1 || $count > 200) {
            throw new \InvalidArgumentException('count must be between 1 and 200');
        }
        if ($concurrency < 1 || $concurrency > 20) {
            throw new \InvalidArgumentException('concurrency must be between 1 and 20');
        }
    }

    // ── Background availability ───────────────────────────────────────────────

    private function backgroundAvailable(): bool
    {
        // Under PHP-FPM (Herd, Valet, Nginx) the server runs multiple workers,
        // so the synchronous Guzzle pool won't deadlock — other workers handle
        // the stress requests.  PHP_BINARY in FPM context is the php-fpm binary
        // which cannot run standalone scripts, so skip background entirely.
        if (PHP_SAPI === 'fpm-fcgi') {
            return false;
        }

        if (! file_exists(base_path('vendor/autoload.php'))) {
            return false;
        }
        if (! file_exists(self::RUNNER_SCRIPT)) {
            return false;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return function_exists('exec') || function_exists('proc_open');
        }

        return function_exists('pclose') && function_exists('popen');
    }

    // ── In-process (synchronous) ──────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function runInProcess(array $config): array
    {
        $count = (int) $config['count'];
        $concurrency = (int) $config['concurrency'];
        $timeout = isset($config['timeout']) ? (float) $config['timeout'] : 10.0;
        $method = strtoupper($config['method']);
        $url = $config['url'];
        $headers = $config['headers'] ?? [];
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
                    'error' => $reason instanceof \Throwable ? $reason->getMessage() : (string) $reason,
                ];
            },
        ]);

        $pool->promise()->wait();

        return $this->computeStats($results, (hrtime(true) - $wallStart) / 1e6);
    }

    // ── Stats ─────────────────────────────────────────────────────────────────

    /**
     * @param  array<int, array{status: int, ms: float, error?: string}>  $results
     * @return array<string, mixed>
     */
    private function computeStats(array $results, float $wallMs): array
    {
        $total = count($results);
        if ($total === 0) {
            return [
                'total' => 0, 'succeeded' => 0, 'failed' => 0,
                'successRate' => 0.0, 'errorRate' => 0.0, 'throughput' => 0.0,
                'timing' => ['min' => 0.0, 'max' => 0.0, 'avg' => 0.0, 'p50' => 0.0, 'p95' => 0.0, 'p99' => 0.0],
                'statusDistribution' => [], 'errors' => [], 'wallTimeMs' => $wallMs,
            ];
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

        return [
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
        ];
    }
}
