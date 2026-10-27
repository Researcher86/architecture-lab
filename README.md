# Architecture Lab

**[🧪 Engineering Lab](https://github.com/Researcher86/engineering-lab-book)** · Architecture · 7 chapters

> An executable laboratory for the decisions that shape a codebase: where a
> dependency is allowed to point, what a module boundary costs, how a write
> model becomes a read model, and what an event actually guarantees.

This repository is the code behind the seven chapters of
[Architecture Lab](https://github.com/Researcher86/engineering-lab-book/tree/master/architecture-lab).
It contains one working course-enrollment system, seven architectural
treatments of it, a dependency-rule checker, a deterministic outbox, measured
experiments and benchmarks, and an end-to-end scenario.

The code release is tagged [`v1.0.0`](https://github.com/Researcher86/architecture-lab/releases/tag/v1.0.0);
the build journal lives in [docs/PHASES.md](docs/PHASES.md).

---

## 30-second demo

Requires Docker. Nothing is installed on the host.

```bash
make build       # build php:8.5-cli with the project extensions
make install     # composer install inside the container
make check       # PHPUnit + PHPStan level 8 + PHP-CS-Fixer
make scenario    # the one end-to-end course-enrollment flow
```

The harness is discoverable:

```bash
make experiment                              # list experiments
make experiment ARGS="layered:cost"
make experiment ARGS="clean:dependency-rule"
make benchmark                               # list benchmark suites
make benchmark ARGS="05-cqrs --repetitions=5"
```

Open a shell in the same container with `make shell`; stop it with `make down`.

## What it does

| | |
|---|---|
| **Docker-first** | The only supported environment is the project container; `make` never calls host PHP |
| **PHP 8.5** | `php:8.5-cli`, strict types, no runtime framework |
| **One domain, seven lenses** | Course enrollment is implemented once, then examined as layers, ports/adapters, clean dependencies, modules, CQRS, events and bounded contexts |
| **Dependency rule as code** | A token-level scanner builds the import graph, checks allowed directions, finds cycles and reports coupling metrics |
| **Real transactions** | SQLite document store, outbox and processed-event keys share one PDO; the in-memory adapters are an explicit test double |
| **Measured, not asserted** | Every claim has an experiment or benchmark; the environment and commit travel with the numbers |
| **Book contract** | Every chapter quotes real output and links to code pinned at `v1.0.0` |

## Chapters

The order is fixed by the book. Implementation follows the same order.

| # | Chapter | What the code shows | Status |
|---|---|---|---|
| 01 | Layered Architecture | a working transaction-script baseline with an intentional layer skip and `PDO` in the application | ✅ code, tests, measurements |
| 02 | Hexagonal / Ports and Adapters | the same enrollment use case over in-memory and SQLite adapters | ✅ code, tests, measurements |
| 03 | Clean Architecture | a dependency graph, the dependency rule, module metrics and cycle detection | ✅ code, tests, measurements |
| 04 | Modular Monolith | five contexts in one deployable unit, with public APIs and cross-context imports | ✅ code, tests, measurements |
| 05 | CQRS | command/query buses and event-built read models beside the write model | ✅ code, tests, measurements |
| 06 | Event-Driven Architecture | outbox, at-least-once delivery, idempotent listeners, retries and dead letters | ✅ code, tests, measurements |
| 07 | DDD and Bounded Contexts | a context map, anti-corruption adapters and explicit translations | ✅ code, tests, measurements |

## The MVP scenario

`make scenario` runs one deterministic flow through the whole system:

```text
Catalog publishes two offerings
        ↓
12 students request seats round-robin (capacity 4 each)
        ↓
Enrollment reserves a seat in Catalog and stores a pending enrollment
        ↓
Billing charges every 4th payment as declined
        ↓
outbox delivers enrollment / billing / catalog events
        ↓
Reporting builds capacity and per-student read models
        ↓
Notifications records confirmed and cancelled messages
```

The default event-driven run produces:

```text
accepted 8, rejected by capacity 4
write model: confirmed 6, cancelled 2, pending 0
outbox: 16 pending before pump, 42 delivered, 0 failed, 0 dead-lettered
read model: reserved 6, confirmed 6, cancelled 2, pending 0, entries 8
notifications 8, stale read-model entries before delivery 0
```

All eleven invariants pass, including `capacity-conserved`, `catalog-conserved`,
`read-model-confirmed`, `no-dead-letters` and `notifications-match`.
`make scenario ARGS="--mode=sync"` produces the synchronous variant: 10
accepted, 2 rejected, 8 confirmed, 2 cancelled, 52 delivered events.

The same run is available as:

```bash
make experiment ARGS="scenario:course-enrollment --students=12 --offerings=2 --capacity=4 --fail-every=4 --mode=event-driven --seed=architecture-lab"
```

## Measurements at a glance

One development run in Docker on macOS (aarch64, PHP 8.5.11, OPcache CLI off,
JIT off, Xdebug off, Zend MM). These are relative observations, not universal
constants. The full tables and environment block are in
[docs/BENCHMARKS.md](docs/BENCHMARKS.md).

| Question | Operation | Result |
|---|---|---|
| What does the layered baseline do? | 200 enrollments | 1,201 SQL statements, 4.07 ms; 2 intended layer violations |
| Does the adapter matter? | 100 enrollments | in-memory 4.36 ms / 0 SQL vs SQLite 16.63 ms / 4,121 SQL |
| Is the clean tree clean? | full scan | 127 files, 127 classes, 20 interfaces, 7,030 lines, 0 violations, 0 cycles |
| What does a module boundary cost? | 1,000 lookups | 1.632 ms through `CatalogApi` vs 0.102 ms raw document read |
| Does the read model help? | 5 rounds over 100 students | 2.84 ms projection lookup vs 74.79 ms write-side scan (26.3×) |
| What does at-least-once mean? | 20 flaky deliveries | 22 attempts, 20 effects, 2 retries; duplicate publish → 1 effect, 1 skip |
| How many events move? | event-driven scenario | 16 pending, 42 delivered; sync scenario 52 delivered |

## Project structure

```text
architecture-lab/
├── bin/                  # experiment and benchmark entry points
├── src/
│   ├── Architecture/     # scanner, graph, rules, cycles, module metrics
│   ├── Baseline/Layered/ # the intentional "before" implementation
│   ├── Catalog/          # offerings, seats, capacity
│   ├── Enrollment/       # requests and state transitions
│   ├── Billing/          # invoices and deterministic payments
│   ├── Reporting/        # event-built read models
│   ├── Notifications/    # observable side effect
│   ├── Eventing/         # envelope, bus, outbox, idempotency
│   ├── Persistence/      # in-memory and SQLite document adapters
│   ├── Cqrs/             # command and query buses
│   ├── Integration/      # the one MVP scenario
│   └── Runtime/          # composition root
├── experiments/          # one runnable question per chapter
├── benchmarks/           # seven measured suites
├── tests/                # unit, invariant, differential and harness tests
├── docs/                 # engineering documentation (English)
└── var/                  # generated results (gitignored)
```

## How the architecture is checked

`make experiment ARGS="clean:dependency-rule"` scans every PHP file under
`src/`, reads top-level `use` statements, builds a class graph and applies the
rules:

- domain code may depend only on the shared kernel;
- application code uses ports, never persistence adapters or another context's
  internals;
- cross-context imports may target only `PublicApi`;
- delivery code may see `PublicApi` DTOs, not domain objects or repositories;
- presentation in the baseline may not reach infrastructure directly.

The clean tree has **0 violations and 0 module cycles**. The deliberately
coupled baseline has exactly **2** intended violations: `PDO` imported by the
application service and a controller that reaches a repository directly.

This is a useful gate, not a sandbox: the scanner is token-based, ignores
dynamically constructed class names, and does not parse group imports as a
full AST would. The limitations are recorded in
[docs/DECISIONS.md](docs/DECISIONS.md).

## What this lab does not pretend

- There is no HTTP API, authentication, UI or production deployment. The
  delivery layer is the scenario and the CLI harness.
- The in-memory document store and outbox are separate objects, so they are
  not atomic together. Only the SQLite composition shares one transaction.
- The event bus is synchronous and in-process. The outbox guarantees
  at-least-once *delivery to this bus*, not delivery to a remote broker.
- There is no saga/process manager, no distributed lock and no cross-process
  capacity reservation. Concurrency control is the single SQLite transaction.
- The read model is in the same database as the write model. CQRS here means a
  separate model and query path, not a separate database or service.
- CQRS is not event sourcing. Aggregates are stored as documents; events are
  integration facts written to an outbox.
- `NotificationLog` is an observable in-process side effect, not an email
  provider.
- The payment gateway is deterministic; it models success/failure, not a real
  provider, idempotency keys over HTTP or settlement.
- Event versioning is a field, not a migration/upcasting system.
- The dependency checker is a useful approximation, not a compiler plugin.

## Documentation

- [docs/PHASES.md](docs/PHASES.md) — the build journal: phases, decisions and definitions of done.
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — modules, runtime composition, write/read paths and data.
- [docs/BENCHMARKS.md](docs/BENCHMARKS.md) — how measurements are taken and the measured tables.
- [docs/COMPLEXITY.md](docs/COMPLEXITY.md) — operation counts, cost models and what each number means.
- [docs/DECISIONS.md](docs/DECISIONS.md) — dated decisions and trade-offs.

## Running the seven experiments

```bash
make experiment ARGS="layered:cost --students=200"
make experiment ARGS="hexagonal:adapter-swap --students=100"
make experiment ARGS="clean:dependency-rule"
make experiment ARGS="modules:coupling"
make experiment ARGS="cqrs:queries --students=100"
make experiment ARGS="events:delivery --events=20 --students=6 --capacity=6"
make experiment ARGS="ddd:contexts"
```

The corresponding benchmark suites:

```bash
make benchmark ARGS="01-layered --repetitions=5"
make benchmark ARGS="02-hexagonal --repetitions=5"
make benchmark ARGS="03-clean --repetitions=5"
make benchmark ARGS="04-modules --repetitions=5"
make benchmark ARGS="05-cqrs --repetitions=5"
make benchmark ARGS="06-events --repetitions=5"
make benchmark ARGS="07-ddd --repetitions=5"
```

Each report starts with PHP version, OS, architecture, CPU, memory limit,
cgroup limit, OPcache/JIT/Xdebug state, allocator and git commit. A number
without that block is an anecdote.

## How this project relates

- **[Engineering Lab book](https://github.com/Researcher86/engineering-lab-book)** —
  the Russian narrative: one chapter per architectural idea, code excerpts
  pinned to this repository's `v1.0.0` tag.
- **[Algorithms Lab](https://github.com/Researcher86/algorithms-lab)** — the
  sibling laboratory that measures data structures and algorithms; this
  project applies the same measurement discipline to boundaries and flows.
- **PHP Systems Lab** — the systems built from those algorithms; this lab is
  about how their code is organised before scale makes the decisions expensive.

## License

MIT.
