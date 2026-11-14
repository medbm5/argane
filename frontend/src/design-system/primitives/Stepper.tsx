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
        'inline-flex h-10 items-center rounded-pill border border-line bg-surface text-fg',
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
        className="grid size-10 place-items-center rounded-pill transition-colors hover:bg-surface-alt disabled:opacity-40"
      >
        <Minus aria-hidden className="size-4" />
      </button>
      <output aria-live="polite" className="tabular min-w-8 text-center font-medium">
        {value}
      </output>
      <button
        type="button"
        aria-label={`Ajouter une place (${label})`}
        disabled={value >= max}
        onClick={() => {
          onChange(clamp(value + step))
        }}
        className="grid size-10 place-items-center rounded-pill transition-colors hover:bg-surface-alt disabled:opacity-40"
      >
        <Plus aria-hidden className="size-4" />
      </button>
    </div>
  )
}
