<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import MeterReaderLayout from '@/layouts/MeterReaderLayout.vue';
import { index as meterReadingsIndex } from '@/routes/meter-readings';

defineOptions({ layout: MeterReaderLayout });

type PresentedEntry = {
  id: number;
  type: string;
  date: string;
  document_number: string | null;
  description: string | null;
  amount: number;
};

type BillBreakdown = {
  billing_category?: string | null;
  reading: {
    previous_value: number;
    current_value: number;
    consumption: number;
    reading_date: string;
    billing_month: string;
  };
  tiers: Array<{
    lower_units: number;
    upper_units: number | null;
    units: number;
    rate_per_unit: number;
    amount: number;
  }>;
  usage_charge: number;
  service_charge: number;
  presented_entries: PresentedEntry[];
  summary: {
    previous_due: number;
    payments: number;
    credits: number;
    debits: number;
    this_month: number;
    previous_balance: number;
    total_due: number;
  };
};

const props = defineProps<{
  bill: {
    id: number;
    bill_number: string;
    billing_month: string;
    month_label: string;
    is_current: boolean;
    usage_charge: number;
    service_charge: number;
    monthly_charge: number;
    previous_balance: number;
    total_due: number;
    breakdown: BillBreakdown;
    status: string;
    due_date: string;
    issued_at: string;
    is_reissue: boolean;
    supersedes_bill_number: string | null;
    generated_by: string;
  };
  account: {
    id: number;
    owner_name: string;
    account_number: string;
    meter_number: string;
    connection_address: string | null;
  };
  org: {
    name: string;
  };
}>();

setLayoutProps({
  title: props.bill.bill_number,
  backHref: meterReadingsIndex().url,
});

const statusMeta: Record<
  string,
  {
    label: string;
    variant: 'default' | 'secondary' | 'destructive' | 'outline';
  }
> = {
  generated: { label: 'Generated', variant: 'secondary' },
  approved: { label: 'Approved', variant: 'default' },
  paid: { label: 'Paid', variant: 'default' },
  overdue: { label: 'Overdue', variant: 'destructive' },
};

const status = computed(
  () =>
    statusMeta[props.bill.status] ?? {
      label: props.bill.status,
      variant: 'outline' as const,
    },
);

const entryTypeLabels: Record<string, string> = {
  water_charge: 'Water charge',
  charge: 'Charge',
  penalty: 'Penalty',
  adjustment: 'Adjustment',
  payment: 'Payment',
  reversal: 'Reversal',
};

const debitEntries = computed(() =>
  props.bill.breakdown.presented_entries.filter((entry) => entry.amount > 0),
);

const entryLabel = (entry: PresentedEntry): string =>
  entry.description ?? entryTypeLabels[entry.type] ?? entry.type;

const formatAmount = (value: number): string =>
  new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Math.abs(Number(value) || 0));

const formatRs = (value: number): string => `Rs ${formatAmount(value)}`;

const formatLkr = (value: number): string => `රු. ${formatAmount(value)}`;

const formatUnits = (value: number): string =>
  new Intl.NumberFormat('en-US').format(Number(value) || 0);

const printBill = (): void => {
  window.print();
};
</script>

