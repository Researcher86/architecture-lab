# Decisions — Architecture Lab

A record of meaningful technical and process decisions, with the reason each
was made. New decisions are appended with a date.

## 2026-10-26 — One domain, seven lenses

- The lab uses a single scenario, course enrollment, implemented once and
  examined from seven architectural angles. Seven unrelated demos would let
  every chapter avoid the consequences of the previous one.
- The fixed order is: layered baseline, ports/adapters, dependency rule,
  modular monolith, CQRS, event-driven delivery, bounded contexts.
- The code keeps one deployable unit. The book can then show boundaries as
  design decisions, not as a deployment diagram that hides the source.

## 2026-10-26 — The baseline is intentionally wrong

- `src/Baseline/Layered/` exists to make the first chapter's failure modes
  executable: `PDO` and transaction control in the application service, a
  controller that imports a repository directly, a "domain" that is not
  isolated.
- The checker reports exactly two violations for it and excludes it from the
  clean analysis. It is not a second supported architecture.
- Keeping the baseline runnable is important: a slide can claim coupling; the
  experiment can show 1,201 SQL statements and a layer skip in the same tree.

## 2026-10-26 — Docker and PHP 8.5 are the only supported environment

- Every command runs through `make` and `docker compose exec` in the project
  image; host PHP, Composer and extensions are not a supported setup.
- The image is `php:8.5-cli` with PDO SQLite, Composer, Xdebug and diagnostic
  tools. Xdebug is installed with `mode=off` and `start_with_request=trigger`;
  debug runs opt in through `experiment-debug` / `benchmark-debug`.
- `memory_limit=512M`, `opcache.enable_cli=0` and UTC are explicit so a later
  host or image default cannot silently change the environment.
- CI runs the same `make` targets in the same image. There is no second
  environment to drift from.

## 2026-10-26 — No runtime framework or third-party package

- The point is to see the wiring. A framework would supply the container, the
  buses and the event dispatcher, and the book would end up explaining the
  framework instead of the architecture.
- `composer.json` has no runtime dependencies; PHPUnit, PHPStan and
  PHP-CS-Fixer are development tools.
- The cost is explicit: `LabRuntime` is a hand-written composition root and the
  buses are tiny. That is the subject of the lab.

## 2026-10-26 — Document store behind a port, SQLite as the real adapter

- The application owns a narrow `DocumentStore` port (`get`, `put`, `all`,
  `transactional`), not a schema. Repositories map aggregates to documents in
  infrastructure.
- SQLite gives real transactions, a real outbox table and a real
  processed-event table without asking the reader to run a database server.
- The in-memory adapter is a test double and an adapter-swap experiment. It is
  not presented as production persistence.
- **Known limitation:** the in-memory document store, outbox and processed
  event store are separate objects, so they are not atomic together. Only the
  SQLite composition shares one PDO and one transaction.

## 2026-10-26 — Money is integer minor units plus a currency

- No floats: `19.99` is not exactly representable, and payment rounding is not
  the subject.
- The currency is part of the value; adding EUR to USD throws instead of
  silently producing a number.
- `Money` is in `Shared`, because every context needs the same value semantics.

## 2026-10-26 — Typed identifiers, deterministic ids

- `StudentId`, `OfferingId`, `EnrollmentId` and `InvoiceId` prevent accidental
  argument swaps and make signatures self-documenting.
- `SequentialIdGenerator` produces `enr-1`, `evt-2`, `inv-3`. This makes
  scenario reports and tests exact. A production adapter would use UUIDs or a
  database sequence; that choice belongs outside the application.
- Aggregates never expose a public mutable property; identity is passed in,
  state changes go through methods.

## 2026-10-26 — Public API per context, ACL adapters between contexts

- Each context has a `PublicApi` namespace with interfaces, DTOs and public
  exceptions. Another context may import only that namespace.
- `CatalogSeatAllocator` translates Catalog's exception and DTO into
  Enrollment's `SeatAllocator` / `SeatReservation` vocabulary.
  `BillingPaymentProcessor` translates Billing's result into Enrollment's
  `PaymentOutcome`.
- `EnrollmentRequestedListener` reads the published event payload and creates
  Billing values; it imports no Enrollment class.
- The runtime wires the adapters. Cross-context imports therefore appear in
  infrastructure, not in application code.

## 2026-10-26 — CQRS is a model split, not a database split

- `CommandBus` and `QueryBus` make the write/read distinction visible in the
  delivery layer. The buses are tiny on purpose; their job is not routing
  magic, it is preventing delivery code from constructing repositories.
- The read models are event-built documents. They use counters and
  decrement-from-pending transitions, not absolute seat counts in events, so a
  late event cannot overwrite a newer state.
