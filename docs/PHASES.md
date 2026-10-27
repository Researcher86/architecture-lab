# Architecture Lab — How It Was Built

This is the build journal. Each phase states its goal, what made it real, its
definition of done, and the evidence that holds it. Work happens in the
project container; a phase is complete only after `make test`,
`make analyse` and `make format-check` pass there.

Status legend: ✅ complete, 🚧 in progress, ⏳ planned.

---

## 1. Where this project fits

```text
engineering-lab/                     # workspace
├── book/                            # git: Researcher86/engineering-lab-book
│   ├── README.md                    # series table of contents
│   └── architecture-lab/            # seven Russian chapters
├── architecture-lab/                # git: Researcher86/architecture-lab — this project
│   ├── src/                         # seven treatments + harness + scenario
│   ├── experiments/                 # runnable questions
│   ├── benchmarks/                  # measured suites
│   ├── tests/                       # unit, differential, invariant and architecture tests
│   ├── docs/                        # engineering documentation (EN)
│   └── var/                         # generated results (gitignored)
└── algorithms-lab/                  # sibling lab and process reference
```

### Roles

| Component | Role |
|---|---|
| `architecture-lab` | Executable source of truth: code, tests, measurements, English docs |
| `book/architecture-lab` | Russian narrative, seven chapters, code pinned to `v1.0.0` |
| `book/php-systems-lab/tools/check_book.py` | Verifies code blocks and links, with `--expected-chapters` |
| `book/.github/workflows/book.yml` | Book CI: existing books, Algorithms Lab, Architecture Lab |
| `engineering-lab-book` | Umbrella book repository and published narrative |

### Contract with the ecosystem

1. **One chapter = one architectural mechanism**, in the fixed order:
   Layered, Hexagonal, Clean, Modular Monolith, CQRS, Event-Driven, DDD.
2. **One domain, one codebase.** The same course-enrollment code is examined
   from each angle; the baseline is the explicit "before" implementation.
3. **The book is written from the code.** Implementation, tests and
   measurements first; chapters quote real files and real output.
4. **Links are pinned.** Every book link targets `v1.0.0` or a full SHA, never
   a branch.
5. **Languages.** Code, comments, README and `docs/` are English. Book chapters
   are Russian.
6. **One command per experiment.** `make experiment ARGS="..."` is the
   interface the book can quote.
7. **The book is checked mechanically.** Seven chapters, closed fences, exact
   excerpts, valid line ranges, resolvable links.

---

## 2. Principles

1. **Build the baseline first.** A working coupled implementation makes the
   later claims testable.
2. **Boundaries are measured, not preached.** The dependency checker and the
   module metrics are part of the code.
3. **Counters before stopwatches.** SQL statements, events, store reads and
   projection writes prove what happened; time is secondary and noisy.
4. **Reproducibility is part of the result.** Seed, PHP version, OPcache/JIT,
   Xdebug mode, memory limit and commit travel with every number.
5. **Do not hide the seam.** In-memory atomicity, synchronous bus, no saga, no
   production gateway and token-level scanning are documented as limitations.
6. **The scenario is an architecture test.** Convergence and conservation
   checks catch broken context translations.
7. **The book is the last phase.** Prose after code and numbers, never instead
   of them.
8. **No runtime dependencies.** Composer is for development tools only.

### Non-goals

- A production service, deployment, HTTP API, authentication or UI.
- A distributed system, broker, Kubernetes, database server or multi-region
  consistency.
- Event sourcing, snapshots, upcasting or a saga framework.
- A framework integration guide.
- Performance guarantees or CI timing thresholds.

---

## 3. Stack and standards

| Aspect | Standard | Note |
|---|---|---|
| PHP | `^8.5`, CLI, 64-bit | `php:8.5-cli` in Docker |
| Composer | `researcher86/architecture-lab`, PSR-4 `ArchitectureLab\` → `src/`, `ArchitectureLab\Tests\` → `tests/` | No runtime dependencies |
| Runtime | PDO + PDO SQLite | One connection for documents, outbox and processed events |
| Tests | PHPUnit `^11.5` | 172 tests, 1,270 assertions at the time of writing |
| Static analysis | PHPStan `^2.2`, level 8 | Paths: `src`, `bin`, `tests`, `experiments`, `benchmarks` |
| Style | PHP-CS-Fixer `^3` | PSR-12, strict types, import cleanup |
| Docker | `php:8.5-cli` + PDO SQLite, Composer, Xdebug (off), diagnostics | `memory_limit=512M`, `opcache.enable_cli=0`, UTC |
| Orchestration | `docker-compose.yml`, service `php`, container `architecture-lab` | Project mounted at `/app` |
| Make | `build/install/test/analyse/format/format-check/check/experiment/scenario/benchmark` + debug variants | CI calls the same targets |
| CI | GitHub Actions: build, install, test, analyse, format-check, down | No performance thresholds |
| Results | `var/results/`, `var/benchmarks/`, `var/datasets/` gitignored | Keep directories, not results |
| Release | annotated `v1.0.0` after code/docs freeze | Book links target it |
| Language | Code/docs/README English; book Russian | Comments explain decisions |

### Docker is the only supported environment

```text
host
  └── make <target>
        └── docker compose exec php <command>
              └── php:8.5-cli + PDO SQLite + Composer
