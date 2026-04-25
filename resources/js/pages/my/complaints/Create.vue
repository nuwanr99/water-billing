<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ImagePlus, X } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { create, index, store } from '@/routes/my/complaints';

defineProps<{
  accounts: {
    id: number;
    account_number: string;
    connection_address: string | null;
  }[];
  categories: { value: string; label: string }[];
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'My complaints', href: index() },
      { title: 'New complaint', href: create() },
    ],
  },
});

const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<{
  water_account_id: string;
  category: string;
  subject: string;
  description: string;
  attachments: File[];
}>({
  water_account_id: '',
  category: '',
  subject: '',
  description: '',
  attachments: [],
});

form.transform((data) => ({
  ...data,
  water_account_id:
    data.water_account_id === '' || data.water_account_id === 'general'
      ? null
      : Number(data.water_account_id),
}));

const onFilesPicked = (event: Event): void => {
  const input = event.target as HTMLInputElement;
  form.attachments = [
    ...form.attachments,
    ...Array.from(input.files ?? []),
  ].slice(0, 5);
  input.value = '';
};

const removeAttachment = (index: number): void => {
  form.attachments = form.attachments.filter((_, i) => i !== index);
};

const submit = (): void => {
  form.post(store().url, { forceFormData: true });
};
</script>

<template>
  <Head title="New complaint" />

  <div class="flex flex-col gap-6 p-4">
    <Heading
      variant="small"
      title="New complaint"
      description="Let the society office know about a leak, blockage, or other issue"
    />

    <form class="max-w-2xl space-y-6" @submit.prevent="submit">
      <div class="grid gap-2">
        <Label for="category">Category</Label>
        <Select v-model="form.category">
          <SelectTrigger id="category" class="w-full">
            <SelectValue placeholder="Select a category" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="category in categories"
              :key="category.value"
              :value="category.value"
            >
              {{ category.label }}
            </SelectItem>
          </SelectContent>
        </Select>
        <InputError :message="form.errors.category" />
      </div>

      <div class="grid gap-2">
        <Label for="water_account_id">Water account (optional)</Label>
        <Select v-model="form.water_account_id">
          <SelectTrigger id="water_account_id" class="w-full">
            <SelectValue placeholder="General — not account specific" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="general">
              General — not account specific
            </SelectItem>
            <SelectItem
              v-for="account in accounts"
              :key="account.id"
              :value="String(account.id)"
            >
              {{ account.account_number }}
              <span v-if="account.connection_address">
                — {{ account.connection_address }}
              </span>
            </SelectItem>
          </SelectContent>
        </Select>
        <InputError :message="form.errors.water_account_id" />
      </div>

      <div class="grid gap-2">
        <Label for="subject">Subject</Label>
        <Input
          id="subject"
          v-model="form.subject"
          required
          maxlength="120"
          placeholder="Short summary of the issue"
        />
        <InputError :message="form.errors.subject" />
      </div>

      <div class="grid gap-2">
        <Label for="description">Description</Label>
        <Textarea
          id="description"
          v-model="form.description"
          required
          rows="4"
          placeholder="Describe what's happening, when it started, and anything else that could help"
        />
        <InputError :message="form.errors.description" />
      </div>

      <div class="grid gap-2">
        <Label>Photos (optional)</Label>

        <div v-if="form.attachments.length > 0" class="flex flex-wrap gap-2">
          <span
            v-for="(file, index) in form.attachments"
            :key="index"
            class="inline-flex items-center gap-1.5 rounded-md bg-muted px-2 py-1 text-xs"
          >
            {{ file.name }}
            <button
              type="button"
              class="text-muted-foreground hover:text-foreground"
              :aria-label="`Remove ${file.name}`"
              @click="removeAttachment(index)"
            >
              <X class="size-3" />
            </button>
          </span>
        </div>

        <input
          ref="fileInput"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          multiple
          class="hidden"
          @change="onFilesPicked"
        />
        <Button
          type="button"
          variant="outline"
          size="sm"
          class="w-fit"
          :disabled="form.attachments.length >= 5"
          @click="fileInput?.click()"
        >
          <ImagePlus class="size-4" />
          Add photo
        </Button>
        <InputError
          :message="form.errors['attachments.0'] ?? form.errors.attachments"
        />
      </div>

      <div class="flex items-center gap-4">
        <Button type="submit" :disabled="form.processing">
          Submit complaint
        </Button>
        <Button variant="secondary" as-child>
          <Link :href="index()">Cancel</Link>
        </Button>
      </div>
    </form>
  </div>
</template>
