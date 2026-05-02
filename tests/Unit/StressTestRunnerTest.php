<?php

declare(strict_types=1);

use LaraMint\LaravelStress\StressTestRunner;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Call a private method on StressTestRunner via reflection.
 *
 * @param  array<mixed>  $args
 */
function callPrivate(StressTestRunner $runner, string $method, array $args = []): mixed
{
    $ref = new ReflectionMethod(StressTestRunner::class, $method);
    $ref->setAccessible(true);

    return $ref->invoke($runner, ...$args);
}

// ── Validation: count ─────────────────────────────────────────────────────────

describe('validation – count', function () {
    it('throws when count is zero', function () {
        $runner = new StressTestRunner;

        expect(fn () => $runner->run(['count' => 0, 'concurrency' => 1, 'method' => 'GET', 'url' => 'http://x']))
            ->toThrow(InvalidArgumentException::class, 'count must be between 1 and 200');
    });

    it('throws when count is negative', function () {
        $runner = new StressTestRunner;

        expect(fn () => $runner->run(['count' => -5, 'concurrency' => 1, 'method' => 'GET', 'url' => 'http://x']))
            ->toThrow(InvalidArgumentException::class, 'count must be between 1 and 200');
    });

    it('throws when count exceeds 200', function () {
        $runner = new StressTestRunner;

        expect(fn () => $runner->run(['count' => 201, 'concurrency' => 1, 'method' => 'GET', 'url' => 'http://x']))
            ->toThrow(InvalidArgumentException::class, 'count must be between 1 and 200');
    });

    it('accepts count boundary value 1', function () {
        // Validation should pass; the request itself will fail against a fake URL — that's fine.
        $runner = new StressTestRunner;

        expect(fn () => $runner->run(['count' => 1, 'concurrency' => 1, 'method' => 'GET', 'url' => 'http://0.0.0.0:1']))
            ->not->toThrow(InvalidArgumentException::class);
    });

    it('accepts count boundary value 200', function () {
        $runner = new StressTestRunner;

        expect(fn () => $runner->run(['count' => 200, 'concurrency' => 1, 'method' => 'GET', 'url' => 'http://0.0.0.0:1']))
            ->not->toThrow(InvalidArgumentException::class);
    });
});

// ── Validation: concurrency ───────────────────────────────────────────────────

describe('validation – concurrency', function () {
    it('throws when concurrency is zero', function () {
        $runner = new StressTestRunner;

        expect(fn () => $runner->run(['count' => 1, 'concurrency' => 0, 'method' => 'GET', 'url' => 'http://x']))
            ->toThrow(InvalidArgumentException::class, 'concurrency must be between 1 and 20');
    });

    it('throws when concurrency is negative', function () {
        $runner = new StressTestRunner;

        expect(fn () => $runner->run(['count' => 1, 'concurrency' => -1, 'method' => 'GET', 'url' => 'http://x']))
            ->toThrow(InvalidArgumentException::class, 'concurrency must be between 1 and 20');
    });

    it('throws when concurrency exceeds 20', function () {
        $runner = new StressTestRunner;

        expect(fn () => $runner->run(['count' => 1, 'concurrency' => 21, 'method' => 'GET', 'url' => 'http://x']))
            ->toThrow(InvalidArgumentException::class, 'concurrency must be between 1 and 20');
    });

    it('accepts concurrency boundary value 1', function () {
        $runner = new StressTestRunner;

        expect(fn () => $runner->run(['count' => 1, 'concurrency' => 1, 'method' => 'GET', 'url' => 'http://0.0.0.0:1']))
            ->not->toThrow(InvalidArgumentException::class);
    });

    it('accepts concurrency boundary value 20', function () {
        $runner = new StressTestRunner;

        expect(fn () => $runner->run(['count' => 1, 'concurrency' => 20, 'method' => 'GET', 'url' => 'http://0.0.0.0:1']))
            ->not->toThrow(InvalidArgumentException::class);
    });
});

// ── computeStats: empty results ───────────────────────────────────────────────

