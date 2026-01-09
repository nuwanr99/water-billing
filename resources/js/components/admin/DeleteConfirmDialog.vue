<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
  url: string;
  title: string;
  description: string;
  confirmName?: string;
  triggerLabel?: string;
}>();

const open = ref(false);

const form = useForm({
  confirmation: '',
});

const submit = () => {
  form.delete(props.url, {
    preserveScroll: true,
    onSuccess: () => {
      open.value = false;
    },
  });
};
</script>

<template>
  <Dialog v-model:open="open">
    <DialogTrigger as-child>
      <Button variant="destructive">{{ triggerLabel ?? 'Delete' }}</Button>
    </DialogTrigger>
    <DialogContent>
      <form class="space-y-6" @submit.prevent="submit">
        <DialogHeader class="space-y-3">
          <DialogTitle>{{ title }}</DialogTitle>
          <DialogDescription>{{ description }}</DialogDescription>
        </DialogHeader>

        <div v-if="confirmName" class="grid gap-2">
          <Label for="confirmation">
            Type
            <span class="font-semibold">{{ confirmName }}</span>
            to confirm
          </Label>
          <Input
            id="confirmation"
            v-model="form.confirmation"
            autocomplete="off"
            :placeholder="confirmName"
          />
          <InputError :message="form.errors.confirmation" />
        </div>

        <DialogFooter class="gap-2">
          <DialogClose as-child>
            <Button
              type="button"
              variant="secondary"
              @click="
                () => {
                  form.clearErrors();
                  form.reset();
                }
              "
            >
              Cancel
            </Button>
          </DialogClose>

          <Button
            type="submit"
            variant="destructive"
            :disabled="
              form.processing ||
              (confirmName !== undefined && form.confirmation !== confirmName)
            "
          >
            {{ triggerLabel ?? 'Delete' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
