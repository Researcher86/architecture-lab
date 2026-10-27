# Benchmarks — How They Are Run, and How to Read Them

A number without its environment is an anecdote. Every report carries the PHP
version, OS, architecture, CPU, memory limit, cgroup limit, OPcache/JIT/Xdebug
state, allocator and git commit.

## The two loops

- The **inner** loop runs the operation `iterations` times and is timed.
- The **outer** loop repeats the whole measurement `repetitions` times, after
  one discarded warm-up by default.

`ops/sec` is derived from the median, not the mean. `min`, `max`, `p95` and
median absolute deviation are kept so one lucky sample does not become a
claim. A benchmark suite loads its own fixture once; some suites deliberately
build on earlier operations in the same file, which is stated below.

## Counters are a separate instrument

Time says how fast this machine ran. Counters say what the code did:
`storeReads`, `storeWrites`, `sqlQueries`, `repositoryCalls`,
`eventsPublished`, `eventHandlers`, `projectionUpdates`, `handlerSkips`,
`aclTranslations`, `moduleCalls`. The experiments report counters; the
benchmarks report time and PHP allocator memory. They are not mixed into one
score.

`php_delta_bytes` is what `memory_get_usage()` reports: the Zend allocator, not
RSS and not native memory. `peak_delta_bytes` is the same limitation for the
PHP peak. No number here is a RSS claim.

## Environment of the recorded run

```text
PHP 8.5.11 on Linux (aarch64, Docker Desktop on macOS)
CPU: aarch64, 12 logical CPUs
memory_limit: 512M
cgroup: unlimited
OPcache CLI: off
JIT: off
Xdebug: off (installed, mode=off)
allocator: Zend MM
git: aab9748 (the measured code commit; the v1.0.0 tag is the next commit,
     which updates this document only)
```

The project runs in Docker Desktop during development. Absolute timings are
noisier than a native Linux host; relative comparisons, operation counts and
the shape of the curves remain useful. The book quotes one run, not a promise.

## Commands

```bash
make benchmark                                          # list suites
make benchmark ARGS="01-layered --repetitions=5"
make benchmark ARGS="02-hexagonal --repetitions=5"
make benchmark ARGS="03-clean --repetitions=5"
make benchmark ARGS="04-modules --repetitions=5"
make benchmark ARGS="05-cqrs --repetitions=5"
make benchmark ARGS="06-events --repetitions=5"
make benchmark ARGS="07-ddd --repetitions=5"
make benchmark ARGS="05-cqrs --format=json --output=var/results/05-cqrs.json"
```

`--format=text` is for reading, `json` for keeping, `csv` for a spreadsheet.
`--iterations=N` overrides the suite's own count; `--repetitions` and
`--warmups` control the outer loop. The output directory is gitignored.

## Chapter 01 — Layered baseline

The fixture inserts one course with a large capacity and then performs one
transaction-script enrollment per operation. `layered.report` runs after the
enroll benchmark in the same suite, so it scans the rows created by that run.

| Benchmark | Iterations | Median | p95 | max | ops/sec | PHP delta | peak delta |
|---|---:|---:|---:|---:|---:|---:|---:|
| `layered.enroll` | 200 | 3.337 ms | 3.398 ms | 3.398 ms | 59,933 | +592 B | 0 B |
| `layered.report` | 500 | 306.747 ms | 315.663 ms | 315.663 ms | 1,630 | +592 B | +648 B |

The experiment reports 1,201 SQL statements for 200 enrollments and two
intended layer violations. The report benchmark is slow because it returns
every row for the course and is called 500 times; it is the layer-skip path,
not a representative page size.

## Chapter 02 — Ports and adapters

Both runtimes run the same synchronous enrollment use case with the same
payment result. The only difference is the `DocumentStore` adapter.

| Benchmark | Iterations | Median | p95 | max | ops/sec | PHP delta | peak delta |
|---|---:|---:|---:|---:|---:|---:|---:|
| `hexagonal.in_memory` | 200 | 19.776 ms | 29.701 ms | 29.701 ms | 10,113 | +9.57 MB | +9.62 MB |
| `hexagonal.sqlite` | 200 | 226.374 ms | 349.116 ms | 349.116 ms | 883 | +592 B | +1.78 MB |

The direct experiment (100 enrollments) printed:

```text
in-memory: 4.356 ms, SQL statements 0, confirmed 100
SQLite:    16.625 ms, SQL statements 4121, confirmed 100
same decisions: yes
```

The in-memory run allocates the whole document set in PHP arrays; its delta
grows because fixtures and outbox messages stay alive. The SQLite run's PHP
delta stays flat because the data lives in SQLite, while its SQL counter grows.
This is exactly why both time and memory need their units named.

## Chapter 03 — Clean Architecture dependency rule

`clean.scan` scans the whole clean tree and builds the graph, rules, cycles and
metrics. `layered.scan` scans the seven-file baseline. The clean tree is much
larger, so the numbers are cost of the gate, not per-file cost.

| Benchmark | Iterations | Median | p95 | max | ops/sec | PHP delta | peak delta |
|---|---:|---:|---:|---:|---:|---:|---:|
| `clean.scan` | 3 | 95.163 ms | 117.870 ms | 117.870 ms | 32 | +44,392 B | +42,384 B |
| `layered.scan` | 20 | 39.401 ms | 43.602 ms | 43.602 ms | 508 | +320,592 B | 0 B |

The experiment printed:

```text
clean tree: files=127 classes=127 interfaces=20 lines=7030 edges=247 violations=0 cycles=0
layered baseline: files=7 classes=7 lines=316 violations=2
```

## Chapter 04 — Modular monolith

