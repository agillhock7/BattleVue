import { defineStore } from 'pinia';

export const useNetworkStore = defineStore('network', {
  state: () => ({
    online: navigator.onLine,
    apiHealthy: true,
    started: false
  }),
  actions: {
    start() {
      if (this.started) {
        return;
      }
      this.started = true;

      const syncOnline = () => {
        this.online = navigator.onLine;
      };

      window.addEventListener('online', syncOnline);
      window.addEventListener('offline', syncOnline);

      setInterval(async () => {
        if (!this.online) {
          this.apiHealthy = false;
          return;
        }

        try {
          const res = await fetch('/api/auth/me', { credentials: 'include' });
          this.apiHealthy = res.ok;
        } catch {
          this.apiHealthy = false;
        }
      }, 10000);
    }
  }
});
