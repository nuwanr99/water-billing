<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, useTemplateRef } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
  checkoutUrl: string;
  fields: Record<string, string>;
}>();

/**
 * PayHere hosted checkout expects a plain cross-site POST, so this page is
 * just an interstitial: a real HTML form auto-submitted on mount, with a
 * visible button as the fallback when auto-submit is blocked.
 */
const checkoutForm = useTemplateRef<HTMLFormElement>('checkoutForm');

onMounted(() => {
  checkoutForm.value?.submit();
});
</script>

<template>
  <Head title="Redirecting to PayHere" />

  <div
    class="flex min-h-svh flex-col items-center justify-center bg-background p-6"
  >
    <form
      ref="checkoutForm"
      method="post"
      :action="checkoutUrl"
      class="flex w-full max-w-sm flex-col items-center gap-6 text-center"
    >
      <input
        v-for="(value, key) in fields"
        :key="key"
        type="hidden"
        :name="key"
        :value="value"
      />

      <Spinner class="size-10 text-muted-foreground" />

      <div class="space-y-1.5">
        <p class="text-lg font-semibold text-foreground">
          Redirecting you to PayHere…
        </p>
        <p class="text-sm text-muted-foreground">
          Please wait — you are being taken to the secure payment gateway.
        </p>
      </div>

      <Button type="submit" variant="outline" class="w-full sm:w-auto">
        Continue to PayHere
      </Button>
    </form>
  </div>
</template>
