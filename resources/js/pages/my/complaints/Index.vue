<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { MessageSquareWarning, Plus } from '@lucide/vue';
import ComplaintStatusBadge from '@/components/ComplaintStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { complaintCategoryLabels } from '@/lib/complaints';
import type { ComplaintCategory, ComplaintStatus } from '@/lib/complaints';
import { create, index, show } from '@/routes/my/complaints';
import type { Paginated } from '@/types';

type MyComplaintRow = {
  id: number;
  complaint_number: string;
  subject: string;
  category: ComplaintCategory;
  status: ComplaintStatus;
  account_number: string | null;
  submitted_at: string;
};

defineProps<{
  complaints: Paginated<MyComplaintRow>;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'My complaints',
        href: index(),
      },
    ],
  },
});
</script>

<template>
  <Head title="My complaints" />

  <div class="flex flex-col gap-6 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <Heading
        variant="small"
        title="My complaints"
        description="Track complaints you've raised with the society office"
      />
      <Button as-child>
        <Link :href="create()">
          <Plus class="size-4" />
          New complaint
        </Link>
      </Button>
    </div>

    <div
      v-if="complaints.data.length === 0"
      class="flex flex-col items-center gap-3 rounded-xl border border-dashed py-16 text-center"
    >
      <div
        class="flex size-12 items-center justify-center rounded-full bg-muted"
      >
        <MessageSquareWarning class="size-6 text-muted-foreground" />
      </div>
      <div class="space-y-1">
        <p class="font-medium">No complaints yet</p>
        <p class="max-w-sm text-sm text-muted-foreground">
          Raise a complaint about a leak, blockage, or other issue and the
          office will follow up here.
        </p>
      </div>
      <Button as-child>
        <Link :href="create()">
          <Plus class="size-4" />
          New complaint
        </Link>
      </Button>
    </div>

    <div v-else class="flex flex-col gap-3">
      <Link
        v-for="complaint in complaints.data"
        :key="complaint.id"
        :href="show(complaint.id)"
      >
        <Card class="transition-colors hover:bg-muted/50">
          <CardContent class="flex flex-col gap-1.5 py-4">
            <div class="flex items-center justify-between gap-2">
              <p class="font-medium">{{ complaint.subject }}</p>
              <ComplaintStatusBadge :status="complaint.status" />
            </div>
            <p class="text-sm text-muted-foreground">
              {{ complaint.complaint_number }} ·
              {{ complaintCategoryLabels[complaint.category] }} ·
              {{ complaint.account_number ?? 'General' }} ·
              {{ complaint.submitted_at }}
            </p>
          </CardContent>
        </Card>
      </Link>
    </div>

    <Pagination :paginator="complaints" />
  </div>
</template>
