<?php

namespace App\Libraries;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * The reporting window every management report is parameterized by: a month,
 * a quarter, a year, or a custom from/to range. Parsed leniently from the
 * request query string, falling back to the current month so report pages
 * always land on a sensible default.
 */
class ReportPeriod
{
    protected function __construct(
        public readonly string $type,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    /**
     * Build the period from the request's period_type / month / quarter /
     * year / from / to query parameters.
     */
    public static function fromRequest(Request $request): self
    {
        $type = $request->string('period_type')->value();

        try {
            return match ($type) {
                'quarter' => self::quarter(
                    (int) $request->input('year', now()->year),
                    (int) $request->input('quarter', now()->quarter),
                ),
                'year' => self::year((int) $request->input('year', now()->year)),
                'custom' => self::custom(
                    $request->string('from')->value(),
                    $request->string('to')->value(),
                ),
                default => self::month($request->string('month')->value() ?: now()->format('Y-m')),
            };
        } catch (InvalidArgumentException) {
            return self::month(now()->format('Y-m'));
        }
    }

    public static function month(string $month): self
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);

        if ($start === null) {
            throw new InvalidArgumentException("Invalid month [{$month}].");
        }

        return new self('month', $start->startOfMonth(), $start->endOfMonth());
    }

    public static function quarter(int $year, int $quarter): self
    {
        if ($year < 2000 || $year > 2100 || $quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException("Invalid quarter [{$year} Q{$quarter}].");
        }

        $start = CarbonImmutable::create($year, ($quarter - 1) * 3 + 1, 1)->startOfDay();

        return new self('quarter', $start, $start->addMonths(2)->endOfMonth());
    }

    public static function year(int $year): self
    {
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException("Invalid year [{$year}].");
        }

        $start = CarbonImmutable::create($year, 1, 1)->startOfDay();

        return new self('year', $start, $start->endOfYear());
    }

    public static function custom(string $from, string $to): self
    {
        $start = CarbonImmutable::make($from);
        $end = CarbonImmutable::make($to);

        if ($start === null || $end === null || $start->greaterThan($end)) {
            throw new InvalidArgumentException("Invalid custom range [{$from} – {$to}].");
        }

        return new self('custom', $start->startOfDay(), $end->endOfDay());
    }

    /**
     * The billing_month (Y-m) values the period spans, for filtering tables
     * keyed by billing month rather than a date column.
     *
     * @return list<string>
     */
    public function months(): array
    {
        $months = [];
        $cursor = $this->start->startOfMonth();

        while ($cursor->lessThanOrEqualTo($this->end)) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonth();
        }

        return $months;
    }

    /**
     * A human label for report headers, e.g. "July 2026", "Q2 2026",
     * "Year 2026", or "01 Jan 2026 – 15 Mar 2026".
     */
    public function label(): string
    {
        return match ($this->type) {
            'month' => $this->start->format('F Y'),
            'quarter' => 'Q'.$this->start->quarter.' '.$this->start->year,
            'year' => 'Year '.$this->start->year,
            default => $this->start->format('d M Y').' – '.$this->end->format('d M Y'),
        };
    }

    /**
     * A filesystem-safe suffix for export filenames, e.g. "2026-07" or
     * "2026-q2".
     */
    public function fileSuffix(): string
    {
        return match ($this->type) {
            'month' => $this->start->format('Y-m'),
            'quarter' => $this->start->year.'-q'.$this->start->quarter,
            'year' => (string) $this->start->year,
            default => $this->start->format('Y-m-d').'_'.$this->end->format('Y-m-d'),
        };
    }

    /**
     * The period controls to echo back to the Inertia page.
     *
     * @return array{period_type: string, month: string, quarter: int, year: int, from: string, to: string, label: string}
     */
    public function filters(): array
    {
        return [
            'period_type' => $this->type,
            'month' => $this->start->format('Y-m'),
            'quarter' => $this->start->quarter,
            'year' => $this->start->year,
            'from' => $this->start->toDateString(),
            'to' => $this->end->toDateString(),
            'label' => $this->label(),
        ];
    }

    /**
     * The query parameters that reproduce this period on another URL (the
     * PDF/CSV export links).
     *
     * @return array<string, string|int>
     */
    public function queryParameters(): array
    {
        return match ($this->type) {
            'month' => ['period_type' => 'month', 'month' => $this->start->format('Y-m')],
            'quarter' => ['period_type' => 'quarter', 'year' => $this->start->year, 'quarter' => $this->start->quarter],
            'year' => ['period_type' => 'year', 'year' => $this->start->year],
            default => ['period_type' => 'custom', 'from' => $this->start->toDateString(), 'to' => $this->end->toDateString()],
        };
    }
}
