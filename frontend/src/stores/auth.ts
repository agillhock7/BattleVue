import { defineStore } from 'pinia';
import { api } from '@/services/api';
import type { User } from '@/types/models';

type AuthState = {
  user: User | null;
  ready: boolean;
};

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    ready: false
  }),
  actions: {
    async fetchMe() {
      try {
        const data = await api.get<{ user: User | null }>('/auth/me');
        this.user = data.user;
      } finally {
        this.ready = true;
      }
    },
    async login(identity: string, password: string) {
      const data = await api.post<{ user: User }>('/auth/login', { identity, password });
      this.user = data.user;
    },
    async register(username: string, email: string, password: string) {
      await api.post('/auth/register', { username, email, password });
      await this.login(username, password);
    },
    async logout() {
      try {
        await api.post('/auth/logout');
      } catch {
        // Ignore; clear local state anyway.
      }
      this.user = null;
    }
  }
});
