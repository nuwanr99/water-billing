<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Droplets } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { lookup } from '@/routes/pay';

const page = usePage();
const societyName = page.props.name;
const currentYear = new Date().getFullYear();

const highlights = ['Metered billing', 'Pay online', 'Receipts on WhatsApp'];

const form = useForm({
  account_number: '',
  contact: '',
});

const submit = (): void => {
  form.post(lookup.url());
};
</script>

<template>
  <div class="relative flex min-h-svh flex-col">
    <Head title="Welcome" />

    <!-- Hero photograph with a readability overlay -->
    <img
      src="/images/home-hero.jpg"
      alt=""
      class="absolute inset-0 size-full object-cover"
    />
    <div
      class="absolute inset-0 bg-gradient-to-r from-black/75 via-black/45 to-black/25"
    />
    <div
      class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-black/60 to-transparent"
    />

    <header
      class="relative mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5"
    >
      <div class="flex items-center gap-2 text-white">
        <AppLogoIcon class="size-6 fill-current" />
        <span class="text-sm font-semibold tracking-wide">Billing System</span>
      </div>
      <Button
        variant="outline"
        size="sm"
        class="border-white/30 bg-white/10 text-white backdrop-blur hover:bg-white/20 hover:text-white"
        as-child
      >
        <Link :href="login()">Sign in</Link>
      </Button>
    </header>

    <main
      class="relative mx-auto flex w-full max-w-6xl flex-1 items-center px-6 py-12"
    >
      <div class="grid w-full items-center gap-12 lg:grid-cols-[1fr_24rem]">
        <div class="flex max-w-xl flex-col items-start gap-6 text-white">
          <div
            class="flex size-12 items-center justify-center rounded-xl bg-white/15 backdrop-blur"
          >
            <Droplets class="size-6" />
          </div>
          <h1
            class="text-4xl font-semibold tracking-tight text-balance drop-shadow-sm lg:text-5xl"
          >
            {{ societyName }}
          </h1>
          <p class="text-lg text-balance text-white/85">
            Clean water for our villages, and a simple way to check and pay
            your water bill from anywhere.
          </p>
          <ul class="flex flex-wrap gap-2">
            <li
              v-for="highlight in highlights"
              :key="highlight"
              class="rounded-full border border-white/25 bg-white/10 px-3.5 py-1.5 text-xs font-medium text-white/90 backdrop-blur"
            >
              {{ highlight }}
            </li>
          </ul>
        </div>

        <Card
          class="w-full border-white/20 bg-white/10 text-white shadow-2xl backdrop-blur-xl"
        >
          <CardHeader>
            <CardTitle>Pay your water bill</CardTitle>
            <CardDescription class="text-white/70">
              No sign-in needed. Use the phone number or email registered
              with the society.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form class="flex flex-col gap-5" @submit.prevent="submit">
              <div class="grid gap-2">
                <Label for="account_number">Account number</Label>
                <Input
                  id="account_number"
                  v-model="form.account_number"
                  type="text"
                  required
                  autocomplete="off"
                  placeholder="e.g. ACC-0001"
                  class="border-white/25 bg-white/10 text-white placeholder:text-white/50"
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
                  class="border-white/25 bg-white/10 text-white placeholder:text-white/50"
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
                Continue to payment
              </Button>

              <p class="text-center text-xs text-white/60">
                Payments are processed securely by PayHere.
              </p>
            </form>
          </CardContent>
        </Card>
      </div>
    </main>

    <footer
      class="relative mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-2 px-6 py-5 text-xs text-white/70"
    >
      <p>© {{ currentYear }} {{ societyName }}.</p>
      <p>Serving Galekale &amp; Bombrawa, Medamahanuwara</p>
    </footer>
  </div>
</template>
