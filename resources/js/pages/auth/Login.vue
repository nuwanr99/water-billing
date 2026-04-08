<script setup lang="ts">
import { Form, Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { store } from '@/routes/login';
import { request as otpRequest, verify as otpVerify } from '@/routes/login/otp';
import { request } from '@/routes/password';

defineOptions({
  layout: {
    title: 'Log in to your account',
    description:
      'Log in with a one-time code sent to your WhatsApp, or your email and password',
  },
});

defineProps<{
  status?: string;
  canResetPassword: boolean;
}>();

const otpSent = ref(false);

const tabTriggerClass =
  'dark:data-[state=active]:bg-primary dark:data-[state=active]:text-primary-foreground dark:data-[state=active]:border-transparent';

const otpForm = useForm({
  phone: '',
  code: '',
  remember: false,
});

function sendOtpCode() {
  otpForm.post(otpRequest.url(), {
    preserveScroll: true,
    onSuccess: () => {
      otpSent.value = true;
      otpForm.code = '';
    },
  });
}

function verifyOtpCode() {
  otpForm.post(otpVerify.url(), {
    preserveScroll: true,
  });
}
</script>

<template>
  <Head title="Log in" />

  <div
    v-if="status"
    class="mb-4 text-center text-sm font-medium text-green-600"
  >
    {{ status }}
  </div>

  <PasskeyVerify separator="Or continue with" />

  <Tabs default-value="otp" class="gap-6">
    <TabsList class="grid w-full grid-cols-2">
      <TabsTrigger value="otp" :class="tabTriggerClass"
        >Mobile login</TabsTrigger
      >
      <TabsTrigger value="password" :class="tabTriggerClass">
        Email login
      </TabsTrigger>
    </TabsList>

    <TabsContent value="otp">
      <form
        class="flex flex-col gap-6"
        @submit.prevent="otpSent ? verifyOtpCode() : sendOtpCode()"
      >
        <div class="grid gap-6">
          <div class="grid gap-2">
            <Label for="phone">Phone number</Label>
            <Input
              id="phone"
              v-model="otpForm.phone"
              type="tel"
              name="phone"
              required
              autofocus
              autocomplete="tel"
              placeholder="07XXXXXXXX"
              :readonly="otpSent"
            />
            <InputError :message="otpForm.errors.phone" />
          </div>

          <div v-if="otpSent" class="grid gap-2">
            <Label for="code">One-time code</Label>
            <Input
              id="code"
              v-model="otpForm.code"
              type="text"
              name="code"
              inputmode="numeric"
              autocomplete="one-time-code"
              maxlength="6"
              required
              placeholder="6-digit code"
            />
            <InputError :message="otpForm.errors.code" />
            <button
              type="button"
              class="text-left text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
              :disabled="otpForm.processing"
              @click="sendOtpCode"
            >
              Resend code
            </button>
          </div>

          <div v-if="otpSent" class="flex items-center justify-between">
            <Label for="otp-remember" class="flex items-center space-x-3">
              <Checkbox id="otp-remember" v-model="otpForm.remember" />
              <span>Remember me</span>
            </Label>
          </div>

          <Button
            type="submit"
            class="w-full"
            :disabled="otpForm.processing"
            data-test="otp-button"
          >
            <Spinner v-if="otpForm.processing" />
            {{ otpSent ? 'Log in' : 'Send code via WhatsApp' }}
          </Button>
        </div>
      </form>
    </TabsContent>

    <TabsContent value="password">
      <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
      >
        <div class="grid gap-6">
          <div class="grid gap-2">
            <Label for="email">Email address</Label>
            <Input
              id="email"
              type="email"
              name="email"
              required
              :tabindex="1"
              autocomplete="email"
              placeholder="email@example.com"
            />
            <InputError :message="errors.email" />
          </div>

          <div class="grid gap-2">
            <div class="flex items-center justify-between">
              <Label for="password">Password</Label>
              <TextLink
                v-if="canResetPassword"
                :href="request()"
                class="text-sm"
                :tabindex="5"
              >
                Forgot your password?
              </TextLink>
            </div>
            <PasswordInput
              id="password"
              name="password"
              required
              :tabindex="2"
              autocomplete="current-password"
              placeholder="Password"
            />
            <InputError :message="errors.password" />
          </div>

          <div class="flex items-center justify-between">
            <Label for="remember" class="flex items-center space-x-3">
              <Checkbox id="remember" name="remember" :tabindex="3" />
              <span>Remember me</span>
            </Label>
          </div>

          <Button
            type="submit"
            class="w-full"
            :tabindex="4"
            :disabled="processing"
            data-test="login-button"
          >
            <Spinner v-if="processing" />
            Log in
          </Button>
        </div>
      </Form>
    </TabsContent>
  </Tabs>
</template>
