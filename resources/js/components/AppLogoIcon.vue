<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
  inheritAttrs: false,
});

type Props = {
  className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    :class="className"
    v-bind="$attrs"
  >
    <path
      fill="currentColor"
      d="M12 2.25c0 0-7.25 8.15-7.25 13a7.25 7.25 0 1 0 14.5 0c0-4.85-7.25-13-7.25-13Z"
    />
  </svg>
</template>