`modules.public_api` reads through `CatalogApi` (repository, hydration,
aggregate, DTO). `modules.store_read` reads the same document directly, which
the application is not allowed to do. `modules.command_bus` publishes a new
offering through the command bus.

| Benchmark | Iterations | Median | p95 | max | ops/sec | PHP delta | peak delta |
|---|---:|---:|---:|---:|---:|---:|---:|
| `modules.public_api` | 1,000 | 1.632 ms | 1.911 ms | 1.911 ms | 612,745 | +592 B | 0 B |
| `modules.store_read` | 1,000 | 0.102 ms | 0.107 ms | 0.107 ms | 9,848,142 | +592 B | 0 B |
| `modules.command_bus` | 200 | 1.470 ms | 1.991 ms | 1.991 ms | 136,008 | +2.56 MB | +2.63 MB |

The raw read is about 16× faster in this fixture. That is the cost of
hydration and a DTO boundary, not an argument for deleting the boundary: the
raw read also knows the collection name and the document schema, which is the
coupling the module rule prevents. The command-bus delta grows because each
publish keeps an offering document and an outbox message.

## Chapter 05 — CQRS read model

The fixture enrolls 500 students in one offering, then asks either the
projection or the write-side inspection API for one student. The write side has
no per-student index: `forStudent()` reads every enrollment and filters it.

| Benchmark | Iterations | Median | p95 | max | ops/sec | PHP delta | peak delta |
|---|---:|---:|---:|---:|---:|---:|---:|
| `cqrs.read_model` | 100 | 0.501 ms | 0.604 ms | 0.604 ms | 199,667 | +592 B | 0 B |
| `cqrs.write_scan` | 100 | 70.899 ms | 71.480 ms | 71.480 ms | 1,410 | +592 B | +648 B |

The direct experiment with 100 students and five rounds printed:

```text
read model:  2.841 ms, 505 store reads, 500 entries
write model: 74.785 ms, 505 store reads, 500 entries
speedup: 26.33x
```

Both sides report 505 store reads because the document store's `all()` counts
one read plus one per returned document. The difference is the work after the
read: `forStudent()` decodes and hydrates every enrollment, while the schedule
query reads one document and maps entries. A real SQL database would expose
the same difference as a scan versus an index lookup.

## Chapter 06 — Event delivery

`events.outbox_roundtrip` writes 20 events and drains them through one
idempotent listener. `events.duplicate_skip` publishes one already-processed
event repeatedly.

| Benchmark | Iterations | Median | p95 | max | ops/sec | PHP delta | peak delta |
|---|---:|---:|---:|---:|---:|---:|---:|
| `events.outbox_roundtrip` | 50 | 3.536 ms | 4.647 ms | 4.647 ms | 14,140 | +5.70 MB | +5.67 MB |
| `events.duplicate_skip` | 1,000 | 0.173 ms | 0.184 ms | 0.184 ms | 5,767,844 | +592 B | 0 B |

The direct experiment printed:

```text
flaky delivery: attempts=22 effects=20 delivered=20 failed=2 pending=0
duplicate publish: effects=1 skips=1
permanent failure: dead=1 pending=0
runtime chain: pending before=13, after=0, notifications=6
```

The outbox stores every delivered message in this in-memory fixture, hence the
PHP delta. The duplicate-skip path allocates almost nothing new after the first
delivery, which is visible in both the time and the flat delta.

## Chapter 07 — Bounded contexts

Both operations enroll 50 students and drain the outbox. In sync mode
Enrollment calls Billing inline through a port; in event-driven mode Billing
and Enrollment exchange events. The workloads are not identical: event-driven
mode records and delivers more events.

| Benchmark | Iterations | Median | p95 | max | ops/sec | PHP delta | peak delta |
|---|---:|---:|---:|---:|---:|---:|---:|
| `ddd.sync_contexts` | 20 | 5,695.327 ms | 9,231.261 ms | 9,231.261 ms | 4 | +2.60 MB | +11.51 MB |
| `ddd.event_driven_contexts` | 20 | 5,948.853 ms | 9,416.664 ms | 9,416.664 ms | 3 | +2.60 MB | +3.31 MB |

Each iteration is 50 enrollments (1,000 per benchmark measurement), so these
numbers are dominated by SQLite statements and the outbox chain, not by a
context-boundary microbenchmark. The two medians are close; the run does not
support a claim that one style is faster. It supports the operational point:
both are in the same process, and the event-driven path has more moving parts
to observe.

## Scenario numbers

`make scenario` (event-driven defaults):

```text
accepted 8, rejected by capacity 4
write model: confirmed 6, cancelled 2, pending 0
outbox: pending before pump 16, delivered 42, failed 0, dead-lettered 0, pending after 0
read model: reserved 6, confirmed 6, cancelled 2, pending 0, entries 8
notifications 8, stale entries before delivery 0
```

`make scenario ARGS="--mode=sync"`:

```text
accepted 10, rejected by capacity 2
write model: confirmed 8, cancelled 2, pending 0
outbox: pending before pump 52, delivered 52, failed 0, dead-lettered 0, pending after 0
read model: reserved 8, confirmed 8, cancelled 2, pending 0, entries 10
notifications 10, stale entries before delivery 0
```

The event count differs because sync mode handles 10 accepted enrollments and
each produces catalog, enrollment and billing facts; event-driven mode
handles 8. The scenario does not compare speed. It compares final state and
invariants.

## What is not measured

- No network, broker, disk fsync, process restart or multi-process contention.
- No HTTP or database server; SQLite is in-process and `:memory:`.
- No production data distribution. The payment gateway is deterministic.
- No RSS or native memory. The memory columns are PHP allocator bytes.
- No CI performance threshold: a shared runner is not a stable timer. CI runs
  correctness, static analysis and formatting only.

These omissions are part of the result, not hidden behind an adjective.
