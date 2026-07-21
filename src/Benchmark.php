<?php

declare(strict_types=1);

namespace peels\benchmark;

use InvalidArgumentException;

/**
 * Lightweight benchmarking helper for tracking execution time and memory usage.
 */
class Benchmark
{
    /**
     * Captured timestamps keyed by benchmark marker label.
     *
     * @var array<string, string>
     */
    protected static array $timeMarkers = [];
    /**
     * Captured memory usage snapshots keyed by benchmark marker label.
     *
     * @var array<string, int>
     */
    protected static array $memoryMarkers = [];

    /**
     * Record both time and memory usage at a named checkpoint.
     *
     * @param string $name Unique identifier for the checkpoint.
     *
     * @return void
     */
    public static function mark(string $name): void
    {
        self::$timeMarkers[$name] = microtime();
        self::$memoryMarkers[$name] = memory_get_usage(false);
    }

    /**
     * Calculate elapsed time between two markers.
     *
     * @param string $mark1     Starting marker name.
     * @param string $mark2     Ending marker name.
     * @param int    $decimals  Decimal precision for the formatted response.
     *
     * @throws InvalidArgumentException When either marker has not been recorded.
     *
     * @return string
     */
    public static function elapsedTime(string $mark1, string $mark2, int $decimals = 4): string
    {
        self::checkMarkers(self::$timeMarkers, $mark1, $mark2);

        list($startmark, $startSeconds) = explode(' ', self::$timeMarkers[$mark1]);
        list($endmark, $endSeconds) = explode(' ', self::$timeMarkers[$mark2]);

        return number_format(($endmark + $endSeconds) - ($startmark + $startSeconds), $decimals);
    }

    /**
     * Calculate memory usage delta between two markers and format as human readable.
     *
     * @param string $mark1 Starting marker name.
     * @param string $mark2 Ending marker name.
     *
     * @throws InvalidArgumentException When either marker has not been recorded.
     *
     * @return string
     */
    public static function memoryUsage(string $mark1, string $mark2): string
    {
        self::checkMarkers(self::$memoryMarkers, $mark1, $mark2);

        return self::humanSize(self::$memoryMarkers[$mark2] - self::$memoryMarkers[$mark1]);
    }

    /**
     * Ensure that two markers exist in the provided dataset.
     *
     * @param array<string, mixed> $array  Marker dataset to inspect.
     * @param string               $mark1  Starting marker name.
     * @param string               $mark2  Ending marker name.
     *
     * @throws InvalidArgumentException When a marker is missing.
     *
     * @return void
     */
    protected static function checkMarkers(array $array, string $mark1, string $mark2): void
    {
        if (!isset($array[$mark1])) {
            throw new InvalidArgumentException($mark1);
        }

        if (!isset($array[$mark2])) {
            throw new InvalidArgumentException($mark2);
        }
    }

    /**
     * Present a byte count using the most appropriate unit.
     *
     * @param int|float $size Raw size delta in bytes.
     *
     * @return string
     */
    protected static function humanSize($size)
    {
        if ($size >= 1073741824) {
            $fileSize = round($size / 1024 / 1024 / 1024, 1) . 'GB';
        } elseif ($size >= 1048576) {
            $fileSize = round($size / 1024 / 1024, 1) . 'MB';
        } elseif ($size >= 1024) {
            $fileSize = round($size / 1024, 1) . 'KB';
        } else {
            $fileSize = $size . ' bytes';
        }
        return $fileSize;
    }
}
