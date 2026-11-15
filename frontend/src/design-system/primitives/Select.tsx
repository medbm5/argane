import * as RadixSelect from '@radix-ui/react-select'
import { Check, ChevronDown } from 'lucide-react'
import { useId, type ReactNode } from 'react'
import { cn } from '../cn'
import { fieldClasses } from './fieldStyles'
import type { FieldProps } from './Input'

export interface SelectOption {
  value: string
  label: string
  description?: string
  disabled?: boolean
  /** A CSS colour shown as a small square before the label (e.g. a category colour). */
  swatch?: string
}

/** The open list: ink outline, hard shadow, slides in from the trigger. */
export function SelectMenu({ children, title }: { children: ReactNode; title?: string }) {
  return (
    <RadixSelect.Portal>
      <RadixSelect.Content
        position="popper"
        sideOffset={8}
        className={cn(
          'z-50 max-h-(--radix-select-content-available-height) min-w-(--radix-select-trigger-width) overflow-hidden',
          'border-2 border-ink-900 bg-paper-50 text-ink-900 shadow-[6px_6px_0_var(--ink-900)]',
          'origin-(--radix-select-content-transform-origin) animate-[rise-in_160ms_var(--ease-out)]',
        )}
      >
        {title ? (
          <p className="border-b-2 border-ink-900 bg-ink-900 px-4 py-1.5 font-display text-xs tracking-[0.2em] text-pop-yellow uppercase">
            {title}
          </p>
        ) : null}
        <RadixSelect.ScrollUpButton className="flex h-6 items-center justify-center bg-paper-50">
          <ChevronDown aria-hidden className="size-4 rotate-180" />
        </RadixSelect.ScrollUpButton>
        <RadixSelect.Viewport>{children}</RadixSelect.Viewport>
        <RadixSelect.ScrollDownButton className="flex h-6 items-center justify-center bg-paper-50">
          <ChevronDown aria-hidden className="size-4" />
        </RadixSelect.ScrollDownButton>
      </RadixSelect.Content>
    </RadixSelect.Portal>
  )
}

/** One row: optional swatch, label, description; yellow when highlighted, tick when chosen. */
export function SelectItem({ option }: { option: SelectOption }) {
  return (
    <RadixSelect.Item
      value={option.value}
      disabled={option.disabled}
      className={cn(
        'group relative flex cursor-pointer items-center gap-3 border-b border-ink-900/15 py-2.5 pr-10 pl-4 outline-none select-none last:border-b-0',
        'transition-colors duration-100 data-highlighted:bg-pop-yellow',
        'data-[state=checked]:font-bold',
        'data-disabled:cursor-not-allowed data-disabled:opacity-40',
      )}
    >
      {option.swatch ? (
        <span
          aria-hidden
          className="size-3.5 shrink-0 border-2 border-ink-900"
          style={{ background: option.swatch }}
        />
      ) : null}
      <span className="flex flex-col">
        <RadixSelect.ItemText>{option.label}</RadixSelect.ItemText>
        {option.description ? (
          <span className="text-xs font-normal text-sepia-800">{option.description}</span>
        ) : null}
      </span>
      <RadixSelect.ItemIndicator className="absolute right-3 flex size-5 items-center justify-center bg-ink-900 text-paper-50">
        <Check aria-hidden className="size-3.5" strokeWidth={3} />
      </RadixSelect.ItemIndicator>
    </RadixSelect.Item>
  )
}

/** Chevron that flips while the menu is open (trigger carries data-state). */
export function SelectChevron({ className }: { className?: string }) {
  return (
    <RadixSelect.Icon asChild>
      <ChevronDown
        aria-hidden
        className={cn(
          'size-5 shrink-0 transition-transform duration-200 ease-theatre group-data-[state=open]:rotate-180',
          className,
        )}
      />
    </RadixSelect.Icon>
  )
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
        className={cn(
          'text-xs font-bold tracking-[0.12em] text-fg uppercase',
          hideLabel && 'sr-only',
        )}
      >
        {label}
      </label>
      <RadixSelect.Root value={value} onValueChange={onValueChange} disabled={disabled}>
        <RadixSelect.Trigger
          aria-labelledby={`${id}-label`}
          aria-invalid={error ? true : undefined}
          className={cn(
            fieldClasses,
            'group inline-flex cursor-pointer items-center justify-between gap-2 text-left data-placeholder:text-muted',
            'data-[state=open]:bg-pop-yellow data-[state=open]:text-ink-900',
            size === 'sm' && 'h-9 px-2 text-sm',
          )}
        >
          <RadixSelect.Value placeholder={placeholder} />
          <SelectChevron />
        </RadixSelect.Trigger>
        <SelectMenu>
          {options.map((option) => (
            <SelectItem key={option.value} option={option} />
          ))}
        </SelectMenu>
      </RadixSelect.Root>
      {error ? <p className="text-sm font-medium text-danger">{error}</p> : null}
      {!error && hint ? <p className="text-sm text-muted">{hint}</p> : null}
    </div>
  )
}
