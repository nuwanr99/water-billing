<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, MapPin } from '@lucide/vue';
import { ref } from 'vue';
import ComplaintStatusBadge from '@/components/ComplaintStatusBadge.vue';
import InputError from '@/components/InputError.vue';
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
import { complaintCategoryLabels } from '@/lib/complaints';
import type { ComplaintCategory, ComplaintStatus } from '@/lib/complaints';
import { close, index, reply } from '@/routes/my/complaints';

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
    water_account: {
      id: number;
      account_number: string;
      connection_address: string | null;
    } | null;
    closure_note: string | null;
    submitted_at: string;
    closed_at: string | null;
  };
  thread: ThreadMessage[];
  can: { reply: boolean; close: boolean };
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'My complaints', href: index() }],
  },
});

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

  <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
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
          {{ complaintCategoryLabels[complaint.category] }} ·
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
                Close it once your issue is resolved. You can add a note for the
                office if you like.
              </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
              <Label for="note">Note (optional)</Label>
              <Textarea id="note" v-model="closeForm.note" rows="3" />
              <InputError :message="closeForm.errors.note" />
            </div>

            <DialogFooter class="gap-2">
              <DialogClose as-child>
                <Button type="button" variant="secondary">Cancel</Button>
              </DialogClose>
              <Button type="submit" :disabled="closeForm.processing">
                Close complaint
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>

    <Card v-if="complaint.water_account">
      <CardContent class="flex items-center gap-3 py-4 text-sm">
        <MapPin class="size-4 shrink-0 text-muted-foreground" />
        <div>
          <p class="font-medium">
            Account {{ complaint.water_account.account_number }}
          </p>
          <p class="text-muted-foreground">
            {{
              complaint.water_account.connection_address ?? 'Registered address'
            }}
          </p>
        </div>
      </CardContent>
    </Card>

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
</template>
