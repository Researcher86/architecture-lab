<?php

declare(strict_types=1);

namespace ArchitectureLab\Experiment;

use InvalidArgumentException;

/**
 * Validated options for an experiment.
 *
 * The names are shared by every experiment; a descriptor declares which ones
 * it actually reads. An unknown or misplaced option is refused instead of
 * being silently ignored, because a typo in a book command must fail loudly.
 */
final readonly class Options
{
    public const KNOWN = [
        'seed' => 'seed for a reproducible scenario',
        'students' => 'number of student enrollment attempts',
        'capacity' => 'seats per course offering',
        'offerings' => 'number of course offerings',
        'fail-every' => 'fail every Nth payment, 0 disables failures',
        'events' => 'number of synthetic events to publish',
        'duplicates' => 'number of duplicate deliveries to inject',
        'limit' => 'maximum number of messages handled in one pass',
        'mode' => 'workflow mode: sync or event-driven',
        'size' => 'generic sample size for an experiment',
    ];

    private const POSITIVE_INTEGER_OPTIONS = [
        'students',
        'capacity',
        'offerings',
        'events',
        'duplicates',
        'limit',
        'size',
    ];

    /**
     * @param array<string, int|string> $values
     */
    private function __construct(
        private array $values,
    ) {
    }

    /**
     * @param array<string, string> $raw
     *
     * @throws InvalidArgumentException on an unknown option or an invalid value
     */
    public static function fromStrings(array $raw): self
    {
        $values = [];

        foreach ($raw as $name => $value) {
            if (!isset(self::KNOWN[$name])) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown option --%s. Known options: %s',
                    $name,
                    implode(', ', array_keys(self::KNOWN)),
                ));
            }

            if (in_array($name, self::POSITIVE_INTEGER_OPTIONS, true)) {
                if (preg_match('/^\d+$/', $value) !== 1 || (int) $value < 1) {
                    throw new InvalidArgumentException(sprintf(
                        '--%s must be a positive integer, got "%s"',
                        $name,
                        $value,
                    ));
                }

                $values[$name] = (int) $value;

                continue;
            }

            if ($name === 'fail-every') {
                if (preg_match('/^\d+$/', $value) !== 1) {
                    throw new InvalidArgumentException(sprintf(
                        '--fail-every must be zero or a positive integer, got "%s"',
                        $value,
                    ));
                }

                $values[$name] = (int) $value;

                continue;
            }

            if ($name === 'mode') {
                if (!in_array($value, ['sync', 'event-driven'], true)) {
                    throw new InvalidArgumentException(sprintf(
                        '--mode must be sync or event-driven, got "%s"',
                        $value,
                    ));
                }

                $values[$name] = $value;

                continue;
            }

            if (trim($value) === '') {
                throw new InvalidArgumentException(sprintf('--%s cannot be empty', $name));
            }

            $values[$name] = $value;
        }

        return new self($values);
    }

    public static function none(): self
    {
        return new self([]);
    }

    public function seed(string $default): string
    {
        $value = $this->values['seed'] ?? $default;

        return (string) $value;
    }

    public function students(int $default): int
    {
        return $this->positive('students', $default);
    }

    public function capacity(int $default): int
    {
        return $this->positive('capacity', $default);
    }

    public function offerings(int $default): int
    {
        return $this->positive('offerings', $default);
    }

    public function failEvery(int $default): int
    {
        return (int) ($this->values['fail-every'] ?? $default);
    }

    public function events(int $default): int
    {
        return $this->positive('events', $default);
    }

    public function duplicates(int $default): int
    {
        return $this->positive('duplicates', $default);
    }

    public function limit(int $default): int
    {
        return $this->positive('limit', $default);
    }

    public function size(int $default): int
    {
        return $this->positive('size', $default);
    }

    public function mode(string $default): string
    {
        return (string) ($this->values['mode'] ?? $default);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->values);
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    private function positive(string $name, int $default): int
    {
        return (int) ($this->values[$name] ?? $default);
    }
}
