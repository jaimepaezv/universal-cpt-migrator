# Benchmark Profiles

This plugin uses repeatable local benchmark-style validation profiles inside the PHPUnit scale suite. These are not synthetic microbenchmarks; they are operational workload profiles designed to catch regressions in chunking, ZIP transport, media handling, and worker recovery.

## Current Profiles

### High-Volume Media-Heavy

- Source items: 120
- Chunk size: 25
- Media density: every 4th item
- Coverage:
  - background export
  - ZIP extraction
  - chunked background import
  - imported thumbnail verification

### Large Recovery Profile

- Source items: 180
- Chunk size: 15
- Media density: every 3rd item
- Failure injection:
  - queued worker event cleared before execution
- Coverage:
  - diagnostics worker repair
  - resumed import after missing worker event

### Very Large Warning Profile

- Source items: 240
- Chunk size: 12
- Media density: every 2nd item
- Failure injection:
  - repeated missing-manifest media warnings
  - worker event repair before execution
- Coverage:
  - item-level warning preservation
  - full completion without failed items
  - multi-iteration import progression

### Simulated Slow-I/O Dense Media Profile

- Source items: 90
- Chunk size: 5
- Media density: every item
- Payload density: enlarged post bodies to keep package and import work non-trivial
- Coverage:
  - dense manifest-backed media handling
  - high iteration count from small chunk sizes
  - stability under a slower-I/O style chunk profile

## What These Profiles Intentionally Measure

- package construction across non-trivial content sets
- ZIP artifact creation and extraction
- chunk progression over multiple background worker iterations
- worker-event repair and resumed processing
- media portability and warning preservation under load
- end-state correctness rather than synthetic throughput only

## What They Do Not Guarantee

- exact throughput on shared hosting
- behavior on object-storage-backed uploads
- multisite-specific performance
- network latency effects for remote media enabled environments

## How To Extend The Profile Matrix

When adding a new profile:

1. choose one bottleneck to stress
2. keep assertions deterministic
3. prefer worker repair or warning-path injections over arbitrary sleeps
4. avoid timing-only assertions unless the environment is tightly controlled
5. verify imported post count, media outcomes, and job completion state

## Recommended Future Profiles

- very large 500+ item package with mixed media density
- retry-after-failure profile with transport corruption between chunks
- remote-media-enabled profile against a controlled local host allowlist
- constrained retention profile to validate cleanup pressure after completion