- The read model and write model share the SQLite database. This is a
  deliberate simplification: the chapter is about separate models and query
  paths, not about operating two databases.
- The write-side inspection API remains available for tests and diagnostics.
  The CQRS benchmark compares it with the projection so the scan is visible.

## 2026-10-26 — The outbox is the transaction boundary for events

- The aggregate write and the outbox row commit together in SQLite. Delivery
  is a separate step. A crash between commit and dispatch leaves the event in
  the outbox; a crash after dispatch leaves it pending and it is delivered
  again.
- Delivery is at-least-once. `IdempotentListener` makes side effects
  effectively once per `(listener, event id)` by checking before and recording
  after success.
- If a later listener fails, the whole event is retried; earlier successful
  listeners are skipped because their keys are already recorded.
- `OutboxDispatcher` dead-letters after `maxAttempts` (default 3). There is no
  backoff, scheduler or dead-letter replay command. The lab shows the
  mechanism, not a production operations story.

## 2026-10-26 — Deterministic payment gateway

- `DeterministicPaymentGateway(failEvery)` succeeds always when `failEvery = 0`
  and declines every Nth charge otherwise. The scenario can name the exact
  enrollment that fails.
- No randomness and no network. A real provider's latency, timeouts and
  webhook semantics are explicitly out of scope.
- Billing is idempotent by enrollment id: a retry returns the existing invoice
  decision and does not charge twice. This is tested in
  `tests/Billing/BillingServiceTest.php`.

## 2026-10-26 — Architecture checker is code, with declared limits

- The scanner is token-based rather than a full AST. It reads top-level
  `namespace`, `use`, class/interface/trait/enum declarations and line counts.
  A full parser would add a dependency and hide the mechanism; the lab wants
  the checker to be part of the measured code.
- Rules are import-direction rules, not file-placement rules. A violation is a
  `use` statement that points in a forbidden direction.
- The checker cannot see dynamic class names (`$class = 'ArchitectureLab\\' . ...`),
  and group imports are read as an `use` statement, not expanded. This is a
  gate, not a sandbox.
- Baseline is analysed by a separate layered check and excluded from the clean
  report. The clean report is the contract for the rest of the tree.
- Module metrics are descriptive. `D = 1` on an unconnected leaf module is not
  an instruction to delete it.

## 2026-10-26 — The scenario is the integration contract

- One command, one deterministic seed, one report:
  `make scenario`.
- The scenario intentionally observes the read model before pumping the
  outbox. That catches a projection that is secretly updated during the write
  path.
- It checks eleven invariants: stale window, no pending events, no dead
  letters, write convergence, read/write confirmed and cancelled counts,
  read-model capacity conservation, catalog conservation, no oversubscription,
  notification count and request accounting.
- Additional scenarios are out of scope for `v1.0.0`.

## 2026-10-26 — Benchmarks report environment, not a score

- Every report includes PHP version, OS, architecture, CPU, memory limit,
  cgroup limit, OPcache/JIT/Xdebug state, allocator and commit.
- The harness warms up once, measures five repetitions, reports median/p95/min/
  max/MAD. CI does not enforce a timing threshold: a shared runner is not a
  stable timer.
- Counters are reported separately from time. `php_delta_bytes` is the PHP
  allocator, not RSS; the field name says so everywhere.
- The book quotes one run and names the environment. It does not present the
  numbers as universal constants.

## 2026-10-26 — Book is written after code and measurements freeze

- The seven Russian chapters are written only after `v1.0.0` exists.
- Every code block is an exact excerpt pinned to
  `https://github.com/Researcher86/architecture-lab/blob/v1.0.0/...` with a
  line range; the book checker verifies both the text and the lines.
- Numbers in the book come from this repository's measured output. If a number
  is absent from `docs/BENCHMARKS.md` or a recorded experiment, it does not go
  into the chapter.
- Formatting with Prettier is a separate commit from content changes, and the
  checker runs after formatting.

## 2026-10-27 — Tests are part of the architecture evidence

- The suite covers value objects, aggregate transitions, bus semantics,
  outbox retries/dead letters/idempotency, projections, both runtime modes,
  both persistence adapters, the scenario invariants, the architecture scanner
  and rules, the baseline, the benchmark harness and the experiment harness.
- Differential tests compare in-memory and SQLite decisions instead of
  asserting only one adapter.
- Architecture tests assert the clean tree has zero violations and the
  baseline has exactly the two intended ones. A new accidental import breaks
  the build, not just a diagram.
- No test asserts wall-clock performance. Timing tests would be flaky and would
  turn a measurement into a threshold.
