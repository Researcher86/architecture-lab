# Complexity — Cost Models, Counters and What They Mean

This lab measures architectural cost, not asymptotic complexity of a single
algorithm. The useful questions are still quantitative: how many statements
does one enrollment issue, how many events does one flow produce, how many
document reads does a query need, and which part of the graph is actually
connected.

## Three kinds of evidence

| Evidence | Question it answers | Where it comes from |
|---|---|---|
| Code shape | What is allowed to depend on what? | Imports, module map, dependency rules |
| Counters | What did one operation actually do? | `storeReads`, `storeWrites`, `sqlQueries`, `eventsPublished`, `eventHandlers`, `projectionUpdates`, `handlerSkips` |
| Time | What did this machine charge for it? | Experiments and benchmarks with repetitions and environment |

Time alone cannot prove a boundary is useful: a slower path may be the one
that keeps a context replaceable. Imports alone cannot prove a design is
correct: the checker sees static `use` statements, not runtime wiring. Both are
reported, and neither is turned into a single score.

## Counter vocabulary

| Counter | Meaning in this lab |
|---|---|
| `storeReads` | calls to `DocumentStore::get`/`all`; `all()` adds one per returned document |
| `storeWrites` | calls to `DocumentStore::put` |
| `sqlQueries` | PDO statements issued by the SQLite adapters, including schema and outbox operations |
| `repositoryCalls` | repository methods that reach the store |
| `eventsPublished` | events handed to the in-process bus by the dispatcher |
| `eventHandlers` | inner listeners invoked after the idempotency check |
| `projectionUpdates` | read-model documents written |
| `handlerSkips` | idempotent deliveries suppressed |
| `aclTranslations` | reserved for explicit ACL accounting; the current adapters do not increment it |
| `moduleCalls` | reserved for module-boundary accounting; the current runtime does not increment it |

The last two are declared but not used. That is visible here instead of being
presented as if every counter were populated.

## Write-path cost

### Layered baseline

One `LayeredEnrollmentService::enroll()` performs:

```text
duplicate check           1 SELECT
begin transaction         1 statement (counted by the service)
course read               1 SELECT
enrollment insert         1 INSERT
seat increment            1 UPDATE
commit                    1 statement (counted by the service)
```

So the measured 200 enrollments produce **1,200 statements**, plus one report
query = **1,201**. The cost is linear in the number of enrollments, with a
constant of six statements per enrollment. It is not "bad because it is six";
it is bad here because the service owns SQL, the transaction and two concrete
repositories at once.

### Clean write path

The same operation crosses explicit seams:

```text
RequestEnrollmentHandler
  → SeatAllocator::reserve                 (port)
      → CatalogApi::offering + reserveSeat (public API)
          → repository + outbox            (inside a unit of work)
  → EnrollmentRepository::save
  → Outbox::record(enrollment.requested)
```

In synchronous mode the handler then calls `PaymentProcessor::charge`, which
crosses Billing's public API and applies the result in a second transaction.
The number of statements is higher than the baseline because each aggregate
is loaded and stored through a document adapter, and the outbox is a real
table. The gain is not a lower constant; it is that each step can be replaced
or tested through a port.

## Event count per accepted enrollment

For one accepted request in the scenario:

```text
catalog.seat_reserved
enrollment.requested
billing.invoice_issued
billing.payment_succeeded | billing.payment_failed
enrollment.confirmed      | enrollment.cancelled
```

That is five events. Publishing two offerings adds two
`catalog.course_offering_published` events. The scenario measured:

| Mode | Accepted | Published events | Delivered |
|---|---:|---:|---:|
| event-driven | 8 | 2 | 42 = 2 + 8×5 |
| sync | 10 | 2 | 52 = 2 + 10×5 |

Event-driven and sync produce the same five facts per accepted enrollment; the
difference is *when* Billing runs and how many requests fit before the seats
are exhausted. In sync mode a declined payment releases its seat immediately,
so two more students are accepted. That is an architectural consequence visible
in a counter, not an implementation detail.

## Read-path cost

### Projection query

`StudentScheduleQueryHandler` reads one document:

```text
storeReads = 1
work       = O(entries for one student)
```

`OfferingCapacityQueryHandler` also reads one document. Five rounds over 100
students plus one offering therefore produced **505 store reads**, matching
`5 × (100 + 1)`. The query cost is independent of the total number of
students in the system.

### Write-side scan

