<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CheckCircle2, MapPin, PlayCircle } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import JobStatusBadge from '@/components/JobStatusBadge.vue';
import MessageThread from '@/components/MessageThread.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { show as complaintShow } from '@/routes/my/complaints';
import { index, status, updates } from '@/routes/my-jobs';

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
  can: { post_update: boolean; change_status: boolean };
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'My jobs', href: index() }],
  },
});

const startForm = useForm({ status: 'in_progress' });

const startJob = (): void => {
  startForm.post(status(props.job.id).url, {
    preserveScroll: true,
  });
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
</script>

<template>
  <Head :title="job.job_number" />

  <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
    <div class="space-y-1">
      <div class="flex flex-wrap items-center gap-2">
        <h2 class="text-lg font-semibold tracking-tight">
          {{ job.title }}
        </h2>
        <JobStatusBadge :status="job.status" />
      </div>
      <p class="text-sm text-muted-foreground">
        {{ job.job_number }} · scheduled {{ job.scheduled_date }}
      </p>
    </div>

    <div
      v-if="
        can.change_status &&
        (job.status === 'assigned' || job.status === 'in_progress')
      "
      class="flex flex-col gap-2 sm:flex-row"
    >
      <Button
        v-if="job.status === 'assigned'"
        class="w-full sm:w-auto"
        :disabled="startForm.processing"
        @click="startJob"
      >
        <PlayCircle class="size-4" />
        Start job
      </Button>

      <Dialog v-model:open="completeOpen">
        <DialogTrigger as-child>
          <Button variant="outline" class="w-full sm:w-auto">
            <CheckCircle2 class="size-4" />
            Mark complete
          </Button>
        </DialogTrigger>
        <DialogContent>
          <form class="space-y-6" @submit.prevent="submitComplete">
            <DialogHeader class="space-y-3">
              <DialogTitle>Mark this job as complete?</DialogTitle>
              <DialogDescription>
                Add a note describing the work you did before completing this
                job.
              </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
              <Label for="notes">Completion notes</Label>
              <Textarea
                id="notes"
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
                  completeForm.notes.trim() === '' || completeForm.processing
                "
              >
                Mark complete
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>

    <Card>
      <CardContent class="flex flex-col gap-4 py-4 text-sm">
        <p class="whitespace-pre-line">{{ job.description }}</p>
        <p class="text-muted-foreground">
          Scheduled for {{ job.scheduled_date }}
        </p>

        <div v-if="job.complaint" class="flex flex-col gap-3 border-t pt-4">
          <div class="flex items-start gap-3">
            <MapPin class="size-4 shrink-0 text-muted-foreground" />
            <div>
              <p class="font-medium">
                {{ job.complaint.account_number ?? job.complaint.subject }}
              </p>
              <p class="text-muted-foreground">
                {{ job.complaint.connection_address ?? 'No address on file' }}
              </p>
            </div>
          </div>
          <Button as-child variant="outline" size="sm" class="self-start">
            <Link :href="complaintShow(job.complaint.id)">
              View complaint
            </Link>
          </Button>
        </div>
      </CardContent>
    </Card>

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

    <MessageThread
      :messages="thread"
      :can-reply="can.post_update"
      :reply-url="updates(job.id).url"
    />
  </div>
</template>
