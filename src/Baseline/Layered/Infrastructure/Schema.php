<?php

declare(strict_types=1);

namespace ArchitectureLab\Baseline\Layered\Infrastructure;

use PDO;

/** The baseline schema is deliberately flat: one table carries every concept. */
final class Schema
{
    public static function create(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS layered_courses (
                id TEXT PRIMARY KEY,
                title TEXT NOT NULL,
                capacity INTEGER NOT NULL,
                seats_taken INTEGER NOT NULL DEFAULT 0,
                price_cents INTEGER NOT NULL
            )',
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS layered_enrollments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                student_id TEXT NOT NULL,
                course_id TEXT NOT NULL,
                course_title TEXT NOT NULL,
                status TEXT NOT NULL,
                paid INTEGER NOT NULL,
                amount_cents INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                UNIQUE (student_id, course_id)
            )',
        );
    }

    private function __construct()
    {
    }
}
