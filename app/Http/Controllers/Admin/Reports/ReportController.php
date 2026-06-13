<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Libraries\ReportPeriod;
use App\Services\ReportPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Base for the management report controllers (docs/management-reports.md).
 * Each report resolves its period from the request, renders an Inertia page
 * on screen, and offers the same dataset as an A4 PDF and a CSV download.
 */
abstract class ReportController extends Controller
{
    /** The report title shown in the UI, PDF header, and export filenames. */
    abstract protected function title(): string;

    /**
     * Resolve the requested reporting period, defaulting to this month.
     */
    protected function period(Request $request): ReportPeriod
    {
        return ReportPeriod::fromRequest($request);
    }

    /**
     * Download the given report view as an A4 PDF.
     *
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    protected function pdfResponse(string $view, ReportPeriod $period, array $data): Response
    {
        $document = app(ReportPdfService::class)->render($view, $this->title(), $period, $data);

        return response($document, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->exportFilename($period, 'pdf').'"',
        ]);
    }

    /**
     * Stream the given rows as a CSV download.
     *
     * @param  list<string>  $columns
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     */
    protected function csvResponse(ReportPeriod $period, array $columns, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new RuntimeException('Unable to open the output stream for the CSV export.');
            }

            fputcsv($handle, $columns);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $this->exportFilename($period, 'csv'), ['Content-Type' => 'text/csv']);
    }

    /**
     * The export filename, e.g. "billing-summary-2026-07.pdf".
     */
    protected function exportFilename(ReportPeriod $period, string $extension): string
    {
        return Str::slug($this->title()).'-'.$period->fileSuffix().'.'.$extension;
    }
}
