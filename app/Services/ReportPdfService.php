<?php

namespace App\Services;

use App\Libraries\ReportPeriod;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders a management report as a printable A4 PDF via dompdf. Every report
 * view extends the shared pdf.reports.layout, which carries the society
 * name, report title, period, and generation date in its header.
 */
class ReportPdfService
{
    /**
     * Render the given report view with its data and return the raw PDF
     * document bytes.
     *
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, string $title, ReportPeriod $period, array $data = []): string
    {
        $html = view($view, $data + [
            'title' => $title,
            'period' => $period,
            'orgName' => config('app.name'),
            'generatedAt' => now(),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }
}
