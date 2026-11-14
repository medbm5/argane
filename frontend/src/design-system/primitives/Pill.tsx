import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { cn } from '../cn'

export interface PillProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  selected?: boolean
  children: ReactNode
}

/** Toggleable chip: date pills, time slots, category filters. */
export function Pill({
  selected = false,
  className,
  children,
  type = 'button',
  ...props
}: PillProps) {
  return (
    <button
      type={type}
      aria-pressed={selected}
      className={cn(
        'inline-flex items-center gap-1.5 rounded-pill border px-4 py-1.5 text-sm font-medium whitespace-nowrap',
        'transition-colors duration-200 ease-theatre disabled:pointer-events-none disabled:opacity-40',
        selected
          ? 'border-primary bg-primary text-on-primary'
          : 'border-line bg-transparent text-fg hover:border-fg',
        className,
      )}
      {...props}
    >
      {children}
    </button>
  )
}
