import { defineStore } from 'pinia';
import { api } from '@/services/api';

type NotificationEntry = {
  id: number;
  type: string;
  title: string;
  body: string;
  is_read: number;
  created_at: string;
};

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    items: [] as NotificationEntry[]
  }),
  getters: {
    unreadCount(state): number {
      return state.items.filter((item) => Number(item.is_read) === 0).length;
    }
  },
  actions: {
    async fetch() {
      const data = await api.get<{ notifications: NotificationEntry[] }>('/notifications');
      this.items = data.notifications;
    },
    async markRead(ids: number[] = []) {
      await api.post('/notifications/read', { ids });
      await this.fetch();
    }
  }
});