describe('computeStats – empty results', function () {
    it('returns zero-filled structure when no results', function () {
        $runner = new StressTestRunner;
        $stats = callPrivate($runner, 'computeStats', [[], 100.0]);

        expect($stats['total'])->toBe(0)
            ->and($stats['succeeded'])->toBe(0)
            ->and($stats['failed'])->toBe(0)
            ->and($stats['successRate'])->toBe(0.0)
            ->and($stats['errorRate'])->toBe(0.0)
            ->and($stats['throughput'])->toBe(0.0)
            ->and($stats['statusDistribution'])->toBe([])
            ->and($stats['errors'])->toBe([])
            ->and($stats['timing']['min'])->toBe(0.0)
            ->and($stats['timing']['max'])->toBe(0.0)
            ->and($stats['timing']['avg'])->toBe(0.0)
            ->and($stats['timing']['p50'])->toBe(0.0)
            ->and($stats['timing']['p95'])->toBe(0.0)
            ->and($stats['timing']['p99'])->toBe(0.0);
    });
});

// ── computeStats: success / failure counting ──────────────────────────────────

describe('computeStats – success and failure counting', function () {
    it('counts 2xx responses as succeeded', function () {
        $runner = new StressTestRunner;
        $results = [
            ['status' => 200, 'ms' => 50.0],
            ['status' => 201, 'ms' => 60.0],
            ['status' => 204, 'ms' => 40.0],
        ];

        $stats = callPrivate($runner, 'computeStats', [$results, 300.0]);

        expect($stats['total'])->toBe(3)
            ->and($stats['succeeded'])->toBe(3)
            ->and($stats['failed'])->toBe(0)
            ->and($stats['successRate'])->toBe(100.0)
            ->and($stats['errorRate'])->toBe(0.0);
    });

    it('counts non-2xx responses as failed', function () {
        $runner = new StressTestRunner;
        $results = [
            ['status' => 200, 'ms' => 50.0],
            ['status' => 404, 'ms' => 30.0],
            ['status' => 500, 'ms' => 70.0],
            ['status' => 0, 'ms' => 80.0, 'error' => 'Connection refused'],
        ];

        $stats = callPrivate($runner, 'computeStats', [$results, 500.0]);

        expect($stats['total'])->toBe(4)
            ->and($stats['succeeded'])->toBe(1)
            ->and($stats['failed'])->toBe(3)
            ->and($stats['successRate'])->toBe(25.0)
            ->and($stats['errorRate'])->toBe(75.0);
    });

    it('treats status 300 as failed (not 2xx)', function () {
        $runner = new StressTestRunner;
        $results = [
            ['status' => 200, 'ms' => 10.0],
            ['status' => 300, 'ms' => 20.0],
        ];

        $stats = callPrivate($runner, 'computeStats', [$results, 100.0]);

        expect($stats['succeeded'])->toBe(1)
            ->and($stats['failed'])->toBe(1);
    });
});

// ── computeStats: status distribution ────────────────────────────────────────

describe('computeStats – status distribution', function () {
    it('builds a status distribution map', function () {
        $runner = new StressTestRunner;
        $results = [
            ['status' => 200, 'ms' => 10.0],
            ['status' => 200, 'ms' => 20.0],
            ['status' => 500, 'ms' => 30.0],
        ];

        $stats = callPrivate($runner, 'computeStats', [$results, 200.0]);

        expect($stats['statusDistribution'])->toBe(['200' => 2, '500' => 1]);
    });
});

// ── computeStats: errors ──────────────────────────────────────────────────────

