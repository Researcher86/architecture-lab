<?php

declare(strict_types=1);

namespace ArchitectureLab\Baseline\Layered\Infrastructure;

use ArchitectureLab\Instrumentation\Counters;
use PDO;

final readonly class LayeredCourseRepository
{
    public function __construct(
        private PDO $pdo,
        private Counters $counters,
    ) {
    }

    /**
     * @return array{id: string, title: string, capacity: int, seats_taken: int, price_cents: int}|null
     */
    public function find(string $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM layered_courses WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        ++$this->counters->sqlQueries;

        if (!is_array($row)) {
            return null;
        }

        /** @var array{id: string, title: string, capacity: int, seats_taken: int, price_cents: int} $row */
        return $row;
    }

    public function incrementSeats(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE layered_courses SET seats_taken = seats_taken + 1 WHERE id = :id');
        $statement->execute(['id' => $id]);
        ++$this->counters->sqlQueries;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT * FROM layered_courses ORDER BY id');

        if ($statement === false) {
            throw new \RuntimeException('Unable to read layered courses');
        }

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        ++$this->counters->sqlQueries;

        /** @var list<array<string, mixed>> $rows */
        return $rows;
    }
}