`DocumentEnrollmentRepository::forStudent()` calls `all()` and filters in PHP.
The document store's `all()` counts one read per returned document, so the same
five rounds also reported **505 store reads** — but each read hydrated an
enrollment and then filtered it. Time exposed the difference: 2.841 ms for the
projection versus 74.785 ms for the write scan, a 26.3× gap in that run.

This is the honest version of CQRS: the read model does not reduce the number
of store calls in this toy adapter; it reduces the amount of work after the
call. A real indexed database would make the difference a scan versus an index
lookup.

### Ordered entry cost

`StudentScheduleProjection` stores entries in a map keyed by enrollment id.
A redelivered `enrollment.confirmed` overwrites the entry; it does not append a
duplicate. The query handler sorts entries by id before returning, so the
response order is stable even though the document is a map. Sorting is
`O(k log k)` for `k` entries of one student.

## Module graph metrics

The analyzer reports, for each module:

```text
Ca = afferent couplings (modules that import this module)
Ce = efferent couplings (modules this module imports)
I  = Ce / (Ca + Ce)         instability: 0 stable, 1 unstable
A  = interfaces / classes   abstractness
D  = |A + I - 1|            distance from the main sequence
```

The measured clean tree (127 files, 247 internal edges, 5 modules) produced:

| Module | Classes | Interfaces | Ca | Ce | I | A | D |
|---|---:|---:|---:|---:|---:|---:|---:|
| Billing | 11 | 3 | 1 | 0 | 0.000 | 0.273 | 0.727 |
| Catalog | 10 | 2 | 1 | 0 | 0.000 | 0.200 | 0.800 |
| Enrollment | 20 | 4 | 0 | 2 | 1.000 | 0.200 | 0.200 |
| Notifications | 1 | 0 | 0 | 0 | 0.000 | 0.000 | 1.000 |
| Reporting | 9 | 0 | 0 | 0 | 0.000 | 0.000 | 1.000 |

These numbers are descriptive, not grades:

- Billing and Catalog are imported through their `PublicApi` by Enrollment
  infrastructure, so they have `Ca = 1` and no outgoing context edges.
- Enrollment has `Ce = 2` because its adapters import Catalog's and Billing's
  public APIs.
- Notifications and Reporting have no import edges at all: the runtime
  subscribes their listeners. An import-based graph cannot see event
  subscriptions, which is exactly why the runtime is documented separately.
- `D = 1` for a leaf module with no interfaces is expected. It says "this
  module is concrete and unconnected in the import graph", not "delete it".

The clean report checks **0 violations, 0 cycles**. The baseline report checks
**2 violations** in 7 files / 316 lines.

## Dependency-graph cost

The scanner reads `F` files and emits `E` internal import edges. The analyzer:

1. scans files and tokenises them: `O(source size)`;
2. builds edges: `O(E)`;
3. checks rules: `O(E)` per pass;
4. computes module metrics: `O(E + M log M)` for `M` modules (the sort);
5. runs Tarjan's SCC: `O(M + E)`.

The measured `clean.scan` benchmark ran three complete scans per repetition and
reported a median of 95.163 ms per repetition (**~31.7 ms per scan**) for 127
files and 247 edges. That includes PHP tokenisation of every file, not just
graph traversal, so it is a gate cost rather than a graph-theory cost.

## Test and invariant cost

The suite runs 172 tests / 1,270 assertions in under a second in the
development container. The important ones are not timed:

- value-object boundary tests (`Money`, ids, clocks);
- aggregate transition tests (cannot confirm twice, cannot cancel confirmed);
- differential tests: in-memory vs SQLite adapters must make the same decisions;
- eventing invariants: retries do not duplicate effects, failures dead-letter,
  duplicate publishes increment `handlerSkips`;
- scenario invariants: stale window, convergence, capacity conservation,
  catalog conservation, notification count;
- architecture invariants: clean tree 0 violations/0 cycles, baseline exactly 2
  intended violations.

## What is deliberately not claimed

- No `O(1)` claim for document-store access: SQLite queries are not constant
  time, and `all()` is linear in the collection size.
- No claim that module metrics predict maintenance cost. They are a reading
  aid with a small, documented graph.
- No claim that the event-driven path is faster. The measured difference was
  within a noisy run and the event-driven path delivers more facts per
  accepted request in sync mode because more requests fit.
- No RSS or native-memory claim; only PHP allocator deltas are reported.
- No concurrency analysis. The lab has one process and one SQLite connection.
