<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CheckCircle2, MapPin, Plus, Wrench } from '@lucide/vue';
import { ref } from 'vue';
import ComplaintStatusBadge from '@/components/ComplaintStatusBadge.vue';
import InputError from '@/components/InputError.vue';
import JobStatusBadge from '@/components/JobStatusBadge.vue';
import MessageThread from '@/components/MessageThread.vue';
import MultiUserSelect from '@/components/MultiUserSelect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { complaintCategoryLabels } from '@/lib/complaints';
import type { ComplaintCategory } from '@/lib/complaints';
import type { ComplaintStatus } from '@/lib/complaints';
import type { MaintenanceJobStatus } from '@/lib/jobs';
import {
  assign,
  close,
  handlers,
  index,
  reply,
} from '@/routes/admin/complaints';
import {
  create as createJob,
  show as jobShow,
} from '@/routes/admin/maintenance-jobs';
import type { ComboboxOption } from '@/types';

type ThreadMessage = {
  id: number;
  body: string;
  is_system: boolean;
  is_mine: boolean;
  author: string | null;
  created_at: string | null;
  attachments: { id: number; name: string; is_image: boolean; url: string }[];
};

const props = defineProps<{
  complaint: {
    id: number;
    complaint_number: string;
    subject: string;
    category: ComplaintCategory;
    status: ComplaintStatus;
    member: { id: number; name: string };
    water_account: {
      id: number;
      account_number: string;
      connection_address: string | null;
    } | null;
    handlers: { id: number; name: string }[];
    closure_note: string | null;
    submitted_at: string;
    closed_at: string | null;
  };
  thread: ThreadMessage[];
  jobs: {
    id: number;
    job_number: string;
    title: string;
    status: MaintenanceJobStatus;
    assignees: string[];
    scheduled_date: string;
  }[];
  can: { assign: boolean; reply: boolean; close: boolean; create_job: boolean };
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Complaints', href: index() }],
  },
});

const selectedHandlers = ref<ComboboxOption[]>(
  props.complaint.handlers.map((handler): ComboboxOption => ({
    value: handler.id,
    label: handler.name,
  })),
);

const assignForm = useForm<{ handler_ids: number[] }>({ handler_ids: [] });

const submitAssign = (): void => {
  assignForm
    .transform(() => ({
      handler_ids: selectedHandlers.value.map((user) => user.value),
    }))
    .post(assign(props.complaint.id).url, { preserveScroll: true });
};

const closeOpen = ref(false);
const closeForm = useForm({ note: '' });

const submitClose = (): void => {
  closeForm.post(close(props.complaint.id).url, {
    preserveScroll: true,
    onSuccess: () => {
      closeOpen.value = false;
    },
  });
};
</script>

<template>
  <Head :title="complaint.complaint_number" />

  <div class="mx-auto w-full max-w-6xl p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="space-y-1">
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="text-lg font-semibold tracking-tight">
            {{ complaint.subject }}
          </h2>
          <ComplaintStatusBadge :status="complaint.status" />
        </div>
        <p class="text-sm text-muted-foreground">
          {{ complaint.complaint_number }} ·
          {{ complaintCategoryLabels[complaint.category] }} · submitted
          {{ complaint.submitted_at }}
        </p>
      </div>

      <Dialog v-if="can.close" v-model:open="closeOpen">
        <DialogTrigger as-child>
          <Button variant="outline" size="sm">
            <CheckCircle2 class="size-4" />
            Close complaint
          </Button>
        </DialogTrigger>
        <DialogContent>
          <form class="space-y-6" @submit.prevent="submitClose">
            <DialogHeader class="space-y-3">
              <DialogTitle>Close this complaint?</DialogTitle>
              <DialogDescription>
                Leave a closing note explaining the resolution. It will be
                delivered to the member.
              </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
              <Label for="note">Resolution note</Label>
              <Textarea id="note" v-model="closeForm.note" rows="3" required />
              <InputError :message="closeForm.errors.note" />
            </div>

            <DialogFooter class="gap-2">
              <DialogClose as-child>
                <Button type="button" variant="secondary">Cancel</Button>
              </DialogClose>
              <Button
                type="submit"
                :disabled="closeForm.processing || closeForm.note.trim() === ''"
              >
                Close complaint
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
      <div class="flex flex-col gap-6 lg:col-span-2">
        <div
          v-if="complaint.status === 'closed' && complaint.closure_note"
          class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm dark:border-emerald-900 dark:bg-emerald-950/40"
        >
          <p class="font-medium text-emerald-800 dark:text-emerald-300">
            Resolution note
          </p>
          <p
            class="mt-1 whitespace-pre-line text-emerald-900/90 dark:text-emerald-200/80"
          >
            {{ complaint.closure_note }}
          </p>
        </div>

        <MessageThread
          :messages="thread"
          :can-reply="can.reply"
          :reply-url="reply(complaint.id).url"
        />
      </div>

      <div class="flex flex-col gap-6">
        <Card>
          <CardHeader>
            <CardTitle>Member</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3 text-sm">
            <p class="font-medium">{{ complaint.member.name }}</p>

            <div v-if="complaint.water_account" class="flex items-center gap-3">
              <MapPin class="size-4 shrink-0 text-muted-foreground" />
              <div>
                <p class="font-medium">
                  Account {{ complaint.water_account.account_number }}
                </p>
                <p class="text-muted-foreground">
                  {{
                    complaint.water_account.connection_address ??
                    'Registered address'
                  }}
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Handlers</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div
              v-if="complaint.handlers.length > 0"
              class="flex flex-col gap-2"
            >
              <div
                v-for="handler in complaint.handlers"
                :key="handler.id"
                class="rounded-md bg-muted px-3 py-1.5 text-sm"
              >
                {{ handler.name }}
              </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">Unassigned</p>

            <div v-if="can.assign" class="flex flex-col gap-3 border-t pt-4">
              <MultiUserSelect
                v-model="selectedHandlers"
                :search-url="handlers().url"
                placeholder="Search users by name or email..."
              />
              <InputError :message="assignForm.errors.handler_ids" />
              <Button
                size="sm"
                :disabled="
                  selectedHandlers.length === 0 || assignForm.processing
                "
                @click="submitAssign"
              >
                Assign
              </Button>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader
            class="flex flex-row items-center justify-between gap-2 space-y-0"
          >
            <CardTitle class="flex items-center gap-2">
              <Wrench class="size-4 text-muted-foreground" />
              Jobs
            </CardTitle>
            <Button v-if="can.create_job" size="sm" variant="outline" as-child>
              <Link :href="createJob({ query: { complaint: complaint.id } })">
                <Plus class="size-4" />
                Create job
              </Link>
            </Button>
          </CardHeader>
          <CardContent class="space-y-3">
            <Link
              v-for="job in jobs"
              :key="job.id"
              :href="jobShow(job.id)"
              class="block rounded-lg border p-3 transition-colors hover:bg-muted/50"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-medium">{{ job.title }}</span>
                <JobStatusBadge :status="job.status" />
              </div>
              <p class="mt-1 text-xs text-muted-foreground">
                {{ job.job_number }} · {{ job.scheduled_date }}
                <template v-if="job.assignees.length > 0">
                  · {{ job.assignees.join(', ') }}
                </template>
              </p>
            </Link>
            <p v-if="jobs.length === 0" class="text-sm text-muted-foreground">
              No jobs yet.
              <template v-if="can.create_job">
                Create one to dispatch the work.
              </template>
            </p>
          </CardContent>
        </Card>
      </div>
    </div>
  </div>
</template>
