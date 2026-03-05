export async function request(method, path, body = null) {
  const base = window.LR_DATA?.rest_url || '';
  const res = await fetch(base + path, {
    method,
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.LR_DATA?.nonce || '' },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await res.json();
  if (!res.ok) throw new Error(json?.message || 'Request failed');
  return json;
}
