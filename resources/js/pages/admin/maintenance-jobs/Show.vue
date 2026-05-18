<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { HandCoins, MapPin, Pencil } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import JobStatusBadge from '@/components/JobStatusBadge.vue';
import MessageThread from '@/components/MessageThread.vue';
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
import type { MaintenanceJobStatus } from '@/lib/jobs';
import { show as complaintShow } from '@/routes/admin/complaints';
import { create as createExpense } from '@/routes/admin/expenses';
import { edit, index, status, updates } from '@/routes/admin/maintenance-jobs';

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
  job: {
    id: number;
    job_number: string;
    title: string;
    description: string;
    status: MaintenanceJobStatus;
    scheduled_date: string;
    completion_notes: string | null;
    completed_at: string | null;
    created_by: string;
    assignees: { id: number; name: string }[];
    complaint: {
      id: number;
      complaint_number: string;
      subject: string;
      account_number: string | null;
      connection_address: string | null;
    } | null;
  };
  thread: ThreadMessage[];
  can: {
    update: boolean;
    post_update: boolean;
    change_status: boolean;
    record_expense: boolean;
  };
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Maintenance jobs', href: index() }],
  },
});

const isLiveStatus =
  props.job.status === 'assigned' || props.job.status === 'in_progress';

const startForm = useForm({ status: 'in_progress', notes: '' });

const submitStart = (): void => {
  startForm.post(status(props.job.id).url, { preserveScroll: true });
};

const completeOpen = ref(false);
const completeForm = useForm({ status: 'completed', notes: '' });

const submitComplete = (): void => {
  completeForm.post(status(props.job.id).url, {
    preserveScroll: true,
    onSuccess: () => {
      completeOpen.value = false;
    },
  });
};

const cancelOpen = ref(false);
const cancelForm = useForm({ status: 'cancelled', notes: '' });

const submitCancel = (): void => {
  cancelForm.post(status(props.job.id).url, {
    preserveScroll: true,
    onSuccess: () => {
      cancelOpen.value = false;
    },
  });
};
</script>

