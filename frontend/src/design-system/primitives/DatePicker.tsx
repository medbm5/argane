import * as Popover from '@radix-ui/react-popover'
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react'
import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react'
import { cn } from '../cn'
import { fieldClasses } from './fieldStyles'
import type { FieldProps } from './Input'
import { Select } from './Select'

/*
 * Dates are plain "YYYY-MM-DD" strings from end to end (what the API and the old
 * <input type="date"> used), worked on with UTC arithmetic so no time zone can shift a day.
 */

const MONTH = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric', timeZone: 'UTC' })
const LONG = new Intl.DateTimeFormat('fr-FR', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  timeZone: 'UTC',
})
const SHORT = new Intl.DateTimeFormat('fr-FR', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  timeZone: 'UTC',
})
const WEEKDAYS = ['L', 'M', 'M', 'J', 'V', 'S', 'D']
const WEEKDAY_NAMES = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche']

const ISO = /^\d{4}-\d{2}-\d{2}$/

function toDate(iso: string): Date {
  const [y = 1970, m = 1, d = 1] = iso.split('-').map(Number)
  return new Date(Date.UTC(y, m - 1, d))
}
function toIso(date: Date): string {
  return date.toISOString().slice(0, 10)
}
function addDays(iso: string, days: number): string {
  const d = toDate(iso)
  d.setUTCDate(d.getUTCDate() + days)
  return toIso(d)
}
function addMonths(iso: string, months: number): string {
  const d = toDate(iso)
  const day = d.getUTCDate()
  d.setUTCDate(1)
  d.setUTCMonth(d.getUTCMonth() + months)
  const last = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + 1, 0)).getUTCDate()
  d.setUTCDate(Math.min(day, last))
  return toIso(d)
}
function todayIso(): string {
  const now = new Date()
  return toIso(new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate())))
}
/** Monday = 0 … Sunday = 6. */
function weekday(iso: string): number {
  return (toDate(iso).getUTCDay() + 6) % 7
}
/** The 6 weeks shown for the month of `iso`, Monday first. */
function monthGrid(iso: string): string[] {
  const first = `${iso.slice(0, 7)}-01`
  const start = addDays(first, -weekday(first))
  return Array.from({ length: 42 }, (_, i) => addDays(start, i))
}

function formatDateShort(iso: string): string {
  return ISO.test(iso) ? SHORT.format(toDate(iso)) : ''
}

