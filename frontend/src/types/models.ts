export type User = {
  id: number;
  username: string;
  email?: string;
  display_name: string;
  avatar_url?: string | null;
};

export type Track = {
  id: number;
  slug: string;
  title: string;
  description: string;
};

export type Quest = {
  id: number;
  track_id: number;
  track_slug: string;
  title: string;
  description: string;
  difficulty: 'easy' | 'medium' | 'hard';
};

export type QuestStep = {
  id: number;
  step_index: number;
  step_type: 'read' | 'quiz' | 'snippet' | 'checklist';
  payload: Record<string, unknown>;
  required: number;
};
