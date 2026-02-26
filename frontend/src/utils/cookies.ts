export function readCookie(name: string): string {
  const parts = document.cookie.split(';').map((part) => part.trim());
  const match = parts.find((part) => part.startsWith(name + '='));
  return match ? decodeURIComponent(match.substring(name.length + 1)) : '';
}
