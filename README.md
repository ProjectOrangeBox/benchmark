# Benchmark

Lightweight static helper for timing code and measuring memory usage between two named checkpoints — no instantiation, no dependencies.

## Example

```php
use peels\benchmark\Benchmark;

Benchmark::mark('start');

// ... work to measure ...
usleep(150000);
$buffer = str_repeat('x', 1_000_000);

Benchmark::mark('end');

echo Benchmark::elapsedTime('start', 'end');  // e.g. "0.1521" seconds, 4 decimal places
echo Benchmark::memoryUsage('start', 'end');  // e.g. "1.0MB", human-readable
```

Both `elapsedTime()` and `memoryUsage()` throw `InvalidArgumentException` if either marker name was never recorded with `mark()`.
