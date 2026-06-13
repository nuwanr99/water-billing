<script setup lang="ts">
import { FileDown, FileText } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/composables/usePermission';

defineProps<{
  pdfUrl: string;
  csvUrl?: string;
}>();

const { hasPermission } = usePermission();
</script>

<template>
  <div v-if="hasPermission('reports.export')" class="flex items-center gap-2">
    <Button variant="outline" size="sm" as-child>
      <a :href="pdfUrl">
        <FileText class="size-4" />
        Download PDF
      </a>
    </Button>
    <Button v-if="csvUrl" variant="outline" size="sm" as-child>
      <a :href="csvUrl">
        <FileDown class="size-4" />
        Export CSV
      </a>
    </Button>
  </div>
</template>
