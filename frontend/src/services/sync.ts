import { api } from '@/services/api';
import { listOutbox, removeOutbox } from '@/services/idb';

export async function flushOutbox() {
  const items = await listOutbox();
  for (const item of items) {
    try {
      await api.post(item.endpoint, item.payload);
      await removeOutbox(item.id);
    } catch {
      break;
    }
  }
}