/** The calendar itself: a month grid with roving focus, as in the WAI-ARIA date picker pattern. */
function Calendar({
  value,
  onPick,
  min,
  max,
}: {
  value: string
  onPick: (iso: string) => void
  min?: string
  max?: string
}) {
  const today = todayIso()
  const [focused, setFocused] = useState(ISO.test(value) ? value : today)
  const grid = monthGrid(focused)
  const gridRef = useRef<HTMLDivElement>(null)
  const titleId = useId()
  const outOfRange = (iso: string) =>
    (min !== undefined && iso < min) || (max !== undefined && iso > max)

  useEffect(() => {
    gridRef.current?.querySelector<HTMLButtonElement>(`[data-day="${focused}"]`)?.focus()
  }, [focused])

  function onKeyDown(e: KeyboardEvent) {
    const moves: Record<string, () => string> = {
      ArrowLeft: () => addDays(focused, -1),
      ArrowRight: () => addDays(focused, 1),
      ArrowUp: () => addDays(focused, -7),
      ArrowDown: () => addDays(focused, 7),
      Home: () => addDays(focused, -weekday(focused)),
      End: () => addDays(focused, 6 - weekday(focused)),
      PageUp: () => addMonths(focused, e.shiftKey ? -12 : -1),
      PageDown: () => addMonths(focused, e.shiftKey ? 12 : 1),
    }
    const move = moves[e.key]
    if (move) {
      e.preventDefault()
      setFocused(move())
    }
  }

  return (
    <div className="w-[19rem] p-3">
      <div className="mb-2 flex items-center justify-between gap-2">
        <button
          type="button"
          aria-label="Mois précédent"
          className="grid size-9 place-items-center rounded-sm border-2 border-transparent hover:border-ink-900 focus-visible:border-ink-900 focus-visible:outline-none"
          onClick={() => {
            setFocused(addMonths(focused, -1))
          }}
        >
          <ChevronLeft aria-hidden className="size-5" />
        </button>
        <p
          id={titleId}
          aria-live="polite"
          className="font-display text-lg tracking-[0.06em] uppercase"
        >
          {MONTH.format(toDate(focused))}
        </p>
        <button
          type="button"
          aria-label="Mois suivant"
          className="grid size-9 place-items-center rounded-sm border-2 border-transparent hover:border-ink-900 focus-visible:border-ink-900 focus-visible:outline-none"
          onClick={() => {
            setFocused(addMonths(focused, 1))
          }}
        >
          <ChevronRight aria-hidden className="size-5" />
        </button>
      </div>
      <div ref={gridRef} role="grid" tabIndex={-1} aria-labelledby={titleId} onKeyDown={onKeyDown}>
        <div role="row" className="grid grid-cols-7">
          {WEEKDAYS.map((d, i) => (
            <span
              key={WEEKDAY_NAMES[i]}
              role="columnheader"
              aria-label={WEEKDAY_NAMES[i]}
              className="py-1 text-center text-xs font-bold text-muted"
            >
              {d}
            </span>
          ))}
        </div>
        {Array.from({ length: 6 }, (_, week) => (
          <div role="row" key={week} className="grid grid-cols-7">
            {grid.slice(week * 7, week * 7 + 7).map((iso) => {
              const inMonth = iso.slice(0, 7) === focused.slice(0, 7)
              const selected = iso === value
              const disabled = outOfRange(iso)
              return (
                <span role="gridcell" key={iso} aria-selected={selected}>
                  <button
                    type="button"
                    data-day={iso}
                    tabIndex={iso === focused ? 0 : -1}
                    disabled={disabled}
                    aria-label={LONG.format(toDate(iso))}
                    aria-current={iso === today ? 'date' : undefined}
                    onClick={() => {
                      onPick(iso)
                    }}
                    className={cn(
                      'm-0.5 grid h-9 w-[calc(100%-0.25rem)] place-items-center rounded-sm border-2 text-sm tabular-nums transition-colors duration-150',
                      'focus-visible:shadow-[3px_3px_0_var(--pop-blue)] focus-visible:outline-none',
                      selected
                        ? 'border-ink-900 bg-ink-900 font-bold text-pop-yellow'
                        : iso === today
                          ? 'border-ink-900 font-bold'
                          : 'border-transparent hover:border-ink-900',
                      !inMonth && !selected && 'text-muted/60',
                      disabled &&
                        'cursor-not-allowed line-through opacity-40 hover:border-transparent',
                    )}
                  >
                    {Number(iso.slice(8))}
                  </button>
                </span>
              )
            })}
          </div>
        ))}
      </div>
    </div>
  )
}

export interface DatePickerProps extends FieldProps {
  /** "YYYY-MM-DD", or "" when empty. */
  value: string
  onChange: (value: string) => void
  min?: string
  max?: string
  /** Offers an "Effacer" action that sets the value to "". */
  clearable?: boolean
  placeholder?: string
  className?: string
}

