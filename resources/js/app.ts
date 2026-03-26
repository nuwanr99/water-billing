import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import MeterReaderLayout from '@/layouts/MeterReaderLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  layout: (name) => {
    switch (true) {
      case name === 'Welcome':
        return null;
      // Bare print sheets: opened in a new tab, auto-print, no chrome —
      // any layout wrapper distorts the 78mm thermal geometry.
      case name.startsWith('print/'):
        return null;
      // Public payment pages: self-contained, no app chrome (D-36).
      case name.startsWith('pay/'):
        return null;
      case name.startsWith('auth/'):
        return AuthLayout;
      case name.startsWith('meter-readings/'):
        return MeterReaderLayout;
      case name.startsWith('settings/'):
        return [AppLayout, SettingsLayout];
      default:
        return AppLayout;
    }
  },
  progress: {
    color: '#4B5563',
  },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
