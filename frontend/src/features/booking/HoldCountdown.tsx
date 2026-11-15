import { Clock } from 'lucide-react'
import { useEffect, useState } from 'react'

function secondsUntil(iso: string): number {
  return Math.max(0, Math.round((new Date(iso).getTime() - Date.now()) / 1000))
}

const format = (s: number) => `${String(Math.floor(s / 60))}:${String(s % 60).padStart(2, '0')}`

/**
 * Sticky countdown for a hold: a draining bar that turns red in the last two minutes.
 * The visible clock ticks every second; screen readers hear it only at each minute and
 * in the last 30 seconds, not every tick.
 */
export function HoldCountdown({
  expiresAt,
  totalSeconds = 600,
  onExpire,
}: {
  expiresAt: string
  totalSeconds?: number
  onExpire: () => void
}) {
  const [left, setLeft] = useState(() => secondsUntil(expiresAt))

  useEffect(() => {
    const tick = () => {
      const s = secondsUntil(expiresAt)
      setLeft(s)
      if (s === 0) onExpire()
      return s
    }
    if (tick() === 0) return
    const id = setInterval(() => {
      if (tick() === 0) clearInterval(id)
    }, 1000)
    return () => {
      clearInterval(id)
    }
  }, [expiresAt, onExpire])

  const urgent = left <= 120
  const announce = left % 60 === 0 || (left <= 30 && left % 10 === 0)

  return (
    <div
      className={`relative overflow-hidden border-2 border-paper-100 ${urgent ? 'bg-pop-red text-ink-900' : 'bg-pop-yellow text-ink-900'}`}
    >
      <div
        aria-hidden
        className="absolute inset-y-0 left-0 bg-ink-900/15 transition-[width] duration-1000 ease-linear"
        style={{ width: `${String(100 - (left / totalSeconds) * 100)}%` }}
      />
      <p className="relative flex items-center justify-between gap-3 px-4 py-2.5 font-bold">
        <span className="flex items-center gap-2">
          <Clock aria-hidden className="size-4" />
          Places réservées pour vous
        </span>
        <span className="tabular font-display text-2xl" aria-hidden>
          {format(left)}
        </span>
        <span className="sr-only" aria-live="polite">
          {announce ? `Il reste ${format(left).replace(':', ' minutes ')} secondes.` : ''}
        </span>
      </p>
    </div>
  )
}
