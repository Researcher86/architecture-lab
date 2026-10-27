# Architecture — Modules, Runtime and Data

This document describes the system that the chapters measure. It is not a
promise that the same choices fit a different product; it is the map of this
repository at `v1.0.0`.

## The domain in one paragraph

Catalog publishes a course offering with a capacity and a price. A student asks
Enrollment for a seat. Enrollment reserves a seat in Catalog, stores a pending
enrollment and (in synchronous mode) asks Billing to charge. Billing issues an
invoice, asks a deterministic payment gateway, and publishes success or failure.
Enrollment confirms or cancels, releasing the seat on failure. Reporting folds
the events into two read models, and Notifications records what a student would
have received. The same information is available through the write-side
inspection API, which is deliberately not the read model.

## Module map

The five bounded contexts and the shared kernel are namespaces, not services.

```text
ArchitectureLab\
├── Shared\                 Money, typed ids, clocks, ports, id generator
├── Catalog\                offerings, capacity, seat ownership
│   ├── Domain\             CourseOffering, SeatsExhausted
│   ├── Application\        CatalogService + repository port
│   ├── PublicApi\          CatalogApi, OfferingView, commands, exceptions
│   └── Infrastructure\     DocumentCourseOfferingRepository
├── Enrollment\             a student's attempt to take a course
│   ├── Domain\             Enrollment, EnrollmentStatus
│   ├── Application\        RequestEnrollmentHandler, PaymentResultListener, ports
│   ├── PublicApi\          EnrollmentApi, EnrollmentView, commands, exceptions
│   └── Infrastructure\     document repository, Catalog and Billing ACL adapters
├── Billing\                invoices and payment decisions
│   ├── Domain\             Invoice, InvoiceStatus
│   ├── Application\        BillingService, EnrollmentRequestedListener, ports
│   ├── PublicApi\          BillingApi, PaymentOutcome
│   └── Infrastructure\     document repository, deterministic gateway
├── Reporting\              event-built read models
│   ├── Application\        query handlers
│   ├── Projection\         OfferingCapacityProjection, StudentScheduleProjection
│   └── PublicApi\          views and query objects
├── Notifications\          an observable notification log
├── Eventing\               EventEnvelope, EventBus, Outbox, dispatcher, idempotency
├── Persistence\            in-memory and SQLite adapters for the ports
├── Cqrs\                   CommandBus, QueryBus
├── Integration\            CourseEnrollmentScenario
├── Runtime\                LabRuntime, the composition root
├── Architecture\           scanner, graph, rules, cycles, metrics
└── Baseline\Layered\       the intentional transaction-script baseline
```

`Baseline\Layered` is excluded from the clean report and analysed separately.
It is not an alternative production path; it exists to make the first chapter's
failure modes executable.

## The dependency rule

The checker is not a style guide. It enforces these directions:

| Source | May import | Must not import |
|---|---|---|
| `Domain` | `Shared` | any other `ArchitectureLab` namespace |
| `Application` | own `Domain`, own `Application\Port`, `Shared`, `Eventing` | `Persistence`, own `Infrastructure`, another context |
| `PublicApi` | own context and `Shared` DTOs | another context's internals |
| `Infrastructure` | own context, `Shared`, another context's `PublicApi` | another context's `Domain`/`Application` |
| `Integration` / `Cli` | `PublicApi`, `Runtime`, `Cqrs` | `Domain`, repositories, `Persistence` |

A cross-context adapter is the only place where one context's language touches
another. For example, `Enrollment\Infrastructure\CatalogSeatAllocator` imports
`CatalogApi` and translates Catalog's exception and DTO into Enrollment's
`SeatAllocator` port. `Billing\Application\Event\EnrollmentRequestedListener`
reads the published event payload and translates primitives into Billing's
`Money`, `EnrollmentId` and `OfferingId`; it imports no Enrollment class.

The scanner reads top-level namespace, `use`, class/interface/trait/enum
declarations and line counts. It does not execute code and does not follow
dynamic class names. See `docs/DECISIONS.md` for the limits.

## Runtime composition

`LabRuntime` is the only class that knows both application ports and concrete
adapters. Delivery code receives a `CommandBus`, a `QueryBus`, and inspection
APIs; it never constructs a repository.

```text
LabRuntime::sqlite(':memory:', mode, failEvery)
        │
        ├── SqliteDocumentStore ── Catalog/Enrollment/Billing repositories
        ├── SqliteOutbox ───────── EventRecorder writes
        ├── SqliteProcessedEventStore ── IdempotentListener wrappers
        ├── InMemoryEventBus ────── listeners
        ├── CommandBus ─────────── PublishCourse, RequestEnrollment
        └── QueryBus ───────────── OfferingCapacityQuery, StudentScheduleQuery
```

`inMemory()` uses the same ports with `InMemoryDocumentStore`,
`InMemoryOutbox` and `InMemoryProcessedEventStore`. It proves the core does not
need PDO. It does **not** prove atomicity: those three in-memory objects do not
share a transaction. Only `sqlite()` gives the aggregate write and the outbox
record one database transaction.

## Write path

### Catalog

`CatalogService::publish()` constructs the aggregate, saves it and records
`catalog.course_offering_published` in the same unit of work.
`reserveSeat()` loads the aggregate, calls `reserveSeat()`, saves it and records
`catalog.seat_reserved`. The aggregate enforces `reservedSeats < capacity` and
throws `SeatsExhausted`; the application translates that into the public
`NoSeatsAvailable`.

