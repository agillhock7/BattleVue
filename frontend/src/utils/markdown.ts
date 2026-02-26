import DOMPurify from 'dompurify';
import { marked } from 'marked';

marked.setOptions({
  gfm: true,
  breaks: true,
});

export function renderAssistantMarkdown(input: string): string {
  const raw = marked.parse(input || '');
  const html = typeof raw === 'string' ? raw : String(raw);
  return DOMPurify.sanitize(html, {
    USE_PROFILES: { html: true },
  });
}
