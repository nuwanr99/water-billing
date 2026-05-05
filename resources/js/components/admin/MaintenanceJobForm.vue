<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import MultiUserSelect from '@/components/MultiUserSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  assignees as assigneesRoute,
  index,
  store,
  update,
} from '@/routes/admin/maintenance-jobs';
import type { ComboboxOption } from '@/types';

type JobFormData = {
  id: number;
  title: string;
  description: string;
  scheduled_date: string;
  complaint_number: string | null;
  assignees: { id: number; name: string }[];
};

const props = defineProps<{
  mode: 'create' | 'edit';
  job?: JobFormData;
  complaint?: { id: number; complaint_number: string; subject: string } | null;
}>();

const selectedAssignees = ref<ComboboxOption[]>(
  (props.job?.assignees ?? []).map((assignee) => ({
    value: assignee.id,
    label: assignee.name,
  })),
);

const form = useForm<{
  complaint_id: number | null;
  title: string;
  description: string;
  scheduled_date: string;
  assignee_ids: number[];
}>({
  complaint_id: props.complaint?.id ?? null,
  title: props.job?.title ?? '',
  description: props.job?.description ?? '',
  scheduled_date: props.job?.scheduled_date ?? '',
  assignee_ids: [],
});

form.transform((data) => ({
  ...data,
  assignee_ids: selectedAssignees.value.map((assignee) => assignee.value),
}));

const linkedComplaintNumber =
  props.complaint?.complaint_number ?? props.job?.complaint_number ?? null;

const submit = (): void => {
  if (props.mode === 'create') {
    form.post(store().url);

    return;
  }

  form.put(update(props.job!.id).url);
};
</script>

<template>
  <form class="max-w-2xl space-y-6" @submit.prevent="submit">
    <div
      v-if="linkedComplaintNumber"
      class="rounded-lg border bg-muted/40 px-4 py-3 text-sm"
    >
      Linked to complaint
      <span class="font-medium">{{ linkedComplaintNumber }}</span>
      <span v-if="complaint?.subject" class="text-muted-foreground">
        — {{ complaint.subject }}
      </span>
    </div>

    <div class="grid gap-2">
      <Label for="title">Title</Label>
      <Input
        id="title"
        v-model="form.title"
        required
        maxlength="120"
        placeholder="e.g. Replace the broken valve"
      />
      <InputError :message="form.errors.title" />
    </div>

    <div class="grid gap-2">
      <Label for="description">Description</Label>
      <Textarea
        id="description"
        v-model="form.description"
        rows="4"
        required
        placeholder="What needs doing, and where"
      />
      <InputError :message="form.errors.description" />
    </div>

    <div class="grid gap-2">
      <Label for="scheduled_date">Scheduled date</Label>
      <Input
        id="scheduled_date"
        v-model="form.scheduled_date"
        type="date"
        required
        class="max-w-48"
      />
      <InputError :message="form.errors.scheduled_date" />
    </div>

    <div class="grid gap-2">
      <Label>Assignees</Label>
      <MultiUserSelect
        v-model="selectedAssignees"
        :search-url="assigneesRoute().url"
        placeholder="Search anyone by name or email..."
      />
      <InputError :message="form.errors.assignee_ids" />
    </div>

    <div class="flex items-center gap-4">
      <Button type="submit" :disabled="form.processing">
        {{ mode === 'create' ? 'Create job' : 'Save changes' }}
      </Button>
      <Button variant="secondary" as-child>
        <Link :href="index()">Cancel</Link>
      </Button>
    </div>
  </form>
</template>
