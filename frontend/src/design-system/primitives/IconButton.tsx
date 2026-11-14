import { cva, type VariantProps } from 'class-variance-authority'
import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { cn } from '../cn'

const iconButtonVariants = cva(
  [
    'inline-flex shrink-0 items-center justify-center rounded-pill border transition-colors duration-200 ease-theatre',
    'disabled:pointer-events-none disabled:opacity-50',
  ],
  {
    variants: {
      variant: {
        outline: 'border-line bg-surface text-fg hover:border-fg',
        ghost: 'border-transparent bg-transparent text-fg hover:bg-surface-alt',
        solid: 'border-primary bg-primary text-on-primary hover:bg-sepia-800',
      },
      size: {
        sm: 'size-8 [&_svg]:size-4',
        md: 'size-10 [&_svg]:size-5',
      },
    },
    defaultVariants: { variant: 'outline', size: 'md' },
  },
)

export interface IconButtonProps
  extends ButtonHTMLAttributes<HTMLButtonElement>, VariantProps<typeof iconButtonVariants> {
  /** Required: icon-only buttons need an accessible name. */
  label: string
  icon: ReactNode
}

export function IconButton({
  label,
  icon,
  className,
  variant,
  size,
  type = 'button',
  ...props
}: IconButtonProps) {
  return (
    <button
      type={type}
      aria-label={label}
      title={label}
      className={cn(iconButtonVariants({ variant, size }), className)}
      {...props}
    >
      {icon}
    </button>
  )
}
