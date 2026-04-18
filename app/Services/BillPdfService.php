<?php

namespace App\Services;

use App\Models\Bill;
use Illuminate\Support\Carbon;

/**
 * Renders a bill as a Sinhala PDF in the same 78mm ticket layout the bill
 * page prints, for digital delivery (WhatsApp).
 */
class BillPdfService extends TicketPdfService
{
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

        return $this->renderTicket($html);
    }
}
