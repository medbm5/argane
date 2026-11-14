import { Slot } from '@radix-ui/react-slot'
import { cva, type VariantProps } from 'class-variance-authority'
import { LoaderCircle } from 'lucide-react'
import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { cn } from '../cn'

const buttonVariants = cva(
  [
    'inline-flex items-center justify-center gap-2 font-sans font-medium whitespace-nowrap select-none',
    'rounded-sm border transition-[background-color,color,border-color,transform] duration-200 ease-theatre',
    'active:translate-y-px disabled:pointer-events-none disabled:opacity-50',
    'focus-visible:outline-2 focus-visible:outline-offset-2',
  ],
  {
    variants: {
      variant: {
        primary:
          'border-primary bg-primary text-on-primary hover:bg-sepia-800 hover:border-sepia-800',
        secondary: 'border-fg bg-transparent text-fg hover:bg-surface-alt',
        ghost: 'border-transparent bg-transparent text-fg hover:bg-surface-alt',
        gilt: [
          'border-gilt-400 bg-gilt-400 text-ink-900 shadow-[0_0_0_0_var(--stage-glow)]',
          'hover:bg-stage-glow hover:shadow-[0_0_24px_-4px_var(--stage-glow)]',
        ],
        danger: 'border-danger bg-transparent text-danger hover:bg-danger hover:text-paper-50',
      },
      size: {
        sm: 'h-8 px-3 text-sm',
        md: 'h-10 px-4 text-base',
        lg: 'h-12 px-6 text-lg',
      },
      block: {
        true: 'w-full',
      },
    },
    defaultVariants: {
      variant: 'primary',
      size: 'md',
    },
  },
)

export interface ButtonProps
  extends ButtonHTMLAttributes<HTMLButtonElement>, VariantProps<typeof buttonVariants> {
  /** Render the child element (e.g. a router Link) with button styles. */
  asChild?: boolean
  loading?: boolean
  leadingIcon?: ReactNode
}

export function Button({
  className,
  variant,
  size,
  block,
  asChild = false,
  loading = false,
  leadingIcon,
  disabled,
  children,
  type = 'button',
  ...props
}: ButtonProps) {
  const classes = cn(buttonVariants({ variant, size, block }), className)

  if (asChild) {
    return (
      <Slot className={classes} {...props}>
        {children}
      </Slot>
    )
  }

  return (
    <button
      type={type}
      className={classes}
      disabled={disabled === true || loading}
      aria-busy={loading || undefined}
      {...props}
    >
      {loading ? <LoaderCircle aria-hidden className="size-4 animate-spin" /> : leadingIcon}
      {children}
    </button>
  )
}
