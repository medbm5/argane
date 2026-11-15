import { useId, type ReactNode } from 'react'
import { cn } from '../cn'

export interface RotatingBadgeProps {
  /** Text repeated around the circle, e.g. "Saison 26/27 · Théâtre Argane · " */
  text: string
  center?: ReactNode
  size?: number
  className?: string
}

/** Slowly rotating circular text (paused under reduced motion). */
export function RotatingBadge({ text, center, size = 144, className }: RotatingBadgeProps) {
  const id = useId()
  const pathId = `badge-path-${id}`

  return (
    <span
      className={cn('relative inline-grid place-items-center', className)}
      style={{ width: size, height: size }}
    >
      <svg
        viewBox="0 0 100 100"
        aria-hidden
        className="absolute inset-0 size-full animate-[spin-slow_28s_linear_infinite] motion-reduce:animate-none"
      >
        <defs>
          <path id={pathId} d="M50,50 m-38,0 a38,38 0 1,1 76,0 a38,38 0 1,1 -76,0" />
        </defs>
        <text className="fill-current font-sans text-[9.6px] font-medium tracking-[0.18em] uppercase">
          <textPath href={`#${pathId}`} textLength="236">
            {text}
          </textPath>
        </text>
      </svg>
      <span className="sr-only">{text}</span>
      {center ? <span className="relative">{center}</span> : null}
    </span>
  )
}
