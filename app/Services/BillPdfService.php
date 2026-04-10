<?php

namespace App\Services;

use App\Models\Bill;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Renders a bill as a Sinhala PDF in the same 78mm ticket layout the bill
 * page prints, for digital delivery (WhatsApp). Uses mPDF because Sinhala
 * needs OpenType shaping (vowel reordering, conjuncts) that dompdf cannot
 * do, with the ticket's Noto Serif Sinhala face (a pre-2022 build — newer
 * ones use MarkGlyphSets, which mPDF cannot parse).
 */
class BillPdfService
{
    protected const TICKET_WIDTH_MM = 78;

    protected const MARGIN_MM = 3;

    /**
     * Render the bill PDF and return the raw document bytes.
     */
    public function render(Bill $bill): string
    {
        $bill->loadMissing(['waterAccount.owner', 'generator:id,first_name,last_name', 'supersededBill:id,bill_number']);

        $html = view('pdf.bill', [
            'bill' => $bill,
            'account' => $bill->waterAccount,
            'orgName' => config('app.name'),
            'monthLabel' => Carbon::createFromFormat('Y-m', $bill->billing_month)->format('F Y'),
            'payUrl' => route('pay.show', [
                'account' => $bill->waterAccount->account_number,
                'meter' => $bill->waterAccount->meter_number,
            ]),
        ])->render();

        // First pass on an oversized page to measure the ticket, second pass
        // on a page trimmed to the content — matching the printed ticket's
        // continuous-roll auto length.
        $measure = $this->writeTicket($html, 600);

        if ($measure->page === 1) {
            $trimmed = $this->writeTicket($html, ceil($measure->y) + self::MARGIN_MM);

            return $trimmed->Output('', Destination::STRING_RETURN);
        }

        return $measure->Output('', Destination::STRING_RETURN);
    }

    /**
     * Write the ticket HTML onto a fresh document of the given page height.
     */
    protected function writeTicket(string $html, float $heightMm): Mpdf
    {
        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $defaultConfig = (new ConfigVariables)->getDefaults();
        $defaultFontConfig = (new FontVariables)->getDefaults();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => [self::TICKET_WIDTH_MM, $heightMm],
            'margin_left' => self::MARGIN_MM,
            'margin_right' => self::MARGIN_MM,
            'margin_top' => self::MARGIN_MM,
            'margin_bottom' => self::MARGIN_MM,
            'fontDir' => array_merge($defaultConfig['fontDir'], [resource_path('fonts')]),
            'fontdata' => $defaultFontConfig['fontdata'] + [
                'notoserifsinhala' => [
                    'R' => 'NotoSerifSinhala-Regular.ttf',
                    'B' => 'NotoSerifSinhala-Bold.ttf',
                    'useOTL' => 0xFF,
                ],
            ],
            'default_font' => 'notoserifsinhala',
            // The archived Noto Serif Sinhala build carries no Latin glyphs;
            // substitute them from FreeSerif, as the browser print's font
            // stack does with system fonts.
            'useSubstitutions' => true,
            'backupSubsFont' => ['freeserif'],
            'tempDir' => $tempDir,
        ]);

        $mpdf->WriteHTML($html);

        return $mpdf;
    }
}
