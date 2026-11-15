/**
 * Display formatting. The API speaks UTC instants and integer minor units;
 * everything shown to people is in French, in the venue's time zone.
 */
export const VENUE_TIME_ZONE = 'Africa/Casablanca'

const moneyFormatters = new Map<string, Intl.NumberFormat>()

/** 45000 MAD → "450 MAD"; keeps cents only when there are some. */
export function formatMoney(cents: number, currency = 'MAD'): string {
  const whole = cents % 100 === 0
  const key = `${currency}:${String(whole)}`
  let formatter = moneyFormatters.get(key)
  if (!formatter) {
    formatter = new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency,
      currencyDisplay: 'code',
      minimumFractionDigits: whole ? 0 : 2,
      maximumFractionDigits: whole ? 0 : 2,
    })
    moneyFormatters.set(key, formatter)
  }
  // fr-FR puts the code after the number with a narrow no-break space; keep it.
  return formatter.format(cents / 100)
}

function venueFormat(options: Intl.DateTimeFormatOptions) {
  return new Intl.DateTimeFormat('fr-FR', { timeZone: VENUE_TIME_ZONE, ...options })
}

const longDate = venueFormat({ weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
const shortDate = venueFormat({ weekday: 'short', day: 'numeric', month: 'short' })
const dayNumber = venueFormat({ day: '2-digit' })
const monthShort = venueFormat({ month: 'short' })
const time = venueFormat({ hour: '2-digit', minute: '2-digit' })
const monthYear = venueFormat({ month: 'long', year: 'numeric' })
const isoMonth = venueFormat({ year: 'numeric', month: '2-digit' })

const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1)

/** "Vendredi 12 décembre 2026" */
export const formatLongDate = (iso: string) => capitalize(longDate.format(new Date(iso)))
/** "ven. 12 déc." */
export const formatShortDate = (iso: string) => shortDate.format(new Date(iso))
/** "20:00" */
export const formatTime = (iso: string) => time.format(new Date(iso))
/** "décembre 2026" */
export const formatMonthYear = (iso: string) => monthYear.format(new Date(iso))
/** { day: "12", month: "déc." } for date tiles */
export const dateTile = (iso: string) => ({
  day: dayNumber.format(new Date(iso)),
  month: monthShort.format(new Date(iso)).replace('.', ''),
})

/** "2026-12" in the venue time zone, for the month filter. */
export function venueMonth(iso: string): string {
  const parts = isoMonth.formatToParts(new Date(iso))
  const year = parts.find((p) => p.type === 'year')?.value ?? ''
  const month = parts.find((p) => p.type === 'month')?.value ?? ''
  return `${year}-${month}`
}

/** "2 h 20" */
export function formatDuration(minutes: number): string {
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  if (h === 0) return `${String(m)} min`
  return m === 0 ? `${String(h)} h` : `${String(h)} h ${String(m).padStart(2, '0')}`
}

export function formatAgeRating(age: number | null | undefined): string {
  return age ? `Dès ${String(age)} ans` : 'Tout public'
}

/** The next `count` season months from `from` (venue time), as { value: "2026-12", label: "décembre 2026" }. */
export function upcomingMonths(from: Date, count: number): { value: string; label: string }[] {
  const [y, m] = venueMonth(from.toISOString()).split('-').map(Number)
  return Array.from({ length: count }, (_, i) => {
    const mid = new Date(Date.UTC(y ?? 2026, (m ?? 1) - 1 + i, 15, 12))
    return { value: venueMonth(mid.toISOString()), label: formatMonthYear(mid.toISOString()) }
  })
}
