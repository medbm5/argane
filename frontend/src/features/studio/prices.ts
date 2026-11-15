/** "125,50" or "125.5" → 12550; empty → null (no price: not sold in that zone). */
export function parseAmount(text: string): number | null | 'invalid' {
  const clean = text.trim().replace(/\s/g, '').replace(',', '.')
  if (clean === '') return null
  if (!/^\d+(\.\d{1,2})?$/.test(clean)) return 'invalid'
  return Math.round(Number(clean) * 100)
}
