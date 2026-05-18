<?php

namespace App\Services;

use App\Models\RunningNumber;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RunningNumberService
{
    /**
     * The document number series and their printed prefixes (D-24).
     * Phase 4 adds 'receipt'.
     *
     * @var array<string, string>
     */
    protected const array SERIES = [
        'bill' => 'BILL',
        'charge' => 'CHG',
        'journal' => 'JRN',
        'receipt' => 'RCPT',
        'complaint' => 'CMP',
        'job' => 'JOB',
        'expense' => 'EXP',
    ];

    /**
     * Issue the next number in a series, e.g. "BILL-2026-00042".
     *
     * The counter row is incremented under a row lock so numbers are gapless
     * and race-free; call inside the posting transaction so a rollback also
     * releases the number.
     */
    public function next(string $key): string
    {
        if (! array_key_exists($key, self::SERIES)) {
            throw new InvalidArgumentException("Unknown running number series [{$key}].");
        }

        return DB::transaction(function () use ($key): string {
            $counter = RunningNumber::query()->where('key', $key)->lockForUpdate()->first();

            if ($counter === null) {
                RunningNumber::query()->firstOrCreate(
                    ['key' => $key],
                    ['prefix' => self::SERIES[$key], 'year' => now()->year, 'last_number' => 0],
                );

                $counter = RunningNumber::query()->where('key', $key)->lockForUpdate()->firstOrFail();
            }

            if ($counter->year !== now()->year) {
                $counter->year = now()->year;
                $counter->last_number = 0;
            }

            $counter->last_number++;
            $counter->save();

            return sprintf('%s-%d-%05d', $counter->prefix, $counter->year, $counter->last_number);
        });
    }
}