<template>
  <div>
    <Head :title="`Bill ${bill.bill_number}`" />

    <div class="flex flex-1 flex-col gap-4 px-4 pt-3 pb-6 print:hidden">
      <div class="flex flex-col gap-3 rounded-2xl border p-4">
        <div class="flex items-center justify-between gap-2">
          <div class="flex min-w-0 flex-col gap-0.5">
            <p class="truncate font-semibold">{{ bill.bill_number }}</p>
            <p class="text-xs text-muted-foreground">
              {{ bill.month_label }} · {{ account.account_number }}
            </p>
          </div>
          <Badge :variant="status.variant" class="shrink-0">
            {{ status.label }}
          </Badge>
        </div>

        <p
          v-if="bill.is_reissue"
          class="text-xs font-medium text-muted-foreground"
        >
          Reissued — supersedes {{ bill.supersedes_bill_number }}
        </p>
        <p v-if="!bill.is_current" class="text-xs font-medium text-destructive">
          This bill has been superseded and is no longer payable.
        </p>

        <div class="flex items-baseline justify-between gap-2">
          <span class="text-sm text-muted-foreground">Total due</span>
          <span class="text-xl font-bold tabular-nums">
            <template v-if="bill.total_due < 0">CR </template>
            {{ formatRs(bill.total_due) }}
          </span>
        </div>

        <Button size="lg" class="w-full" @click="printBill">
          <Printer class="size-4" />
          Print bill
        </Button>
        <Button variant="outline" class="w-full" as-child>
          <Link :href="meterReadingsIndex().url">
            <ArrowLeft class="size-4" />
            Back to meter readings
          </Link>
        </Button>
      </div>
    </div>

    <div class="flex justify-center pb-8 print:pb-0">
      <div class="ticket bg-white shadow-md dark:bg-white print:shadow-none">
        <div class="head relative z-40">
          <div class="center bold" style="font-size: 18px">{{ org.name }}</div>
          <div class="center" style="font-size: 12px">මාසික ජල බිල්පත</div>
        </div>
        <div class="hr"></div>

        <div class="bill-info relative z-40">
          <div class="row">
            <div class="sin">බිල් අංකය:</div>
            <div class="bold">{{ bill.bill_number }}</div>
          </div>
          <div class="row">
            <div class="sin">මාසය:</div>
            <div>{{ bill.month_label }}</div>
          </div>
          <div class="row">
            <div class="sin">නිකුත් කල දිනය:</div>
            <div>{{ bill.issued_at }}</div>
          </div>
          <div class="row">
            <div class="sin">ගෙවිය යුතු දිනය:</div>
            <div class="bold">{{ bill.due_date }}</div>
          </div>
          <div v-if="bill.is_reissue" class="center bold mt-1">
            නැවත නිකුත් කළ බිල්පතකි — {{ bill.supersedes_bill_number }}
          </div>
        </div>
        <div class="hr"></div>

        <div class="cust-info kv-block relative z-40">
          <div class="row">
            <div class="sin">ගිණුම් අංකය:</div>
            <div class="bold">{{ account.account_number }}</div>
          </div>
          <div class="row">
            <div class="sin">මීටර් අංකය:</div>
            <div>{{ account.meter_number }}</div>
          </div>
          <div v-if="bill.breakdown.billing_category" class="row">
            <div class="sin">ගාස්තු කාණ්ඩය:</div>
            <div>{{ bill.breakdown.billing_category }}</div>
          </div>
          <div class="kv-label">නම:</div>
          <div class="kv-value">{{ account.owner_name }}</div>
          <div v-if="account.connection_address" class="kv-label">ලිපිනය:</div>
          <div v-if="account.connection_address" class="kv-value">
            {{ account.connection_address }}
          </div>
        </div>
        <div class="hr"></div>

        <div class="relative z-40">
          <div class="topic">මීටර් කියවීම</div>
          <div class="row">
            <div class="sin">පෙර කියවීම:</div>
            <div>{{ formatUnits(bill.breakdown.reading.previous_value) }}</div>
          </div>
          <div class="row">
            <div class="sin">වත්මන් කියවීම:</div>
            <div>{{ formatUnits(bill.breakdown.reading.current_value) }}</div>
          </div>
          <div class="row">
            <div class="sin">කියවූ දිනය:</div>
            <div>{{ bill.breakdown.reading.reading_date }}</div>
          </div>
          <div class="row">
            <div class="sin bold">පරිභෝජනය (ඒකක):</div>
            <div class="bold">
              {{ formatUnits(bill.breakdown.reading.consumption) }}
            </div>
          </div>
        </div>
        <div class="hr"></div>

        <div class="accounts relative z-40">
          <div class="topic">පෙර බිල් හා ගෙවීම්</div>
          <table class="amounts">
            <tbody>
              <tr>
                <td>පෙර ගෙවිය යුතු මුදල</td>
                <td class="right">
                  <span v-if="bill.breakdown.summary.previous_due < 0">CR</span>
                  {{ formatLkr(bill.breakdown.summary.previous_due) }}
                </td>
              </tr>
              <tr>
                <td>ගෙවීම්</td>
                <td class="right">
                  - {{ formatLkr(bill.breakdown.summary.payments) }}
                </td>
              </tr>
              <tr v-if="bill.breakdown.summary.credits > 0">
                <td>බැර</td>
                <td class="right">
                  - {{ formatLkr(bill.breakdown.summary.credits) }}
                </td>
              </tr>
              <tr v-for="entry in debitEntries" :key="entry.id">
                <td>
                  {{ entryLabel(entry) }}
                  <span class="tiny">({{ entry.date }})</span>
                </td>
                <td class="right">+ {{ formatLkr(entry.amount) }}</td>
              </tr>
              <tr>
                <td class="bold">හිග මුදල</td>
                <td class="right bold border-bor">
                  <span v-if="bill.breakdown.summary.previous_balance < 0">
                    CR
                  </span>
                  {{ formatLkr(bill.breakdown.summary.previous_balance) }}
                </td>
              </tr>
            </tbody>
          </table>

          <div class="hr"></div>

          <div class="topic">මෙම මාසයේ</div>
          <table class="amounts">
            <tbody>
              <tr>
                <td>ජල ගාස්තුව</td>
                <td class="right">
                  {{ formatLkr(bill.breakdown.usage_charge) }}
                </td>
              </tr>
              <tr v-if="bill.breakdown.service_charge > 0">
                <td>සේවා ගාස්තුව</td>
                <td class="right">
                  {{ formatLkr(bill.breakdown.service_charge) }}
                </td>
              </tr>
              <tr>
                <td class="bold">මේ මස එකතුව</td>
                <td class="right bold">
                  {{ formatLkr(bill.monthly_charge) }}
                </td>
              </tr>
            </tbody>
          </table>

          <div class="hr"></div>

          <table class="amounts">
            <tfoot>
              <tr>
                <td class="topic">ගෙවිය යුතු මුළු මුදල</td>
                <td class="right topic">
                  <span v-if="bill.breakdown.summary.total_due < 0">CR</span>
                  {{ formatLkr(bill.breakdown.summary.total_due) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
        <div class="hr"></div>

        <div class="sign relative z-40">
          <div class="topic">ගෙවීම් සහතික කිරීම</div>
          <div class="row py-2">
            <div class="sin">මුදල</div>
            <div class="sin">.....................................</div>
          </div>
          <div class="row pb-2">
            <div class="sin">දිනය</div>
            <div class="sin">.....................................</div>
          </div>
          <div class="row pb-2">
            <div class="sin">භාණ්ඩාගාරික</div>
            <div class="sin">.....................................</div>
          </div>
        </div>
        <div class="hr"></div>

        <div class="no-break relative z-40">
          <div class="topic">නිවේදන:</div>
          <div class="mt-1 leading-tight">
            {{ bill.due_date }} දිනට පෙර භාණ්ඩාගාරික වෙත ගෙවීම් සිදු කරන්න.
          </div>
        </div>
        <div class="hr"></div>

        <div class="row tiny relative z-40">
          <div>Served by: {{ bill.generated_by }}</div>
          <div>{{ bill.issued_at }}</div>
        </div>
        <div class="center tiny mt-1">Thank you!</div>

        <div v-if="!bill.is_current" class="dis-notice z-10">අවලංගුයි</div>

        <div class="cut-line" style="margin-top: 20px"></div>
      </div>
    </div>
  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Noto+Serif+Sinhala:wght@100..900&display=swap');

@page {
  size: 78mm auto;
  margin: 0;
}

.ticket {
  position: relative;
  width: 78mm;
  padding: 2mm 3mm 2mm 1mm;
  font-family:
    'Noto Serif Sinhala',
    ui-sans-serif,
    system-ui,
    -apple-system,
    'Segoe UI',
    Roboto,
    'Helvetica Neue',
    Arial,
    'Noto Sans',
    'Liberation Sans',
    serif;
  line-height: 1.1;
  font-size: 11px;
  color: #000;
}

.ticket .center {
  text-align: center;
}

.ticket .right {
  text-align: right;
}

.ticket .bold {
  font-weight: 700;
}

.ticket .tiny {
  font-size: 9px;
}

.ticket .mt-1 {
  margin-top: 1mm;
}

.ticket .sin {
  font-size: 11px;
}

.ticket .topic {
  font-size: 12px;
  font-weight: 700;
}

.ticket .hr {
  border-top: 1px dashed #000;
  margin: 2.5mm 0;
}

.ticket .row {
  display: flex;
  justify-content: space-between;
  gap: 4mm;
}

.ticket table {
  width: 100%;
  border-collapse: collapse;
}

.ticket td,
.ticket th {
  padding: 2px 0;
  vertical-align: top;
}

.ticket .amounts td:first-child {
  width: 60%;
}

.ticket .amounts tfoot td {
  padding-top: 2px;
}

.ticket .border-bor {
  border-top: 1px solid #000;
}

.ticket .kv-block {
  display: grid;
  grid-template-columns: 1fr;
  grid-row-gap: 2px;
}

.ticket .kv-label {
  font-weight: 700;
}

.ticket .kv-value {
  padding-left: 5mm;
}

.ticket .no-break {
  page-break-inside: avoid;
}

.ticket .cut-line {
  margin: 3mm 0;
  border-top: 1px dashed #000;
  position: relative;
}

.ticket .cut-line:before {
  content: '✂';
  position: absolute;
  top: -7px;
  left: 50%;
  transform: translateX(-50%);
  background: #fff;
  padding: 0 3px;
}

.ticket .dis-notice {
  font-size: 28px;
  font-weight: 700;
  position: absolute;
  top: 50%;
  width: 100%;
  text-align: center;
  rotate: 315deg;
  z-index: 1;
  color: #ff000050;
}

@media print {
  body * {
    visibility: hidden;
  }

  .ticket,
  .ticket * {
    visibility: visible;
  }

  .ticket {
    position: absolute;
    top: 0;
    left: 0;
    margin: 0;
  }

  a[href]:after {
    content: '';
  }
}
</style>
