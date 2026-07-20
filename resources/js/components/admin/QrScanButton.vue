<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CameraOff, ScanQrCode } from '@lucide/vue';
import QrScanner from 'qr-scanner';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { resolve } from '@/routes/qr';

const props = defineProps<{
  /** Which workflow asked, so the server knows where to send the user. */
  context: 'meter-reading' | 'payment';
  /** Current search filter, preserved when the scan finds nothing. */
  search?: string;
}>();

const CAMERA_STORAGE_KEY = 'qr-scanner-camera';

const open = ref(false);
const state = ref<'requesting' | 'active' | 'error'>('requesting');
const cameras = ref<QrScanner.Camera[]>([]);
const selectedCameraId = ref('');
const videoEl = ref<HTMLVideoElement | null>(null);

let scanner: QrScanner | null = null;

const teardown = (): void => {
  if (scanner) {
    scanner.stop();
    scanner.destroy();
    scanner = null;
  }
};

/**
 * A code is accepted only when it parses as a URL carrying non-empty
 * `account` and `meter` query params (host and path do not matter).
 */
const parseCode = (data: string): { account: string; meter: string } | null => {
  let url: URL;

  try {
    url = new URL(data);
  } catch {
    return null;
  }

  const account = url.searchParams.get('account');
  const meter = url.searchParams.get('meter');

  if (!account || !meter) {
    return null;
  }

  return { account, meter };
};

const onDecode = (result: QrScanner.ScanResult): void => {
  const match = parseCode(result.data);

  if (!match) {
    return;
  }

  teardown();
  open.value = false;

  // The server resolves the pair and redirects straight to the account's
  // page, so the list underneath never re-renders in between.
  router.get(resolve().url, {
    ...match,
    context: props.context,
    ...(props.search ? { search: props.search } : {}),
  });
};

const startScanner = async (): Promise<void> => {
  state.value = 'requesting';
  cameras.value = [];
  selectedCameraId.value = '';

  await nextTick();

  if (!open.value || !videoEl.value) {
    return;
  }

  const preferred = localStorage.getItem(CAMERA_STORAGE_KEY);

  // Track this invocation's own instance: if the dialog is closed and
  // reopened while start() is pending, teardown() destroys `own` and a
  // newer invocation replaces `scanner`, so this one must not touch the
  // shared state anymore.
  const own = new QrScanner(videoEl.value, onDecode, {
    preferredCamera: preferred ?? 'environment',
    highlightScanRegion: true,
    returnDetailedScanResult: true,
  });

  scanner = own;

  try {
    await own.start();
  } catch {
    if (scanner !== own) {
      return;
    }

    teardown();

    if (open.value) {
      state.value = 'error';
    }

    return;
  }

  if (scanner !== own) {
    return;
  }

  if (!open.value) {
    // The dialog was closed while the camera was still starting.
    teardown();

    return;
  }

  state.value = 'active';
  const found = await QrScanner.listCameras(true);

  if (scanner !== own) {
    return;
  }

  cameras.value = found;

  if (preferred && found.some((camera) => camera.id === preferred)) {
    selectedCameraId.value = preferred;
  }
};

watch(open, (isOpen) => {
  if (isOpen) {
    void startScanner();
  } else {
    teardown();
  }
});

watch(selectedCameraId, (deviceId) => {
  if (!deviceId || !scanner) {
    return;
  }

  localStorage.setItem(CAMERA_STORAGE_KEY, deviceId);
  void scanner.setCamera(deviceId);
});

onBeforeUnmount(teardown);
</script>

<template>
  <Dialog v-model:open="open">
    <DialogTrigger as-child>
      <Button
        type="button"
        variant="outline"
        size="icon"
        aria-label="Scan bill QR code"
      >
        <ScanQrCode class="size-4" />
      </Button>
    </DialogTrigger>
    <DialogContent>
      <DialogHeader class="space-y-3">
        <DialogTitle>Scan bill QR code</DialogTitle>
        <DialogDescription>
          Point the camera at the QR code printed on the bill.
        </DialogDescription>
      </DialogHeader>

      <div
        class="relative aspect-square w-full overflow-hidden rounded-lg bg-muted"
      >
        <video ref="videoEl" class="size-full object-cover" muted playsinline />

        <div
          v-if="state !== 'active'"
          class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-muted px-6 text-center"
        >
          <template v-if="state === 'requesting'">
            <Spinner class="text-muted-foreground" />
            <p class="text-sm text-muted-foreground">Starting camera...</p>
          </template>
          <template v-else>
            <div
              class="flex size-12 items-center justify-center rounded-full bg-background"
            >
              <CameraOff class="size-6 text-muted-foreground" />
            </div>
            <div class="space-y-1">
              <p class="font-medium">Camera unavailable</p>
              <p class="text-sm text-muted-foreground">
                Allow camera access in your browser, or check that this device
                has a camera.
              </p>
            </div>
          </template>
        </div>
      </div>

      <Select
        v-if="state === 'active' && cameras.length > 0"
        v-model="selectedCameraId"
      >
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Choose a camera" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="camera in cameras"
            :key="camera.id"
            :value="camera.id"
          >
            {{ camera.label }}
          </SelectItem>
        </SelectContent>
      </Select>
    </DialogContent>
  </Dialog>
</template>