/** A date field in the house style: the trigger looks like an input, the calendar like our menus. */
export function DatePicker({
  label,
  hint,
  error,
  hideLabel,
  value,
  onChange,
  min,
  max,
  clearable,
  placeholder = 'Choisir une date',
  className,
}: DatePickerProps) {
  const [open, setOpen] = useState(false)
  const id = useId()
  const describedBy = error ? `${id}-error` : hint ? `${id}-hint` : undefined

  return (
    <div className={cn('flex flex-col gap-1.5', className)}>
      <span
        id={`${id}-label`}
        className={cn('text-sm font-medium text-fg', hideLabel && 'sr-only')}
      >
        {label}
      </span>
      <Popover.Root open={open} onOpenChange={setOpen}>
        <Popover.Trigger
          type="button"
          aria-labelledby={`${id}-label ${id}-value`}
          aria-describedby={describedBy}
          aria-invalid={error ? true : undefined}
          className={cn(fieldClasses, 'flex items-center justify-between gap-2 text-left')}
        >
          <span id={`${id}-value`} className={cn('truncate', !value && 'text-muted/70')}>
            {value ? formatDateShort(value) : placeholder}
          </span>
          <CalendarDays aria-hidden className="size-4 shrink-0" />
        </Popover.Trigger>
        <Popover.Portal>
          <Popover.Content
            align="start"
            sideOffset={8}
            aria-label={label}
            className={cn(
              'z-50 border-2 border-ink-900 bg-paper-50 text-ink-900 shadow-[6px_6px_0_var(--ink-900)]',
              'origin-(--radix-popover-content-transform-origin) animate-[rise-in_160ms_var(--ease-out)]',
            )}
            onOpenAutoFocus={(e) => {
              e.preventDefault() // the calendar focuses its own day
            }}
          >
            <p className="bg-ink-900 px-4 py-1.5 font-display text-xs tracking-[0.2em] text-pop-yellow uppercase">
              {label}
            </p>
            <Calendar
              value={value}
              min={min}
              max={max}
              onPick={(iso) => {
                onChange(iso)
                setOpen(false)
              }}
            />
            <div className="flex justify-between gap-2 border-t-2 border-ink-900 px-3 py-2 text-sm font-bold">
              <button
                type="button"
                className="underline-offset-4 hover:underline focus-visible:underline focus-visible:outline-none"
                onClick={() => {
                  onChange(todayIso())
                  setOpen(false)
                }}
              >
                Aujourd’hui
              </button>
              {clearable && value ? (
                <button
                  type="button"
                  className="text-accent underline-offset-4 hover:underline focus-visible:underline focus-visible:outline-none"
                  onClick={() => {
                    onChange('')
                    setOpen(false)
                  }}
                >
                  Effacer
                </button>
              ) : null}
            </div>
          </Popover.Content>
        </Popover.Portal>
      </Popover.Root>
      {error ? (
        <p id={`${id}-error`} className="text-sm text-danger">
          {error}
        </p>
      ) : hint ? (
        <p id={`${id}-hint`} className="text-sm text-muted">
          {hint}
        </p>
      ) : null}
    </div>
  )
}

const HOURS = Array.from({ length: 24 }, (_, h) => String(h).padStart(2, '0'))
const QUARTERS = ['00', '15', '30', '45']

export interface DateTimePickerProps extends Omit<
  DatePickerProps,
  'value' | 'onChange' | 'min' | 'max'
> {
  /** "YYYY-MM-DDTHH:mm" (venue local, as the old datetime-local input), or "". */
  value: string
  onChange: (value: string) => void
  /** Time used when a date is picked before any time. */
  defaultTime?: string
}

/** A date in the calendar plus hour and minute menus; minutes go by the quarter hour. */
export function DateTimePicker({
  value,
  onChange,
  defaultTime = '20:00',
  label,
  hint,
  error,
  clearable,
  className,
}: DateTimePickerProps) {
  const date = value.slice(0, 10)
  const time = value.length >= 16 ? value.slice(11, 16) : ''
  const [hour = '', minute = ''] = time ? time.split(':') : []
  const minutes = minute && !QUARTERS.includes(minute) ? [...QUARTERS, minute].sort() : QUARTERS
  const set = (d: string, h: string, m: string) => {
    onChange(d ? `${d}T${h}:${m}` : '')
  }
  const [dh = '20', dm = '00'] = defaultTime.split(':')

  return (
    <fieldset className={cn('flex min-w-0 flex-col gap-1.5', className)}>
      <legend className="mb-1.5 text-sm font-medium text-fg">{label}</legend>
      <div className="grid grid-cols-[minmax(0,1fr)_5rem_5rem] items-start gap-2">
        <DatePicker
          label={`${label} — date`}
          hideLabel
          value={date}
          clearable={clearable}
          error={error}
          onChange={(d) => {
            set(d, hour || dh, minute || dm)
          }}
        />
        <Select
          label={`${label} — heure`}
          hideLabel
          placeholder="hh"
          value={hour || undefined}
          disabled={!date}
          options={HOURS.map((h) => ({ value: h, label: `${h} h` }))}
          onValueChange={(h) => {
            set(date, h, minute || dm)
          }}
        />
        <Select
          label={`${label} — minutes`}
          hideLabel
          placeholder="mm"
          value={minute || undefined}
          disabled={!date}
          options={minutes.map((m) => ({ value: m, label: m }))}
          onValueChange={(m) => {
            set(date, hour || dh, m)
          }}
        />
      </div>
      {hint && !error ? <p className="text-sm text-muted">{hint}</p> : null}
    </fieldset>
  )
}