```

Rules:

1. Every project command goes through `make`.
2. CI uses the same targets and container.
3. `make check` = `test` + `analyse` + `format-check`.
4. Timing thresholds are not part of CI.

---

## 4. Phases

### Phase 0 — Context and plan ✅

- Read the draft topic, the process prompt and the `algorithms-lab` reference.
- Fix the seven chapters and the MVP scenario: course enrollment through
  Catalog → Enrollment → Billing → read model → notifications.
- Record the plan in this file.

**Definition of done:** scope, chapter order, scenario and non-goals are
written down; no code depends on an unstated requirement.

### Phase 1 — Scaffold and harness ✅

- `composer.json`, `Dockerfile`, `docker-compose.yml`, `Makefile`, PHPUnit,
  PHPStan, PHP-CS-Fixer, `.gitignore`, `.editorconfig`, CI workflow.
- Experiment and benchmark entry points with discoverable descriptors.
- Environment block: PHP, OS, architecture, CPU, memory limit, cgroup,
  OPcache, JIT, Xdebug, allocator, commit.

**Definition of done:** `make build`, `make install`, `make test`,
`make analyse`, `make format-check` work before chapter code exists.

### Phase 2 — Shared kernel ✅

- `Money`, typed ids, `FrozenClock`, `SystemClock`, `SequentialIdGenerator`.
- `DocumentStore`, `UnitOfWork`, `Clock`, `IdGenerator` ports.
- `Counters`, `Stopwatch`, `MemoryMeter`, `Measure`, `ByteFormatter`,
  `DeterministicRandom`, `ProjectRoot`.
- In-memory and SQLite document stores, outboxes and processed-event stores.

**Definition of done:** boundaries can be exercised without PDO; the SQLite
adapter has real transaction and rollback tests.

### Phase 3 — Catalog ✅

- `CourseOffering` aggregate with capacity and seat ownership.
- `CatalogApi`, `OfferingView`, public exceptions, publish/reserve/release.
- Document repository and outbox facts.
- Tests: capacity, exhaustion, release, unknown offering, duplicate events.

**Definition of done:** Catalog can be used only through `PublicApi`; domain
exceptions are translated at the boundary.

### Phase 4 — Enrollment and Billing ✅

- Enrollment aggregate and pending → confirmed/cancelled transitions.
- `SeatAllocator` and `PaymentProcessor` ports; Catalog and Billing ACL
  adapters.
- Billing invoice aggregate, deterministic gateway, idempotency by enrollment.
- Synchronous request flow and async payment-result listener.
- Tests: transitions, payment success/failure, seat release, idempotent retry.

**Definition of done:** the write model converges in both modes; no context
imports another context's domain or application layer.

### Phase 5 — Eventing and CQRS ✅

- `EventEnvelope`, `InMemoryEventBus`, `Outbox`, `OutboxDispatcher`,
  `IdempotentListener`, `ProcessedEventStore`.
- Retry, dead-letter and duplicate-delivery tests.
- `CommandBus`, `QueryBus`; capacity and student-schedule projections; query
  handlers and views.
- Stale-window and convergence tests.

**Definition of done:** at-least-once delivery is observable; duplicate publish
does not duplicate an effect; read models are event-built and queryable.

### Phase 6 — Architecture checker ✅

- Token scanner, dependency graph, module map and metrics.
- Clean rules: domain purity, application ports, cross-context public API,
  delivery public API.
- Layered baseline rules: layer skip, `PDO` in application, domain leak.
- Tarjan cycle detection, JSON report, experiment and benchmark.
- Tests: parser, rules, cycles, real clean tree, exact baseline violations.

**Definition of done:** clean tree 0 violations/0 cycles; baseline exactly 2
intended violations; a new accidental import fails the suite.

### Phase 7 — Modular monolith and public APIs ✅

- Five contexts in one deployable unit.
- Public API imports and ACL adapters counted by the `modules:coupling` and
  `ddd:contexts` experiments.
- Module metrics and runtime composition documented.
- Tests: cross-context edge is public API; ACL translation works.

**Definition of done:** module boundaries are visible in the import graph and in
the runtime composition; no context is a deployment unit.

### Phase 8 — Baseline and runtime ✅

- Intentional layered transaction script under `src/Baseline/Layered/`.
- `LabRuntime` composition root for sync/event-driven and SQLite/in-memory.
- Scenario runner, options and report.
- Tests: baseline, runtime modes, adapter equivalence, scenario invariants.

**Definition of done:** both modes pass the same invariants; the baseline's
violations are executable evidence, not a comment.

### Phase 9 — MVP scenario ✅

- `make scenario` runs one deterministic course-enrollment flow.
- Event-driven defaults: 12 students, 2 offerings, capacity 4, fail every 4.
- Sync variant shows seat reuse after failed payments.
- Eleven invariants, recorded JSON, stale-read-model demonstration.

**Definition of done:** one command, one report, all invariants pass in both
modes; `var/results/architecture-lab-scenario.txt` is generated but ignored.

### Phase 10 — Tests and measurements ✅

- 172 tests / 1,270 assertions across foundation, domain, application,
  persistence, eventing, CQRS, architecture, runtime, scenario, benchmark and
  experiment harnesses.
- Seven experiments and seven benchmark suites.
- Real outputs recorded in `docs/BENCHMARKS.md`, `docs/COMPLEXITY.md` and the
  scenario report.

**Definition of done:** `make test`, `make analyse`, `make format-check` green;
numbers in docs come from the recorded runs.

### Phase 11 — Release ✅

- Public repository `https://github.com/Researcher86/architecture-lab`.
- Annotated tag `v1.0.0`.
- GitHub release notes with chapters, scenario, checks and environment.
- README and docs frozen before the tag.

