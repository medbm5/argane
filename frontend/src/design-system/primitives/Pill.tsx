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
        'inline-flex items-center gap-2 border-2 border-fg px-4 py-1.5 text-sm font-bold tracking-wide whitespace-nowrap uppercase',
        'transition-[background-color,box-shadow,transform] duration-150 ease-theatre disabled:pointer-events-none disabled:opacity-40',
        selected
          ? 'bg-pop-yellow text-ink-900 shadow-[3px_3px_0_var(--c-text)]'
          : 'bg-surface text-fg hover:-translate-y-px hover:shadow-[3px_3px_0_var(--c-text)]',
        className,
      )}
      {...props}
    >
      {children}
    </button>
  )
}
