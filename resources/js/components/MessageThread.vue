<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ImagePlus, Paperclip, Send, X } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

type ThreadAttachment = {
  id: number;
  name: string;
  is_image: boolean;
  url: string;
};

type ThreadMessage = {
  id: number;
  body: string;
  is_system: boolean;
  is_mine: boolean;
  author: string | null;
  created_at: string | null;
  attachments: ThreadAttachment[];
};

const props = defineProps<{
  messages: ThreadMessage[];
  canReply: boolean;
  replyUrl: string;
}>();

const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<{ body: string; attachments: File[] }>({
  body: '',
  attachments: [],
});

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
  form.post(props.replyUrl, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      form.attachments = [];
    },
  });
};
</script>

<template>
  <div class="flex flex-col gap-4">
    <ul class="flex flex-col gap-4">
      <li
        v-for="message in messages"
        :key="message.id"
        class="flex flex-col"
        :class="{
          'items-center': message.is_system,
          'items-end': !message.is_system && message.is_mine,
          'items-start': !message.is_system && !message.is_mine,
        }"
      >
        <p
          v-if="message.is_system"
          class="max-w-md rounded-full bg-muted px-3 py-1 text-center text-xs whitespace-pre-line text-muted-foreground"
        >
          {{ message.body }}
        </p>

        <div
          v-else
          class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm sm:max-w-[70%]"
          :class="
            message.is_mine
              ? 'bg-primary text-primary-foreground'
              : 'bg-muted text-foreground'
          "
        >
          <p
            v-if="!message.is_mine && message.author"
            class="mb-1 text-xs font-medium opacity-80"
          >
            {{ message.author }}
          </p>
          <p class="break-words whitespace-pre-line">{{ message.body }}</p>

          <div
            v-if="message.attachments.length > 0"
            class="mt-2 flex flex-wrap gap-2"
          >
            <template
              v-for="attachment in message.attachments"
              :key="attachment.id"
            >
              <a
                v-if="attachment.is_image"
                :href="attachment.url"
                target="_blank"
                rel="noopener"
                class="block overflow-hidden rounded-lg border border-white/20"
              >
                <img
                  :src="attachment.url"
                  :alt="attachment.name"
                  class="h-24 w-24 object-cover"
                />
              </a>
              <a
                v-else
                :href="attachment.url"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1.5 rounded-md bg-background/20 px-2 py-1 text-xs underline"
              >
                <Paperclip class="size-3" />
                {{ attachment.name }}
              </a>
            </template>
          </div>
        </div>

        <span
          v-if="!message.is_system"
          class="mt-1 px-1 text-[11px] text-muted-foreground"
        >
          {{ message.created_at }}
        </span>
      </li>
    </ul>

    <form
      v-if="canReply"
      class="sticky bottom-0 flex flex-col gap-2 border-t bg-background pt-3 pb-[env(safe-area-inset-bottom)]"
      @submit.prevent="submit"
    >
      <Textarea
        v-model="form.body"
        placeholder="Write a reply..."
        rows="2"
        class="resize-none"
        required
      />
      <InputError :message="form.errors.body" />

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
      <InputError
        :message="form.errors['attachments.0'] ?? form.errors.attachments"
      />

      <div class="flex items-center justify-between gap-2">
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
          @click="fileInput?.click()"
        >
          <ImagePlus class="size-4" />
          Add photo
        </Button>
        <Button type="submit" size="sm" :disabled="form.processing">
          <Send class="size-4" />
          Send
        </Button>
      </div>
    </form>
  </div>
</template>
