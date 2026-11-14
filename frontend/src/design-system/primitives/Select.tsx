import * as RadixSelect from '@radix-ui/react-select'
import { Check, ChevronDown } from 'lucide-react'
import { useId } from 'react'
import { cn } from '../cn'
import { fieldClasses } from './fieldStyles'
import type { FieldProps } from './Input'

export interface SelectOption {
  value: string
  label: string
  description?: string
  disabled?: boolean
}

export interface SelectProps extends FieldProps {
  value: string | undefined
  onValueChange: (value: string) => void
  options: SelectOption[]
  placeholder?: string
  disabled?: boolean
  className?: string
  size?: 'sm' | 'md'
}

export function Select({
  label,
  hint,
  error,
  hideLabel,
  value,
  onValueChange,
  options,
  placeholder = 'Choisir…',
  disabled,
  className,
  size = 'md',
}: SelectProps) {
  const id = useId()

  return (
    <div className={cn('flex flex-col gap-1.5', className)}>
      <label
        id={`${id}-label`}
        className={cn('text-sm font-medium text-fg', hideLabel && 'sr-only')}
      >
        {label}
      </label>
      <RadixSelect.Root value={value} onValueChange={onValueChange} disabled={disabled}>
        <RadixSelect.Trigger
          aria-labelledby={`${id}-label`}
          aria-invalid={error ? true : undefined}
          className={cn(
            fieldClasses,
            'inline-flex items-center justify-between gap-2 text-left data-placeholder:text-muted',
            size === 'sm' && 'h-8 px-2 text-sm',
          )}
        >
          <RadixSelect.Value placeholder={placeholder} />
          <RadixSelect.Icon>
            <ChevronDown aria-hidden className="size-4 text-muted" />
          </RadixSelect.Icon>
        </RadixSelect.Trigger>
        <RadixSelect.Portal>
          <RadixSelect.Content
            position="popper"
            sideOffset={4}
            className="z-50 max-h-80 min-w-(--radix-select-trigger-width) overflow-hidden rounded-md border border-line bg-surface text-fg shadow-float"
          >
            <RadixSelect.Viewport className="p-1">
              {options.map((option) => (
                <RadixSelect.Item
                  key={option.value}
                  value={option.value}
                  disabled={option.disabled}
                  className={cn(
                    'relative flex cursor-pointer flex-col rounded-sm py-2 pr-3 pl-8 text-sm outline-none select-none',
                    'data-disabled:cursor-not-allowed data-disabled:opacity-50 data-highlighted:bg-surface-alt',
                  )}
                >
                  <RadixSelect.ItemIndicator className="absolute top-2.5 left-2">
                    <Check aria-hidden className="size-4 text-accent" />
                  </RadixSelect.ItemIndicator>
                  <RadixSelect.ItemText>{option.label}</RadixSelect.ItemText>
                  {option.description ? (
                    <span className="text-xs text-muted">{option.description}</span>
                  ) : null}
                </RadixSelect.Item>
              ))}
            </RadixSelect.Viewport>
          </RadixSelect.Content>
        </RadixSelect.Portal>
      </RadixSelect.Root>
      {error ? <p className="text-sm text-danger">{error}</p> : null}
      {!error && hint ? <p className="text-sm text-muted">{hint}</p> : null}
    </div>
  )
}
