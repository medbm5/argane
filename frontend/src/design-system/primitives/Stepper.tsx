import { Minus, Plus } from 'lucide-react'
import { cn } from '../cn'

export interface StepperProps {
  label: string
  value: number
  onChange: (value: number) => void
  min?: number
  max?: number
  step?: number
  className?: string
}

/** Quantity picker (standing zones, group tickets). */
export function Stepper({
  label,
  value,
  onChange,
  min = 0,
  max = Number.POSITIVE_INFINITY,
  step = 1,
  className,
}: StepperProps) {
  const clamp = (n: number) => Math.min(max, Math.max(min, n))

  return (
    <div
      role="group"
      aria-label={label}
      className={cn(
        'inline-flex h-11 items-center border-2 border-fg bg-surface text-fg shadow-[3px_3px_0_var(--c-text)]',
        className,
      )}
    >
      <button
        type="button"
        aria-label={`Retirer une place (${label})`}
        disabled={value <= min}
        onClick={() => {
          onChange(clamp(value - step))
        }}
        className="grid size-10 place-items-center transition-colors hover:bg-pop-yellow hover:text-ink-900 disabled:opacity-40 disabled:hover:bg-transparent"
      >
        <Minus aria-hidden className="size-4" />
      </button>
      <output aria-live="polite" className="tabular min-w-8 text-center font-display text-xl">
        {value}
      </output>
      <button
        type="button"
        aria-label={`Ajouter une place (${label})`}
        disabled={value >= max}
        onClick={() => {
          onChange(clamp(value + step))
        }}
        className="grid size-10 place-items-center transition-colors hover:bg-pop-yellow hover:text-ink-900 disabled:opacity-40 disabled:hover:bg-transparent"
      >
        <Plus aria-hidden className="size-4" />
      </button>
    </div>
  )
}
