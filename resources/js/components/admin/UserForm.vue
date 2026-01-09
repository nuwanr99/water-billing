<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store, update } from '@/routes/admin/users';

type UserFormData = {
  id: number;
  first_name: string;
  last_name: string;
  phone: string;
  address: string;
  wa_number: string | null;
  email: string;
  roles: string[];
};

const props = defineProps<{
  mode: 'create' | 'edit';
  roles: string[];
  user?: UserFormData;
}>();

const form = useForm({
  first_name: props.user?.first_name ?? '',
  last_name: props.user?.last_name ?? '',
  phone: props.user?.phone ?? '',
  address: props.user?.address ?? '',
  wa_number: props.user?.wa_number ?? '',
  email: props.user?.email ?? '',
  password: '',
  password_confirmation: '',
  roles: [...(props.user?.roles ?? [])],
});

const toggleRole = (role: string, checked: boolean | 'indeterminate') => {
  form.roles =
    checked === true
      ? [...form.roles, role]
      : form.roles.filter((name) => name !== role);
};

const submit = () => {
  if (props.mode === 'create') {
    form.post(store().url);

    return;
  }

  form
    .transform((data) => {
      const payload: Record<string, unknown> = { ...data };

      delete payload.password;
      delete payload.password_confirmation;

      return payload;
    })
    .put(update(props.user!.id).url);
};
</script>

<template>
  <form class="max-w-2xl space-y-6" @submit.prevent="submit">
    <div class="grid gap-6 sm:grid-cols-2">
      <div class="grid gap-2">
        <Label for="first_name">First name</Label>
        <Input
          id="first_name"
          v-model="form.first_name"
          required
          autocomplete="given-name"
          placeholder="First name"
        />
        <InputError :message="form.errors.first_name" />
      </div>

      <div class="grid gap-2">
        <Label for="last_name">Last name</Label>
        <Input
          id="last_name"
          v-model="form.last_name"
          required
          autocomplete="family-name"
          placeholder="Last name"
        />
        <InputError :message="form.errors.last_name" />
      </div>
    </div>

    <div class="grid gap-2">
      <Label for="email">Email address</Label>
      <Input
        id="email"
        v-model="form.email"
        type="email"
        required
        autocomplete="off"
        placeholder="Email address"
      />
      <InputError :message="form.errors.email" />
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
      <div class="grid gap-2">
        <Label for="phone">Phone</Label>
        <Input
          id="phone"
          v-model="form.phone"
          type="tel"
          required
          placeholder="Phone number"
        />
        <InputError :message="form.errors.phone" />
      </div>

      <div class="grid gap-2">
        <Label for="wa_number">WhatsApp number (optional)</Label>
        <Input
          id="wa_number"
          v-model="form.wa_number"
          autocomplete="phone"
          type="tel"
          placeholder="WhatsApp number"
        />
        <InputError :message="form.errors.wa_number" />
      </div>
    </div>

    <div class="grid gap-2">
      <Label for="address">Address</Label>
      <Input
        id="address"
        v-model="form.address"
        required
        placeholder="Address"
      />
      <InputError :message="form.errors.address" />
    </div>

    <template v-if="mode === 'create'">
      <div class="grid gap-6 sm:grid-cols-2">
        <div class="grid gap-2">
          <Label for="password">Password</Label>
          <PasswordInput
            id="password"
            v-model="form.password"
            required
            autocomplete="new-password"
            placeholder="Password"
          />
          <InputError :message="form.errors.password" />
        </div>

        <div class="grid gap-2">
          <Label for="password_confirmation">Confirm password</Label>
          <PasswordInput
            id="password_confirmation"
            v-model="form.password_confirmation"
            required
            autocomplete="new-password"
            placeholder="Confirm password"
          />
          <InputError :message="form.errors.password_confirmation" />
        </div>
      </div>
    </template>

    <div class="grid gap-3">
      <Label>Roles</Label>
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <label
          v-for="role in roles"
          :key="role"
          class="flex items-center gap-2 text-sm"
        >
          <Checkbox
            :model-value="form.roles.includes(role)"
            @update:model-value="toggleRole(role, $event)"
          />
          <span>{{ role }}</span>
        </label>
      </div>
      <InputError :message="form.errors.roles" />
    </div>

    <div class="flex items-center gap-4">
      <Button type="submit" :disabled="form.processing">
        {{ mode === 'create' ? 'Create user' : 'Save changes' }}
      </Button>
      <Button variant="secondary" as-child>
        <Link :href="index()">Cancel</Link>
      </Button>
    </div>
  </form>
</template>