### Enrollment

`RequestEnrollmentHandler` runs inside a unit of work:

1. ask the `SeatAllocator` port to reserve;
2. create the `Enrollment` aggregate in `pending_payment`;
3. save it;
4. record `enrollment.requested` in the outbox.

In synchronous mode a `PaymentProcessor` adapter is present. The handler calls
Billing after the first transaction, then applies the result in a second
transaction. On success it confirms; on failure it cancels and releases the
seat. The receipt is terminal before the command returns.

In event-driven mode the processor is `null`. The command returns
`pending_payment`, and the outbox dispatcher drives the rest.

### Billing

`BillingService::chargeForEnrollment()` is idempotent by enrollment id:

- if no invoice exists, create one and record `billing.invoice_issued`;
- if the invoice is already paid or failed, return the existing decision;
- otherwise call the gateway, save `paid` or `failed`, record
  `billing.payment_succeeded` or `billing.payment_failed`.

The deterministic gateway increments a counter and declines every Nth charge
when `failEvery > 0`. `failEvery = 0` always succeeds. There is no network,
randomness or real provider.

## Read path (CQRS)

Two buses make the split explicit:

```text
CommandBus::dispatch(object $command): mixed
QueryBus::ask(object $query): mixed
```

`CourseEnrollmentScenario` and the experiments use commands for writes and
queries for reads. The read side never loads an aggregate:

- `OfferingCapacityProjection` folds `course_offering_published`,
  `seat_reserved`, `seat_released`, `enrollment.requested`,
  `enrollment.confirmed` and `enrollment.cancelled` into one document per
  offering with counters.
- `StudentScheduleProjection` keeps one document per student with an entry per
  enrollment and the latest status.
- `OfferingCapacityQueryHandler` and `StudentScheduleQueryHandler` read those
  documents and return DTOs.

The projection uses increments and decrements, not absolute seat counts in
events, so a late or redelivered event cannot overwrite a newer number. The
read model is eventually consistent: the scenario deliberately snapshots it
before pumping the outbox to show the stale window.

The write-side inspection API (`EnrollmentApi::all()`, `forStudent()`) exists
for diagnostics and tests. It is not the CQRS read model; in this lab it still
scans documents. The CQRS benchmark compares the two so the cost is visible.

## Eventing and delivery

```text
aggregate transaction
  ├── save document
  └── INSERT outbox row
            │
            ▼
OutboxDispatcher::dispatch(limit)
  ├── bus.publish(envelope)
  │     ├── IdempotentListener("reporting.offering", ...)
  │     ├── IdempotentListener("reporting.schedule", ...)
  │     ├── IdempotentListener("notifications.enrollment", ...)
  │     ├── Billing listener (event-driven mode)
  │     └── Enrollment payment-result listener (event-driven mode)
  ├── success → mark delivered
  └── failure → attempts += 1; pending or dead after maxAttempts
```

`IdempotentListener` checks `(listener, event id)` before invoking the inner
listener and records the key after success. If a later listener in the same
event throws, the whole event is retried; listeners that already succeeded are
skipped. Delivery is at-least-once; effective side effects are once per
listener and event in the scope of the processed-event store.

The dispatcher does not sleep, back off or run on a timer. `LabRuntime::pump()`
calls it repeatedly until the outbox is empty or a safety bound is reached.
This is enough to make the chain deterministic in one process, and it is
honestly not a production scheduler.

## Data collections

| Collection | Owner | Key | Contents |
|---|---|---|---|
| `catalog_course_offerings` | Catalog | offering id | title, price, starts_at, capacity, reserved_seats |
| `enrollment_enrollments` | Enrollment | enrollment id | student, offering, course title, price, status, timestamps |
| `billing_invoices` | Billing | invoice id | enrollment, student, offering, amount, status, failure |
| `reporting_offering_capacity` | Reporting | offering id | counters by status |
| `reporting_student_schedule` | Reporting | student id | map of enrollment entries |
| `outbox` | Eventing | event id | name, aggregate, payload, attempts, status |
| `processed_events` | Eventing | (listener, event id) | idempotency keys |

The SQLite adapter stores documents as JSON in one table. That is a deliberate
lab simplification: the schema is not the subject, the boundary around it is.

## The scenario as an architecture test

`CourseEnrollmentScenario` checks more than "the code ran":

- the read model is empty before the outbox is pumped (stale window is real);
- no events remain pending and no dead letters exist;
- the write model has no pending payments after convergence;
- read-model confirmed/cancelled counts equal the write model;
- the read model's reserved seats equal confirmed + pending;
- Catalog's reserved seats equal the same number;
- no offering is oversubscribed;
- notification count equals terminal enrollments;
- accepted requests equal stored enrollments.

This is the integration test that catches a broken context translation: the
counts only agree if Catalog, Enrollment, Billing and Reporting use the same
enrollment identity and the same events.

## Baseline: what the first chapter measures

`Baseline\Layered` is a small transaction script:

- `LayeredEnrollmentService` imports `PDO`, owns `beginTransaction`/`commit`,
  knows the table shape and calls two concrete repositories;
- `LayeredEnrollmentController` calls a repository directly in `report()`,
  skipping the application layer;
- `CourseFull` and `DuplicateEnrollment` live in a "domain" namespace that is
  not isolated.

The clean analyzer excludes this tree. The layered analyzer reports exactly
the two intended violations and prints them in the experiment. Everything else
in the repository is held to the clean rule.