**Definition of done:** repository is public; tag points at the release commit;
release exists; CI for that commit is green.

### Phase 12 — Book ✅

- Seven Russian chapters in `book/architecture-lab/`.
- Each chapter: problem, mechanism, experiment, failure modes, verification,
  numbers, summary, exercises, next chapter.
- Code excerpts pinned to `v1.0.0` with exact line ranges.
- Book README, root book README, Prettier, checker, Book CI step.
- Record the final book commit and CI run ID here.

**Definition of done:** checker reports `ok` with `--expected-chapters=7`;
Book CI is green; both repositories are clean; the book links resolve.

---

## 5. Evidence map

| Chapter | Implementation | Experiment | Benchmark | Main tests |
|---|---|---|---|---|
| 01 Layered | `src/Baseline/Layered/` | `layered:cost` | `01-layered` | `tests/Baseline/` |
| 02 Hexagonal | `src/*/Infrastructure/`, ports | `hexagonal:adapter-swap` | `02-hexagonal` | adapter equivalence, ports |
| 03 Clean | `src/Architecture/` | `clean:dependency-rule` | `03-clean` | scanner, rules, cycles |
| 04 Modules | context namespaces, `PublicApi` | `modules:coupling` | `04-modules` | cross-context rules, ACL |
| 05 CQRS | `src/Cqrs/`, `src/Reporting/` | `cqrs:queries` | `05-cqrs` | projections, query views |
| 06 Events | `src/Eventing/` | `events:delivery` | `06-events` | outbox, retries, idempotency |
| 07 DDD | contexts, ACLs, `ddd:contexts` | `ddd:contexts` | `07-ddd` | scenario invariants, context edges |
| MVP | `src/Integration/` | `scenario:course-enrollment` | scenario numbers | `tests/Integration/` |

## 6. Commands

```bash
make build install
make check
make scenario
make scenario ARGS="--mode=sync"
make experiment ARGS="clean:dependency-rule"
make benchmark ARGS="05-cqrs --repetitions=5"
```

The full command list is in the README; measured tables are in
`docs/BENCHMARKS.md`; cost models are in `docs/COMPLEXITY.md`.

## 7. Final record

Recorded on 2026-10-27.

- Release commit: `a920641` (`docs: document the architecture, measurements and
  decisions`); the measured code commit is `aab9748`.
- Annotated tag: `v1.0.0` → `a920641` (tag object `7b743dc`).
- Release:
  <https://github.com/Researcher86/architecture-lab/releases/tag/v1.0.0>.
- Lab CI run for `a920641`:
  [#40459609255](https://github.com/Researcher86/architecture-lab/actions/runs/40459609255)
  — success.
- Book commits: `4beb998` (chapters and READMEs), `efbf607` (Prettier),
  `13ca74b` (Book CI step), `1e4dd10` (sync event-count fix); final book HEAD
  `1e4dd10`.
- Book CI run:
  [#40461428425](https://github.com/Researcher86/engineering-lab-book/actions/runs/40461428425)
  — success.
- Checker output: `chapters=7 code_blocks=74 linked=53 exact=53 links=53
  resolved=53 unique_links=53 local_links=7 local_resolved=7` → `ok`.
- Post-release docs correction: `7f8a2f6` fixed the `42`/`52` event
  breakdown in `docs/BENCHMARKS.md` and `docs/COMPLEXITY.md` (CI
  [#40461253439](https://github.com/Researcher86/architecture-lab/actions/runs/40461253439)
  — success). Source files and book excerpts are unchanged, so the book remains
  pinned to `v1.0.0`.
