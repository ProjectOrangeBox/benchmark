<?php

declare(strict_types=1);

use orange\benchmark\Benchmark;

/**
 * Benchmark keeps its markers in static arrays and offers no way to clear them,
 * so every test resets them by reflection first - otherwise a marker recorded by
 * one test would still be there for the next, and "the marker is missing" cases
 * would pass or fail depending on run order.
 *
 * The values themselves are wall-clock time and live memory, so nothing here
 * asserts an exact figure. What is testable is the contract around them: which
 * markers exist, what happens when one doesn't, the sign and ordering of a
 * delta, and the formatting.
 */
final class BenchmarkTest extends unitTestHelper
{
    protected function setUp(): void
    {
        $this->resetMarkers();
    }

    protected function tearDown(): void
    {
        // leave nothing behind for whatever runs next
        $this->resetMarkers();
    }

    private function resetMarkers(): void
    {
        foreach (['timeMarkers', 'memoryMarkers'] as $property) {
            $reflection = new ReflectionProperty(Benchmark::class, $property);
            $reflection->setValue(null, []);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function markers(string $property): array
    {
        return new ReflectionProperty(Benchmark::class, $property)->getValue();
    }

    private function humanSize(int|float $size): string
    {
        return new ReflectionMethod(Benchmark::class, 'humanSize')->invoke(null, $size);
    }

    /* --- mark --------------------------------------------------------------- */

    public function testMarkRecordsBothATimeAndAMemorySnapshot(): void
    {
        Benchmark::mark('start');

        $this->assertArrayHasKey('start', $this->markers('timeMarkers'));
        $this->assertArrayHasKey('start', $this->markers('memoryMarkers'));
    }

    public function testMarkersAreKeptPerName(): void
    {
        Benchmark::mark('one');
        Benchmark::mark('two');

        $this->assertSame(['one', 'two'], array_keys($this->markers('timeMarkers')));
    }

    /**
     * Re-using a name overwrites rather than accumulating - the name is the
     * identity of the checkpoint, so the newest reading wins.
     */
    public function testMarkingTheSameNameTwiceOverwritesIt(): void
    {
        Benchmark::mark('loop');
        $first = $this->markers('timeMarkers')['loop'];

        // enough delay that the second reading is genuinely different
        usleep(2000);

        Benchmark::mark('loop');
        $second = $this->markers('timeMarkers')['loop'];

        $this->assertCount(1, $this->markers('timeMarkers'));
        $this->assertNotSame($first, $second);
    }

    /* --- elapsedTime -------------------------------------------------------- */

    /**
     * microtime() without the float flag returns "msec sec" - two numbers in a
     * string - which is why elapsedTime() splits on the space and adds the halves
     * back together rather than casting the whole thing.
     */
    public function testElapsedTimeReportsAPositiveDurationBetweenTwoMarks(): void
    {
        Benchmark::mark('start');
        usleep(5000);
        Benchmark::mark('end');

        $elapsed = (float) Benchmark::elapsedTime('start', 'end');

        $this->assertGreaterThan(0, $elapsed);
        // 5ms of sleep should not read as multiple seconds on any machine
        $this->assertLessThan(1, $elapsed);
    }

    public function testElapsedTimeHonoursTheRequestedPrecision(): void
    {
        Benchmark::mark('start');
        usleep(1000);
        Benchmark::mark('end');

        $this->assertMatchesRegularExpression('/^-?\d+\.\d{4}$/', Benchmark::elapsedTime('start', 'end'));
        $this->assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', Benchmark::elapsedTime('start', 'end', 2));
        $this->assertMatchesRegularExpression('/^-?\d+$/', Benchmark::elapsedTime('start', 'end', 0));
    }

    /**
     * The arguments are start and end in that order, so passing them backwards
     * reads as a negative duration rather than being silently normalised - the
     * caller asked the wrong question and the answer says so.
     */
    public function testElapsedTimeIsNegativeWhenTheMarksAreReversed(): void
    {
        Benchmark::mark('start');
        usleep(5000);
        Benchmark::mark('end');

        $this->assertLessThan(0, (float) Benchmark::elapsedTime('end', 'start'));
    }

    public function testElapsedTimeBetweenAMarkAndItselfIsZero(): void
    {
        Benchmark::mark('only');

        $this->assertSame(0.0, (float) Benchmark::elapsedTime('only', 'only'));
    }

    public function testElapsedTimeThrowsNamingTheMissingMarker(): void
    {
        Benchmark::mark('start');

        try {
            Benchmark::elapsedTime('start', 'never-marked');
            $this->fail('a missing end marker was accepted');
        } catch (InvalidArgumentException $e) {
            // the message is the marker name, so the caller can see which
            $this->assertSame('never-marked', $e->getMessage());
        }

        try {
            Benchmark::elapsedTime('never-marked', 'start');
            $this->fail('a missing start marker was accepted');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('never-marked', $e->getMessage());
        }
    }

    /* --- memoryUsage -------------------------------------------------------- */

    public function testMemoryUsageReportsTheDeltaBetweenTwoMarks(): void
    {
        Benchmark::mark('start');

        // hold onto something big enough to move the reading
        $ballast = str_repeat('x', 200000);

        Benchmark::mark('end');

        $this->assertStringContainsString('KB', Benchmark::memoryUsage('start', 'end'));

        // keep the allocation alive until after the second mark
        $this->assertSame(200000, strlen($ballast));
    }

    public function testMemoryUsageThrowsNamingTheMissingMarker(): void
    {
        Benchmark::mark('start');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('never-marked');

        Benchmark::memoryUsage('start', 'never-marked');
    }

    /**
     * The two marker sets are independent, so a name only present in one is
     * still missing from the other's point of view.
     */
    public function testTheTimeAndMemoryMarkerSetsAreCheckedSeparately(): void
    {
        Benchmark::mark('both');

        // remove it from the memory set only
        $reflection = new ReflectionProperty(Benchmark::class, 'memoryMarkers');
        $reflection->setValue(null, []);

        // time still answers
        $this->assertIsString(Benchmark::elapsedTime('both', 'both'));

        $this->expectException(InvalidArgumentException::class);

        Benchmark::memoryUsage('both', 'both');
    }

    /* --- humanSize ---------------------------------------------------------- */

    public function testHumanSizeUsesBytesBelowAKilobyte(): void
    {
        $this->assertSame('0 bytes', $this->humanSize(0));
        $this->assertSame('512 bytes', $this->humanSize(512));
        $this->assertSame('1023 bytes', $this->humanSize(1023));
    }

    public function testHumanSizeStepsUpThroughTheUnits(): void
    {
        $this->assertSame('1KB', $this->humanSize(1024));
        $this->assertSame('1.5KB', $this->humanSize(1536));
        $this->assertSame('1MB', $this->humanSize(1048576));
        $this->assertSame('1GB', $this->humanSize(1073741824));
        $this->assertSame('2.5GB', $this->humanSize(2684354560));
    }

    /**
     * Each unit runs right up to the next threshold, so the last value before a
     * step reads in the smaller unit rather than rounding into the larger one.
     */
    public function testHumanSizeSwitchesUnitAtTheThresholdNotBefore(): void
    {
        $this->assertSame('1024KB', $this->humanSize(1048575));
        $this->assertSame('1MB', $this->humanSize(1048576));
    }

    /**
     * Memory can fall between two marks - freeing a large array, say - and the
     * unit thresholds only test the upper bound, so a negative delta always
     * lands in the bytes branch however large it is. Pinned because the number
     * is still correct and readable; it is the unit that does not scale.
     */
    public function testANegativeDeltaIsReportedInBytes(): void
    {
        $this->assertSame('-512 bytes', $this->humanSize(-512));
        $this->assertSame('-2097152 bytes', $this->humanSize(-2097152));
    }
}
