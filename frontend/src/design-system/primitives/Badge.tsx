import { cva, type VariantProps } from 'class-variance-authority'
import type { HTMLAttributes } from 'react'
import { cn } from '../cn'
import { STATUS_BADGES } from './status'

const badgeVariants = cva(
  'inline-flex items-center gap-1 rounded-pill border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
  {
    variants: {
      tone: {
        neutral: 'border-line bg-surface text-muted',
        accent: 'border-accent/40 bg-transparent text-accent',
        success: 'border-success/40 bg-success/10 text-success',
        danger: 'border-danger/40 bg-danger/10 text-danger',
        highlight: 'border-highlight bg-highlight text-paper-50',
        gilt: 'border-gilt-400 bg-gilt-400/20 text-fg',
        ink: 'border-ink-900 bg-ink-900 text-paper-50',
      },
    },
    defaultVariants: { tone: 'neutral' },
  },
)

export type BadgeTone = NonNullable<VariantProps<typeof badgeVariants>['tone']>

export interface BadgeProps
  extends HTMLAttributes<HTMLSpanElement>, VariantProps<typeof badgeVariants> {}

export function Badge({ className, tone, ...props }: BadgeProps) {
  return <span className={cn(badgeVariants({ tone }), className)} {...props} />
}

export function StatusBadge({ status, className }: { status: string; className?: string }) {
  const config = STATUS_BADGES[status] ?? { label: status, tone: 'neutral' as const }
  return (
    <Badge tone={config.tone} className={className}>
      {config.label}
    </Badge>
  )
}
