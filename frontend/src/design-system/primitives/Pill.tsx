import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { Link } from 'react-router'
import { cn } from '../cn'

export interface PillProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  selected?: boolean
  /** Render as a router link (date pills, time slots): uses aria-current instead of aria-pressed. */
  asLink?: string
  children: ReactNode
}

const pillClasses = (selected: boolean, className?: string) =>
  cn(
    'inline-flex items-center gap-2 border-2 border-fg px-4 py-1.5 text-sm font-bold tracking-wide whitespace-nowrap uppercase',
    'transition-[background-color,box-shadow,transform] duration-150 ease-theatre disabled:pointer-events-none disabled:opacity-40',
    selected
      ? 'bg-pop-yellow text-ink-900 shadow-[3px_3px_0_var(--c-text)]'
      : 'bg-surface text-fg hover:-translate-y-px hover:shadow-[3px_3px_0_var(--c-text)]',
    className,
  )

/** Toggleable chip: date pills, time slots, category filters. */
export function Pill({
  selected = false,
  asLink,
  className,
  children,
  type = 'button',
  ...props
}: PillProps) {
  if (asLink !== undefined) {
    return (
      <Link
        to={asLink}
        aria-current={selected ? 'page' : undefined}
        className={pillClasses(selected, className)}
      >
        {children}
      </Link>
    )
  }
  return (
    <button
      type={type}
      aria-pressed={selected}
      className={pillClasses(selected, className)}
      {...props}
    >
      {children}
    </button>
  )
}