describe('computeStats – errors', function () {
    it('collects error messages from failed (status 0) results', function () {
        $runner = new StressTestRunner;
        $results = [
            ['status' => 0, 'ms' => 10.0, 'error' => 'Connection refused'],
            ['status' => 0, 'ms' => 20.0, 'error' => 'Timeout'],
        ];

        $stats = callPrivate($runner, 'computeStats', [$results, 100.0]);

        expect($stats['errors'])->toContain('Connection refused')
            ->toContain('Timeout');
    });

    it('deduplicates identical errors', function () {
        $runner = new StressTestRunner;
        $results = array_fill(0, 10, ['status' => 0, 'ms' => 5.0, 'error' => 'Connection refused']);

        $stats = callPrivate($runner, 'computeStats', [$results, 100.0]);

        expect($stats['errors'])->toHaveCount(1)
            ->and($stats['errors'][0])->toBe('Connection refused');
    });

    it('caps errors at five unique messages', function () {
        $runner = new StressTestRunner;
        $results = array_map(
            fn (int $i) => ['status' => 0, 'ms' => 5.0, 'error' => "Error #{$i}"],
            range(1, 10)
        );

        $stats = callPrivate($runner, 'computeStats', [$results, 100.0]);

        expect($stats['errors'])->toHaveCount(5);
    });

    it('ignores non-zero status results for errors array', function () {
        $runner = new StressTestRunner;
        $results = [
            ['status' => 500, 'ms' => 10.0],
        ];

        $stats = callPrivate($runner, 'computeStats', [$results, 50.0]);

        expect($stats['errors'])->toBe([]);
    });
});

// ── computeStats: timing ──────────────────────────────────────────────────────

describe('computeStats – timing', function () {
    it('calculates min, max, avg correctly for a known dataset', function () {
        $runner = new StressTestRunner;
        $results = [
            ['status' => 200, 'ms' => 10.0],
            ['status' => 200, 'ms' => 20.0],
            ['status' => 200, 'ms' => 30.0],
        ];

        $stats = callPrivate($runner, 'computeStats', [$results, 300.0]);

        expect($stats['timing']['min'])->toBe(10.0)
            ->and($stats['timing']['max'])->toBe(30.0)
            ->and($stats['timing']['avg'])->toBe(20.0);
    });

    it('calculates p50 as the median', function () {
        $runner = new StressTestRunner;
        $results = array_map(
            fn (int $ms) => ['status' => 200, 'ms' => (float) $ms],
            [10, 20, 30, 40, 50, 60, 70, 80, 90, 100]
        );

        $stats = callPrivate($runner, 'computeStats', [$results, 1000.0]);

        // p50 = index floor(9 * 0.50) = 4 → 50ms (0-indexed sorted array)
        expect($stats['timing']['p50'])->toBe(50.0);
    });

    it('returns rounded values', function () {
        $runner = new StressTestRunner;
        $results = [
            ['status' => 200, 'ms' => 10.123456],
            ['status' => 200, 'ms' => 20.654321],
            ['status' => 200, 'ms' => 30.111111],
        ];

        $stats = callPrivate($runner, 'computeStats', [$results, 300.0]);

        expect($stats['timing']['avg'])->toBe(round((10.123456 + 20.654321 + 30.111111) / 3, 2));
    });
});

// ── computeStats: throughput ──────────────────────────────────────────────────

describe('computeStats – throughput', function () {
    it('calculates requests per second from wall time', function () {
        $runner = new StressTestRunner;
        $results = array_fill(0, 10, ['status' => 200, 'ms' => 50.0]);

        // 10 requests in 1000 ms = 10 req/s
        $stats = callPrivate($runner, 'computeStats', [$results, 1000.0]);

        expect($stats['throughput'])->toBe(10.0);
    });

    it('returns zero throughput when wall time is zero', function () {
        $runner = new StressTestRunner;
        $results = [['status' => 200, 'ms' => 5.0]];

        $stats = callPrivate($runner, 'computeStats', [$results, 0.0]);

        expect($stats['throughput'])->toBe(0.0);
    });
});

// ── computeStats: wallTimeMs ──────────────────────────────────────────────────

describe('computeStats – wallTimeMs', function () {
    it('includes rounded wall time in the result', function () {
        $runner = new StressTestRunner;
        $results = [['status' => 200, 'ms' => 5.0]];

        $stats = callPrivate($runner, 'computeStats', [$results, 123.456789]);

        expect($stats['wallTimeMs'])->toBe(round(123.456789, 2));
    });
});
