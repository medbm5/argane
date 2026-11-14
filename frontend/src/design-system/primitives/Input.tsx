import { useId, type InputHTMLAttributes, type ReactNode, type Ref } from 'react'
import { cn } from '../cn'
import { fieldClasses } from './fieldStyles'

export interface FieldProps {
  label: string
  hint?: string
  error?: string
  /** Visually hide the label (still read by screen readers). */
  hideLabel?: boolean
}

export interface InputProps extends InputHTMLAttributes<HTMLInputElement>, FieldProps {
  trailing?: ReactNode
  ref?: Ref<HTMLInputElement>
}

export function Input({
  label,
  hint,
  error,
  hideLabel,
  trailing,
  className,
  id,
  ref,
  ...props
}: InputProps) {
  const autoId = useId()
  const inputId = id ?? autoId
  const describedBy = error ? `${inputId}-error` : hint ? `${inputId}-hint` : undefined

  return (
    <div className={cn('flex flex-col gap-1.5', className)}>
      <label
        htmlFor={inputId}
        className={cn('text-sm font-medium text-fg', hideLabel && 'sr-only')}
      >
        {label}
      </label>
      <div className="relative">
        <input
          ref={ref}
          id={inputId}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
          className={cn(fieldClasses, trailing ? 'pr-10' : undefined)}
          {...props}
        />
        {trailing ? (
          <span className="absolute inset-y-0 right-3 flex items-center text-muted">
            {trailing}
          </span>
        ) : null}
      </div>
      {error ? (
        <p id={`${inputId}-error`} className="text-sm text-danger">
          {error}
        </p>
      ) : hint ? (
        <p id={`${inputId}-hint`} className="text-sm text-muted">
          {hint}
        </p>
      ) : null}
    </div>
  )
}
