import { useId, type ReactNode } from 'react'
import { cn } from '../cn'

const SHAPES = {
  /** Soft blob with wavy edges (landing poster). */
  organic:
    'M0.08,0.12 C0.2,0.02 0.34,0.08 0.5,0.04 C0.66,0 0.82,0.06 0.93,0.15 C1.01,0.27 0.96,0.42 0.99,0.57 C1.02,0.74 0.95,0.88 0.82,0.95 C0.67,1.02 0.52,0.95 0.37,0.98 C0.22,1.01 0.08,0.95 0.03,0.81 C-0.02,0.66 0.04,0.52 0.01,0.37 C-0.01,0.25 0.01,0.18 0.08,0.12 Z',
  /** Proscenium arch. */
  arch: 'M0,1 L0,0.42 C0,0.16 0.22,0 0.5,0 C0.78,0 1,0.16 1,0.42 L1,1 Z',
} as const

export interface WaveMaskProps {
  children: ReactNode
  shape?: keyof typeof SHAPES
  className?: string
}

/** Clips its content (usually a poster) to an organic SVG shape. */
export function WaveMask({ children, shape = 'organic', className }: WaveMaskProps) {
  const id = `wave-${useId().replace(/:/g, '')}`

  return (
    <div className={cn('relative', className)} style={{ clipPath: `url(#${id})` }}>
      <svg width="0" height="0" aria-hidden className="absolute">
        <defs>
          <clipPath id={id} clipPathUnits="objectBoundingBox">
            <path d={SHAPES[shape]} />
          </clipPath>
        </defs>
      </svg>
      {children}
    </div>
  )
}
