<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Paperclip, Plus, Printer } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { collect, index } from '@/routes/admin/payments';
import { show as collectShow } from '@/routes/admin/payments/collect';
import { print as receiptPrint } from '@/routes/admin/payments/receipt';

defineProps<{
  payment: {
    id: number;
    receipt_number: string;
    amount: number;
    method: string;
    reference: string | null;
    destination: string | null;
    paid_at: string;
    balance_after: number;
    recorded_by: string | null;
    attachment_url: string | null;
  };
  account: {
    id: number;
    owner_name: string;
    account_number: string;
    meter_number: string;
  };
  org: {
    name: string;
  };
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Payments',
        href: index(),
      },
      {
        title: 'Receipt',
        href: '#',
      },
    ],
  },
});

const formatAmount = (value: number): string =>
  new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Math.abs(Number(value) || 0));

const formatRs = (value: number): string => `Rs ${formatAmount(value)}`;

const formatLkr = (value: number): string => `රු. ${formatAmount(value)}`;
</script>

<template>
  <div class="flex flex-col gap-6 p-4">
    <Head :title="`Receipt ${payment.receipt_number}`" />

    <div class="flex flex-wrap items-start justify-between gap-4">
      <Heading
        variant="small"
        :title="`Receipt ${payment.receipt_number}`"
        :description="`${payment.paid_at} — ${account.account_number} · ${account.owner_name}`"
      />

      <div class="flex flex-wrap gap-2">
        <Button variant="outline" as-child>
          <Link :href="collect()">
            <ArrowLeft class="size-4" />
            Back to collection
          </Link>
        </Button>

        <Button variant="outline" as-child>
          <Link :href="collectShow(account.id)">
            <Plus class="size-4" />
            New payment for this account
          </Link>
        </Button>

        <Button v-if="payment.attachment_url" variant="outline" as-child>
          <a :href="payment.attachment_url" target="_blank" rel="noopener">
            <Paperclip class="size-4" />
            View slip
          </a>
        </Button>

        <Button as-child>
          <a
            :href="receiptPrint(payment.id).url"
            target="_blank"
            rel="noopener"
          >
            <Printer class="size-4" />
            Print receipt
          </a>
        </Button>
      </div>
    </div>

    <div class="flex max-w-xl flex-col gap-3 rounded-2xl border p-4">
      <div class="flex items-baseline justify-between gap-2">
        <span class="text-sm text-muted-foreground">Amount received</span>
        <span class="text-2xl font-bold tabular-nums">
          {{ formatRs(payment.amount) }}
        </span>
      </div>

      <div class="flex items-baseline justify-between gap-2">
        <span class="text-sm text-muted-foreground">Balance after</span>
        <span class="text-sm font-medium tabular-nums">
          <template v-if="payment.balance_after < 0">CR </template>
          {{ formatRs(payment.balance_after) }}
        </span>
      </div>
    </div>

    <div class="flex justify-center pb-8">
      <div class="ticket bg-white shadow-md dark:bg-white">
        <div class="head relative z-40">
          <div class="center bold" style="font-size: 18px">{{ org.name }}</div>
          <div class="center" style="font-size: 12px">මුදල් ලදුපත</div>
        </div>
        <div class="hr"></div>

        <div class="receipt-info relative z-40">
          <div class="row">
            <div class="sin">ලදුපත් අංකය:</div>
            <div class="bold">{{ payment.receipt_number }}</div>
          </div>
          <div class="row">
            <div class="sin">දිනය:</div>
            <div>{{ payment.paid_at }}</div>
          </div>
        </div>
        <div class="hr"></div>

        <div class="cust-info kv-block relative z-40">
          <div class="row">
            <div class="sin">ගිණුම් අංකය:</div>
            <div class="bold">{{ account.account_number }}</div>
          </div>
          <div class="kv-label">නම:</div>
          <div class="kv-value">{{ account.owner_name }}</div>
        </div>
        <div class="hr"></div>

        <div class="accounts relative z-40">
          <table class="amounts">
            <tbody>
              <tr>
                <td class="topic">ගෙවූ මුදල</td>
                <td class="right topic">{{ formatLkr(payment.amount) }}</td>
              </tr>
              <tr>
                <td>ඉතිරි හිග මුදල</td>
                <td class="right">
                  <span v-if="payment.balance_after < 0">CR</span>
                  {{ formatLkr(payment.balance_after) }}
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="payment.reference" class="row mt-1">
            <div class="sin">විස්තර:</div>
            <div>{{ payment.reference }}</div>
          </div>
        </div>
        <div class="hr"></div>

        <div class="row tiny relative z-40">
          <div>Received by: {{ payment.recorded_by }}</div>
          <div>{{ payment.paid_at }}</div>
        </div>
        <div class="center tiny mt-1">Thank you! / ස්තූතියි!</div>

        <div class="cut-line" style="margin-top: 20px"></div>
      </div>
    </div>
  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Noto+Serif+Sinhala:wght@100..900&display=swap');

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
</style>
