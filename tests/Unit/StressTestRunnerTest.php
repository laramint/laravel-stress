<?php

declare(strict_types=1);

namespace LaraMint\LaravelStress\Tests\Unit;

use InvalidArgumentException;
use LaraMint\LaravelStress\StressTestRunner;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class StressTestRunnerTest extends TestCase
{
    /** @param array<mixed> $args */
    private function callPrivate(StressTestRunner $runner, string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod(StressTestRunner::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($runner, ...$args);
    }

    // ── Validation: count ─────────────────────────────────────────────────────

    public function test_throws_when_count_is_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('count must be between 1 and 200');

        (new StressTestRunner)->run(['count' => 0, 'concurrency' => 1, 'method' => 'GET', 'url' => 'http://x']);
    }

    public function test_throws_when_count_is_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('count must be between 1 and 200');

        (new StressTestRunner)->run(['count' => -5, 'concurrency' => 1, 'method' => 'GET', 'url' => 'http://x']);
    }

    public function test_throws_when_count_exceeds_200(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('count must be between 1 and 200');

        (new StressTestRunner)->run(['count' => 201, 'concurrency' => 1, 'method' => 'GET', 'url' => 'http://x']);
    }

    public function test_accepts_count_boundary_value_1(): void
    {
        try {
            (new StressTestRunner)->run(['count' => 1, 'concurrency' => 1, 'method' => 'GET', 'url' => 'http://0.0.0.0:1']);
        } catch (InvalidArgumentException $e) {
            $this->fail('Should not have thrown InvalidArgumentException: ' . $e->getMessage());
        } catch (\Throwable) {
            // Network error expected — validation passed
        }
        $this->assertTrue(true);
    }

    public function test_accepts_count_boundary_value_200(): void
    {
        try {
            (new StressTestRunner)->run(['count' => 200, 'concurrency' => 1, 'method' => 'GET', 'url' => 'http://0.0.0.0:1']);
        } catch (InvalidArgumentException $e) {
            $this->fail('Should not have thrown InvalidArgumentException: ' . $e->getMessage());
        } catch (\Throwable) {
            // Network error expected — validation passed
        }
        $this->assertTrue(true);
    }

    // ── Validation: concurrency ───────────────────────────────────────────────

    public function test_throws_when_concurrency_is_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('concurrency must be between 1 and 20');

        (new StressTestRunner)->run(['count' => 1, 'concurrency' => 0, 'method' => 'GET', 'url' => 'http://x']);
    }

    public function test_throws_when_concurrency_is_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('concurrency must be between 1 and 20');

        (new StressTestRunner)->run(['count' => 1, 'concurrency' => -1, 'method' => 'GET', 'url' => 'http://x']);
    }

    public function test_throws_when_concurrency_exceeds_20(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('concurrency must be between 1 and 20');

        (new StressTestRunner)->run(['count' => 1, 'concurrency' => 21, 'method' => 'GET', 'url' => 'http://x']);
    }

    public function test_accepts_concurrency_boundary_value_1(): void
    {
        try {
            (new StressTestRunner)->run(['count' => 1, 'concurrency' => 1, 'method' => 'GET', 'url' => 'http://0.0.0.0:1']);
        } catch (InvalidArgumentException $e) {
            $this->fail('Should not have thrown InvalidArgumentException: ' . $e->getMessage());
        } catch (\Throwable) {
            // Network error expected — validation passed
        }
        $this->assertTrue(true);
    }

    public function test_accepts_concurrency_boundary_value_20(): void
    {
        try {
            (new StressTestRunner)->run(['count' => 1, 'concurrency' => 20, 'method' => 'GET', 'url' => 'http://0.0.0.0:1']);
        } catch (InvalidArgumentException $e) {
            $this->fail('Should not have thrown InvalidArgumentException: ' . $e->getMessage());
        } catch (\Throwable) {
            // Network error expected — validation passed
        }
        $this->assertTrue(true);
    }

    // ── computeStats: empty results ───────────────────────────────────────────

    public function test_returns_zero_filled_structure_when_no_results(): void
    {
        $stats = $this->callPrivate(new StressTestRunner, 'computeStats', [[], 100.0]);

        $this->assertSame(0, $stats['total']);
        $this->assertSame(0, $stats['succeeded']);
        $this->assertSame(0, $stats['failed']);
        $this->assertSame(0.0, $stats['successRate']);
        $this->assertSame(0.0, $stats['errorRate']);
        $this->assertSame(0.0, $stats['throughput']);
        $this->assertSame([], $stats['statusDistribution']);
        $this->assertSame([], $stats['errors']);
        $this->assertSame(0.0, $stats['timing']['min']);
        $this->assertSame(0.0, $stats['timing']['max']);
        $this->assertSame(0.0, $stats['timing']['avg']);
        $this->assertSame(0.0, $stats['timing']['p50']);
        $this->assertSame(0.0, $stats['timing']['p95']);
        $this->assertSame(0.0, $stats['timing']['p99']);
    }

    // ── computeStats: success / failure counting ──────────────────────────────

    public function test_counts_2xx_responses_as_succeeded(): void
    {
        $results = [
            ['status' => 200, 'ms' => 50.0],
            ['status' => 201, 'ms' => 60.0],
            ['status' => 204, 'ms' => 40.0],
        ];

        $stats = $this->callPrivate(new StressTestRunner, 'computeStats', [$results, 300.0]);

        $this->assertSame(3, $stats['total']);
        $this->assertSame(3, $stats['succeeded']);
        $this->assertSame(0, $stats['failed']);
        $this->assertSame(100.0, $stats['successRate']);
        $this->assertSame(0.0, $stats['errorRate']);
    }

    public function test_counts_non_2xx_responses_as_failed(): void
    {
        $results = [
            ['status' => 200, 'ms' => 50.0],
            ['status' => 404, 'ms' => 30.0],
            ['status' => 500, 'ms' => 70.0],
            ['status' => 0, 'ms' => 80.0, 'error' => 'Connection refused'],
        ];

        $stats = $this->callPrivate(new StressTestRunner, 'computeStats', [$results, 500.0]);

        $this->assertSame(4, $stats['total']);
        $this->assertSame(1, $stats['succeeded']);
        $this->assertSame(3, $stats['failed']);
        $this->assertSame(25.0, $stats['successRate']);
        $this->assertSame(75.0, $stats['errorRate']);
    }

    public function test_treats_status_300_as_failed(): void
    {
        $results = [
            ['status' => 200, 'ms' => 10.0],
            ['status' => 300, 'ms' => 20.0],
        ];

        $stats = $this->callPrivate(new StressTestRunner, 'computeStats', [$results, 100.0]);

        $this->assertSame(1, $stats['succeeded']);
        $this->assertSame(1, $stats['failed']);
    }

    // ── computeStats: status distribution ────────────────────────────────────

    public function test_builds_a_status_distribution_map(): void
    {
        $results = [
            ['status' => 200, 'ms' => 10.0],
            ['status' => 200, 'ms' => 20.0],
            ['status' => 500, 'ms' => 30.0],
        ];

        $stats = $this->callPrivate(new StressTestRunner, 'computeStats', [$results, 200.0]);

        $this->assertSame(['200' => 2, '500' => 1], $stats['statusDistribution']);
    }

    // ── computeStats: errors ──────────────────────────────────────────────────

    public function test_collects_error_messages_from_status_0_results(): void
    {
        $results = [
            ['status' => 0, 'ms' => 10.0, 'error' => 'Connection refused'],
            ['status' => 0, 'ms' => 20.0, 'error' => 'Timeout'],
        ];

        $stats = $this->callPrivate(new StressTestRunner, 'computeStats', [$results, 100.0]);

        $this->assertContains('Connection refused', $stats['errors']);
        $this->assertContains('Timeout', $stats['errors']);
    }

    public function test_deduplicates_identical_errors(): void
    {
        $results = array_fill(0, 10, ['status' => 0, 'ms' => 5.0, 'error' => 'Connection refused']);

        $stats = $this->callPrivate(new StressTestRunner, 'computeStats', [$results, 100.0]);

        $this->assertCount(1, $stats['errors']);
        $this->assertSame('Connection refused', $stats['errors'][0]);
    }

    public function test_caps_errors_at_five_unique_messages(): void
    {
        $results = array_map(
            fn (int $i) => ['status' => 0, 'ms' => 5.0, 'error' => "Error #{$i}"],
            range(1, 10)
        );

        $stats = $this->callPrivate(new StressTestRunner, 'computeStats', [$results, 100.0]);

        $this->assertCount(5, $stats['errors']);
    }

    public function test_ignores_non_zero_status_results_for_errors_array(): void
    {
        $results = [['status' => 500, 'ms' => 10.0]];

        $stats = $this->callPrivate(new StressTestRunner, 'computeStats', [$results, 50.0]);

        $this->assertSame([], $stats['errors']);
    }

    // ── computeStats: timing ──────────────────────────────────────────────────

    public function test_calculates_min_max_avg_correctly(): void
    {
        $results = [
            ['status' => 200, 'ms' => 10.0],
            ['status' => 200, 'ms' => 20.0],
            ['status' => 200, 'ms' => 30.0],
        ];

        $stats = $this->callPrivate(new StressTestRunner, 'computeStats', [$results, 300.0]);

        $this->assertSame(10.0, $stats['timing']['min']);
        $this->assertSame(30.0, $stats['timing']['max']);
        $this->assertSame(20.0, $stats['timing']['avg']);
    }

    public function test_calculates_p50_as_the_median(): void
    {
        $results = array_map(
            fn (int $ms) => ['status' => 200, 'ms' => (float) $ms],
            [10, 20, 30, 40, 50, 60, 70, 80, 90, 100]
        );

        $stats = $this->callPrivate(new StressTestRunner, 'computeStats', [$results, 1000.0]);

        // p50 = index floor(9 * 0.50) = 4 → 50ms (0-indexed sorted array)
        $this->assertSame(50.0, $stats['timing']['p50']);
    }

    public function test_returns_rounded_timing_values(): void
    {
        $results = [
            ['status' => 200, 'ms' => 10.123456],
            ['status' => 200, 'ms' => 20.654321],
            ['status' => 200, 'ms' => 30.111111],
        ];

        $stats = $this->callPrivate(new StressTestRunner, 'computeStats', [$results, 300.0]);

        $this->assertSame(round((10.123456 + 20.654321 + 30.111111) / 3, 2), $stats['timing']['avg']);
    }

    // ── computeStats: throughput ──────────────────────────────────────────────

    public function test_calculates_requests_per_second_from_wall_time(): void
    {
        $results = array_fill(0, 10, ['status' => 200, 'ms' => 50.0]);

        // 10 requests in 1000 ms = 10 req/s
        $stats = $this->callPrivate(new StressTestRunner, 'computeStats', [$results, 1000.0]);

        $this->assertSame(10.0, $stats['throughput']);
    }

    public function test_returns_zero_throughput_when_wall_time_is_zero(): void
    {
        $stats = $this->callPrivate(new StressTestRunner, 'computeStats', [[['status' => 200, 'ms' => 5.0]], 0.0]);

        $this->assertSame(0.0, $stats['throughput']);
    }

    // ── computeStats: wallTimeMs ──────────────────────────────────────────────

    public function test_includes_rounded_wall_time_in_the_result(): void
    {
        $stats = $this->callPrivate(new StressTestRunner, 'computeStats', [[['status' => 200, 'ms' => 5.0]], 123.456789]);

        $this->assertSame(round(123.456789, 2), $stats['wallTimeMs']);
    }
}
