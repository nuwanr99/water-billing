<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Droplets, TriangleAlert } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { lookup } from '@/routes/pay';

defineProps<{
  notFound: boolean;
}>();

const form = useForm({
  account_number: '',
  contact: '',
});

const submit = (): void => {
  form.post(lookup.url());
};
</script>

<template>
  <div
    class="flex min-h-svh flex-col items-center bg-muted/40 px-4 py-10 sm:justify-center dark:bg-background"
  >
    <Head title="Pay your water bill" />

    <div class="flex w-full max-w-md flex-col gap-6">
      <div class="flex flex-col items-center gap-2 text-center">
        <div
          class="flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground"
        >
          <Droplets class="size-6" />
        </div>
        <h1 class="text-xl font-semibold tracking-tight">
          Pay your water bill
        </h1>
        <p class="max-w-xs text-sm text-balance text-muted-foreground">
          Enter your account number and the phone number or email registered
          with the society.
        </p>
      </div>

      <div
        v-if="notFound"
        class="flex items-start gap-2.5 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm text-amber-700 dark:text-amber-400"
      >
        <TriangleAlert class="mt-0.5 size-4 shrink-0" />
        <p>
          We couldn't find an account for that link — please validate below.
        </p>
      </div>

      <Card>
        <CardContent>
          <form class="flex flex-col gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
              <Label for="account_number">Account number</Label>
              <Input
                id="account_number"
                v-model="form.account_number"
                type="text"
                required
                autofocus
                autocomplete="off"
                placeholder="e.g. WA-0001"
              />
              <InputError :message="form.errors.account_number" />
            </div>

            <div class="grid gap-2">
              <Label for="contact">Phone or email</Label>
              <Input
                id="contact"
                v-model="form.contact"
                type="text"
                required
                autocomplete="off"
                placeholder="071 234 5678 or email@example.com"
              />
              <InputError :message="form.errors.contact" />
            </div>

            <Button
              type="submit"
              size="lg"
              class="w-full"
              :disabled="form.processing"
            >
              <Spinner v-if="form.processing" />
              Find my account
            </Button>
          </form>
        </CardContent>
      </Card>

      <p class="text-center text-xs text-muted-foreground">
        Payments are processed securely by PayHere.
      </p>
    </div>
  </div>
</template>