<template>
  <Head :title="job.job_number" />

  <div class="mx-auto w-full max-w-6xl p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="space-y-1">
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="text-lg font-semibold tracking-tight">
            {{ job.title }}
          </h2>
          <JobStatusBadge :status="job.status" />
        </div>
        <p class="text-sm text-muted-foreground">
          {{ job.job_number }} · scheduled {{ job.scheduled_date }} · created by
          {{ job.created_by }}
        </p>
      </div>

      <div class="flex items-center gap-2">
        <Button v-if="can.record_expense" variant="outline" size="sm" as-child>
          <Link :href="createExpense({ query: { job: job.id } })">
            <HandCoins class="size-4" />
            Record expense
          </Link>
        </Button>

        <Button
          v-if="can.update && isLiveStatus"
          variant="outline"
          size="sm"
          as-child
        >
          <Link :href="edit(job.id)">
            <Pencil class="size-4" />
            Edit
          </Link>
        </Button>
      </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
      <div class="flex flex-col gap-6 lg:col-span-2">
        <div
          v-if="job.status === 'completed' && job.completion_notes"
          class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm dark:border-emerald-900 dark:bg-emerald-950/40"
        >
          <p class="font-medium text-emerald-800 dark:text-emerald-300">
            Completion notes
          </p>
          <p
            class="mt-1 whitespace-pre-line text-emerald-900/90 dark:text-emerald-200/80"
          >
            {{ job.completion_notes }}
          </p>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Description</CardTitle>
          </CardHeader>
          <CardContent class="text-sm whitespace-pre-line">
            {{ job.description }}
          </CardContent>
        </Card>

        <MessageThread
          :messages="thread"
          :can-reply="can.post_update"
          :reply-url="updates(job.id).url"
        />
      </div>

      <div class="flex flex-col gap-6">
        <Card>
          <CardHeader>
            <CardTitle>Details</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3 text-sm">
            <p class="whitespace-pre-line">{{ job.description }}</p>
            <p>
              <span class="text-muted-foreground">Scheduled:</span>
              {{ job.scheduled_date }}
            </p>
            <p>
              <span class="text-muted-foreground">Created by:</span>
              {{ job.created_by }}
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Assignees</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div v-if="job.assignees.length > 0" class="flex flex-col gap-2">
              <div
                v-for="assignee in job.assignees"
                :key="assignee.id"
                class="rounded-md bg-muted px-3 py-1.5 text-sm"
              >
                {{ assignee.name }}
              </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">Unassigned</p>
          </CardContent>
        </Card>

        <Card v-if="job.complaint">
          <CardHeader>
            <CardTitle>Linked complaint</CardTitle>
          </CardHeader>
          <CardContent class="space-y-3 text-sm">
            <div class="flex items-center gap-3">
              <MapPin class="size-4 shrink-0 text-muted-foreground" />
              <div>
                <p class="font-medium">
                  {{ job.complaint.complaint_number }}
                </p>
                <p class="text-muted-foreground">{{ job.complaint.subject }}</p>
              </div>
            </div>
            <p
              v-if="job.complaint.account_number"
              class="text-muted-foreground"
            >
              Account {{ job.complaint.account_number }}
            </p>
            <p
              v-if="job.complaint.connection_address"
              class="text-muted-foreground"
            >
              {{ job.complaint.connection_address }}
            </p>
            <Button variant="outline" size="sm" as-child>
              <Link :href="complaintShow(job.complaint.id)"
                >View complaint</Link
              >
            </Button>
          </CardContent>
        </Card>

        <Card class="border-dashed">
          <CardHeader>
            <CardTitle class="text-muted-foreground">
              Materials & costs
            </CardTitle>
          </CardHeader>
          <CardContent class="text-sm text-muted-foreground">
            Captured with the Expenses & Inventory module.
          </CardContent>
        </Card>

        <Card v-if="can.change_status && isLiveStatus">
          <CardHeader>
            <CardTitle>Status</CardTitle>
          </CardHeader>
          <CardContent class="flex flex-col gap-3">
            <Button
              v-if="job.status === 'assigned'"
              size="sm"
              :disabled="startForm.processing"
              @click="submitStart"
            >
              Start job
            </Button>

            <Dialog v-model:open="completeOpen">
              <DialogTrigger as-child>
                <Button variant="outline" size="sm">Complete</Button>
              </DialogTrigger>
              <DialogContent>
                <form class="space-y-6" @submit.prevent="submitComplete">
                  <DialogHeader class="space-y-3">
                    <DialogTitle>Complete this job?</DialogTitle>
                    <DialogDescription>
                      Leave a note describing the work that was completed.
                    </DialogDescription>
                  </DialogHeader>

                  <div class="grid gap-2">
                    <Label for="complete-notes">Completion notes</Label>
                    <Textarea
                      id="complete-notes"
                      v-model="completeForm.notes"
                      rows="3"
                      required
                    />
                    <InputError :message="completeForm.errors.notes" />
                  </div>

                  <DialogFooter class="gap-2">
                    <DialogClose as-child>
                      <Button type="button" variant="secondary">Cancel</Button>
                    </DialogClose>
                    <Button
                      type="submit"
                      :disabled="
                        completeForm.processing ||
                        completeForm.notes.trim() === ''
                      "
                    >
                      Complete job
                    </Button>
                  </DialogFooter>
                </form>
              </DialogContent>
            </Dialog>

            <Dialog v-model:open="cancelOpen">
              <DialogTrigger as-child>
                <Button variant="outline" size="sm">Cancel job</Button>
              </DialogTrigger>
              <DialogContent>
                <form class="space-y-6" @submit.prevent="submitCancel">
                  <DialogHeader class="space-y-3">
                    <DialogTitle>Cancel this job?</DialogTitle>
                    <DialogDescription>
                      Leave a note explaining why this job is being cancelled.
                    </DialogDescription>
                  </DialogHeader>

                  <div class="grid gap-2">
                    <Label for="cancel-notes">Cancellation notes</Label>
                    <Textarea
                      id="cancel-notes"
                      v-model="cancelForm.notes"
                      rows="3"
                      required
                    />
                    <InputError :message="cancelForm.errors.notes" />
                  </div>

                  <DialogFooter class="gap-2">
                    <DialogClose as-child>
                      <Button type="button" variant="secondary"
                        >Keep job</Button
                      >
                    </DialogClose>
                    <Button
                      type="submit"
                      variant="destructive"
                      :disabled="
                        cancelForm.processing || cancelForm.notes.trim() === ''
                      "
                    >
                      Cancel job
                    </Button>
                  </DialogFooter>
                </form>
              </DialogContent>
            </Dialog>
          </CardContent>
        </Card>
      </div>
    </div>
  </div>
</template>
