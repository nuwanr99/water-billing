<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

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

const formatLkr = (value: number): string =>
  `රු. ${new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Math.abs(Number(value) || 0))}`;

const printNow = (): void => window.print();

onMounted(() => {
  const print = () => window.setTimeout(printNow, 150);

  // Wait for the Sinhala webfont so the ticket doesn't print with a
  // fallback face; fonts.ready resolves immediately when already cached.
  if (document.fonts?.ready) {
    void document.fonts.ready.then(print);
  } else {
    print();
  }
});
</script>

<template>
  <div>
    <Head :title="`Print ${payment.receipt_number}`" />

    <div class="screen-hint">
      Printing… if the dialog didn't open,
      <button type="button" @click="printNow">print again</button>
      — close this tab when done.
    </div>

    <div class="ticket">
      <div class="head">
        <div class="center bold" style="font-size: 18px">{{ org.name }}</div>
        <div class="center" style="font-size: 12px">මුදල් ලදුපත</div>
      </div>
      <div class="hr"></div>

      <div class="row">
        <div class="sin">ලදුපත් අංකය:</div>
        <div class="bold">{{ payment.receipt_number }}</div>
      </div>
      <div class="row">
        <div class="sin">දිනය:</div>
        <div>{{ payment.paid_at }}</div>
      </div>
      <div class="hr"></div>

      <div class="kv-block">
        <div class="row">
          <div class="sin">ගිණුම් අංකය:</div>
          <div class="bold">{{ account.account_number }}</div>
        </div>
        <div class="kv-label">නම:</div>
        <div class="kv-value">{{ account.owner_name }}</div>
      </div>
      <div class="hr"></div>

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
      <div class="hr"></div>

      <div class="row tiny">
        <div>Received by: {{ payment.recorded_by }}</div>
        <div>{{ payment.paid_at }}</div>
      </div>
      <div class="center tiny mt-1">Thank you! / ස්තූතියි!</div>

      <div class="cut-line" style="margin-top: 20px"></div>
    </div>
  </div>
</template>

<style>
@import url('https://fonts.googleapis.com/css2?family=Noto+Serif+Sinhala:wght@100..900&display=swap');

@page {
  size: 78mm auto;
  margin: 0;
}

body {
  margin: 0;
  background: #fff;
}

.screen-hint {
  font-family: system-ui, sans-serif;
  font-size: 13px;
  padding: 8px 12px;
  background: #f5f5f4;
  border-bottom: 1px solid #e7e5e4;
  color: #57534e;
}

.screen-hint button {
  text-decoration: underline;
  cursor: pointer;
}

@media print {
  .screen-hint {
    display: none;
  }
}

.ticket {
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
  background: #fff;
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
