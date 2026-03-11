<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';

type Charge = {
  id: number;
  document_number: string;
  type: string;
  amount: number;
  running_balance: number;
  description: string;
  date: string;
  recorded_by: string | null;
};

const props = defineProps<{
  charge: Charge;
  account: { id: number; owner_name: string; account_number: string };
  org: { name: string };
}>();

const noteTitles: Record<string, string> = {
  charge: 'CHARGE NOTE',
  penalty: 'PENALTY NOTE',
  adjustment: 'ADJUSTMENT NOTE',
};

const noteTitle = noteTitles[props.charge.type] ?? 'CHARGE NOTE';

const amountLabels: Record<string, string> = {
  charge: 'Charge amount',
  penalty: 'Penalty amount',
  adjustment: 'Adjustment amount',
};

const amountLabel = amountLabels[props.charge.type] ?? 'Amount';

/**
 * Currency renders as "Rs 1,234.56"; negative values as credits, "Rs 1,234.56 CR".
 */
const formatCurrency = (amount: number): string => {
  const formatted = Math.abs(amount).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  return amount < 0 ? `Rs ${formatted} CR` : `Rs ${formatted}`;
};

const printNow = (): void => window.print();

onMounted(() => {
  window.setTimeout(printNow, 150);
});
</script>

<template>
  <div>
    <Head :title="`Print ${charge.document_number}`" />

    <div class="screen-hint">
      Printing… if the dialog didn't open,
      <button type="button" @click="printNow">print again</button>
      — close this tab when done.
    </div>

    <div class="charge-ticket">
      <div class="center bold" style="font-size: 15px">{{ org.name }}</div>
      <div class="center topic mt-1">{{ noteTitle }}</div>

      <div class="hr"></div>

      <div class="row">
        <div>Document no:</div>
        <div class="bold">{{ charge.document_number }}</div>
      </div>
      <div class="row">
        <div>Date:</div>
        <div>{{ charge.date }}</div>
      </div>

      <div class="hr"></div>

      <div class="row">
        <div>Account no:</div>
        <div class="bold">{{ account.account_number }}</div>
      </div>
      <div class="row">
        <div>Member:</div>
        <div>{{ account.owner_name }}</div>
      </div>

      <div class="hr"></div>

      <div class="bold">Description:</div>
      <div class="description">{{ charge.description }}</div>

      <div class="hr"></div>

      <div class="row">
        <div class="topic">{{ amountLabel }}</div>
        <div class="topic">{{ formatCurrency(charge.amount) }}</div>
      </div>
      <div class="row mt-1">
        <div>Account balance</div>
        <div class="bold">{{ formatCurrency(charge.running_balance) }}</div>
      </div>

      <div class="hr"></div>

      <div v-if="charge.recorded_by" class="row tiny">
        <div>Recorded by:</div>
        <div>{{ charge.recorded_by }}</div>
      </div>
      <div class="center tiny mt-1">Thank you!</div>

      <div class="cut-line"></div>
    </div>
  </div>
</template>

<style>
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

.charge-ticket {
  width: 78mm;
  padding: 3mm;
  font-family: ui-monospace, 'Courier New', monospace;
  font-size: 11px;
  line-height: 1.2;
  color: #000;
  background: #fff;
}

.charge-ticket .center {
  text-align: center;
}

.charge-ticket .bold {
  font-weight: 700;
}

.charge-ticket .topic {
  font-size: 12px;
  font-weight: 700;
}

.charge-ticket .tiny {
  font-size: 9px;
}

.charge-ticket .mt-1 {
  margin-top: 1mm;
}

.charge-ticket .hr {
  border-top: 1px dashed #000;
  margin: 2.5mm 0;
}

.charge-ticket .row {
  display: flex;
  justify-content: space-between;
  gap: 4mm;
}

.charge-ticket .description {
  margin-top: 1mm;
  white-space: pre-wrap;
  word-break: break-word;
}

.charge-ticket .cut-line {
  margin: 5mm 0 2mm;
  border-top: 1px dashed #000;
  position: relative;
}

.charge-ticket .cut-line::before {
  content: '✂';
  position: absolute;
  top: -7px;
  left: 50%;
  transform: translateX(-50%);
  background: #fff;
  padding: 0 3px;
}
</style>
