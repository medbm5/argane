import { cva, type VariantProps } from 'class-variance-authority'
import type { HTMLAttributes } from 'react'
import { cn } from '../cn'
import { STATUS_BADGES } from './status'

const badgeVariants = cva(
  'inline-flex items-center gap-1 border-2 px-2 py-0.5 text-[0.7rem] font-bold tracking-[0.08em] whitespace-nowrap uppercase',
  {
    variants: {
      tone: {
        neutral: 'border-fg bg-surface text-fg',
        accent: 'border-fg bg-pop-teal text-ink-900',
        success: 'border-fg bg-pop-green text-ink-900',
        danger: 'border-fg bg-pop-red text-paper-50',
        highlight: 'border-fg bg-pop-pink text-ink-900',
        gilt: 'border-fg bg-pop-yellow text-ink-900',
        ink: 'border-fg bg-ink-900 text-paper-50',
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
