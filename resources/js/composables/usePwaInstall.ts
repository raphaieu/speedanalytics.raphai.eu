import { onMounted, ref } from 'vue';

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const DISMISS_KEY = 'speedway-pwa-install-dismissed';

export function usePwaInstall() {
  const canInstall = ref(false);
  const isInstalled = ref(false);
  const isDismissed = ref(false);

  let deferredPrompt: BeforeInstallPromptEvent | null = null;

  onMounted(() => {
    isDismissed.value = localStorage.getItem(DISMISS_KEY) === '1';

    if (window.matchMedia('(display-mode: standalone)').matches) {
      isInstalled.value = true;
      return;
    }

    window.addEventListener('beforeinstallprompt', (event) => {
      event.preventDefault();
      deferredPrompt = event as BeforeInstallPromptEvent;
      canInstall.value = true;
    });

    window.addEventListener('appinstalled', () => {
      isInstalled.value = true;
      canInstall.value = false;
      deferredPrompt = null;
    });
  });

  async function install(): Promise<void> {
    if (!deferredPrompt) {
      return;
    }

    await deferredPrompt.prompt();
    const choice = await deferredPrompt.userChoice;

    deferredPrompt = null;
    canInstall.value = false;

    if (choice.outcome === 'accepted') {
      isInstalled.value = true;
    }
  }

  function dismiss(): void {
    isDismissed.value = true;
    localStorage.setItem(DISMISS_KEY, '1');
  }

  return {
    canInstall,
    isInstalled,
    isDismissed,
    install,
    dismiss,
  };
}
