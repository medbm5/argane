import * as RadixSelect from '@radix-ui/react-select'
import { ArrowRight } from 'lucide-react'
import { useId, useMemo, useState, type SyntheticEvent } from 'react'
import { useNavigate } from 'react-router'
import {
  SelectChevron,
  SelectItem,
  SelectMenu,
  type SelectOption,
} from '../../../design-system/primitives/Select'
import { formatMonthYear } from '../../../lib/format'
import type { EventListItem } from '../../../lib/api/types'
import { CATEGORIES } from '../api'

/** Radix Select forbids empty values; this stands for "no filter". */
const ALL = 'all'

/** One cell of the bar: the whole cell is the trigger, yellow while open. */
function BarSelect({
  label,
  value,
  onValueChange,
  options,
}: {
  label: string
  value: string
  onValueChange: (value: string) => void
  options: SelectOption[]
}) {
  const id = useId()
  return (
    <div className="flex-1 border-b border-paper-50/15 sm:border-r sm:border-b-0">
      <RadixSelect.Root value={value} onValueChange={onValueChange}>
        <RadixSelect.Trigger
          aria-labelledby={`${id}-label ${id}-value`}
          className={[
            'group flex w-full cursor-pointer items-center justify-between gap-3 px-4 pt-2 pb-3 text-left outline-none',
            'transition-colors duration-150 hover:bg-paper-50/5 focus-visible:bg-paper-50/10',
            'data-[state=open]:bg-pop-yellow data-[state=open]:text-ink-900',
          ].join(' ')}
        >
          <span className="flex min-w-0 flex-col">
            <span
              id={`${id}-label`}
              className="text-[0.7rem] font-bold tracking-[0.16em] text-pop-yellow uppercase group-data-[state=open]:text-ink-900"
            >
              {label}
            </span>
            <span id={`${id}-value`} className="truncate text-base font-bold">
              <RadixSelect.Value />
            </span>
          </span>
          <SelectChevron className="text-pop-yellow group-data-[state=open]:text-ink-900" />
        </RadixSelect.Trigger>
        <SelectMenu title={label}>
          {options.map((option) => (
            <SelectItem key={option.value} option={option} />
          ))}
        </SelectMenu>
      </RadixSelect.Root>
    </div>
  )
}

interface Choice {
  slug: string
  month: string
  category: string
}

/** The shows that fit a choice, ignoring one of its fields (the one whose options we list). */
function matching(events: EventListItem[], choice: Choice, ignore: keyof Choice) {
  return events.filter(
    (e) =>
      (ignore === 'slug' || choice.slug === ALL || e.slug === choice.slug) &&
      (ignore === 'month' || choice.month === ALL || e.months.includes(choice.month)) &&
      (ignore === 'category' || choice.category === ALL || e.category === choice.category),
  )
}

const FITS: Record<keyof Choice, (e: EventListItem, value: string) => boolean> = {
  slug: (e, value) => e.slug === value,
  month: (e, value) => e.months.includes(value),
  category: (e, value) => e.category === value,
}

/**
 * Keeps `kept` (what the visitor just chose) and drops any other field that no show fits
 * any more, so the bar never holds a combination that leads nowhere.
 */
function reconcile(events: EventListItem[], choice: Choice, kept: keyof Choice): Choice {
  const next = { ...choice }
  for (const field of ['slug', 'month', 'category'] as const) {
    if (field === kept || next[field] === ALL) continue
    const value = next[field]
    if (!matching(events, next, field).some((e) => FITS[field](e, value))) next[field] = ALL
  }
  return next
}

/**
 * The ink-coloured search bar split into cells: Spectacle · Mois · Catégorie · action.
 * Each cell only offers what fits the others: a show narrows the months to its dates and
 * the category to its own; a category or a month narrows the shows. A chosen show goes
 * straight to its page; otherwise the programme opens with the filters.
 */
export function ProgrammeSearchBar({ events }: { events: EventListItem[] }) {
  const navigate = useNavigate()
  const [choice, setChoice] = useState<Choice>({ slug: ALL, month: ALL, category: ALL })
  const { slug, month, category } = choice

  function choose(field: keyof Choice, value: string) {
    const next = { ...choice, [field]: value }
    if (field === 'slug' && value !== ALL) {
      // A show has one category: pick it for the visitor.
      next.category = events.find((e) => e.slug === value)?.category ?? ALL
    }
    setChoice(reconcile(events, next, field))
  }

  const showOptions = useMemo<SelectOption[]>(
    () => [
      { value: ALL, label: 'Tous les spectacles' },
      ...matching(events, choice, 'slug').map((e) => ({
        value: e.slug,
        label: e.title,
        description: e.categoryLabel,
      })),
    ],
    [events, choice],
  )

  const monthOptions = useMemo<SelectOption[]>(() => {
    const months = new Set(matching(events, choice, 'month').flatMap((e) => e.months))
    return [
      { value: ALL, label: 'Toute la saison' },
      ...[...months].sort().map((value) => ({
        value,
        label: formatMonthYear(`${value}-15T12:00:00Z`),
      })),
    ]
  }, [events, choice])

  const categoryOptions = useMemo<SelectOption[]>(() => {
    const present = new Set(matching(events, choice, 'category').map((e) => e.category))
    return [
      { value: ALL, label: 'Toutes' },
      ...CATEGORIES.filter((c) => present.has(c.value)).map((c) => ({
        value: c.value,
        label: c.label,
        swatch: c.color,
      })),
    ]
  }, [events, choice])

  function submit(e: SyntheticEvent<HTMLFormElement>) {
    e.preventDefault()
    if (slug !== ALL) {
      void navigate(`/spectacles/${slug}`)
      return
    }
    const params = new URLSearchParams()
    if (month !== ALL) params.set('mois', month)
    if (category !== ALL) params.set('categorie', category)
    const qs = params.toString()
    void navigate(qs ? `/programme?${qs}` : '/programme')
  }

  return (
    <div className="px-4 pt-8 sm:px-8 sm:pt-10">
      <p aria-hidden className="mb-2 -rotate-1 font-marker text-lg text-accent">
        trouvez votre soirée
      </p>
      <form
        role="search"
        aria-label="Rechercher dans la programmation"
        onSubmit={submit}
        className="flex flex-col border-2 border-ink-900 bg-ink-900 text-paper-50 shadow-[6px_6px_0_var(--pop-yellow)] sm:flex-row"
      >
        <BarSelect
          label="Spectacle"
          value={slug}
          onValueChange={(v) => {
            choose('slug', v)
          }}
          options={showOptions}
        />
        <BarSelect
          label="Mois"
          value={month}
          onValueChange={(v) => {
            choose('month', v)
          }}
          options={monthOptions}
        />
        <BarSelect
          label="Catégorie"
          value={category}
          onValueChange={(v) => {
            choose('category', v)
          }}
          options={categoryOptions}
        />
        <button
          type="submit"
          className="group flex items-center justify-between gap-4 bg-pop-red px-6 py-5 text-left font-display tracking-[0.1em] text-ink-900 uppercase transition-colors duration-200 hover:bg-pop-yellow hover:text-ink-900 focus-visible:bg-pop-yellow focus-visible:text-ink-900 focus-visible:outline-none sm:w-72"
        >
          {slug === ALL ? 'Voir la programmation' : 'Voir le spectacle'}
          <ArrowRight
            aria-hidden
            className="size-5 transition-transform duration-200 ease-theatre group-hover:translate-x-1"
          />
        </button>
      </form>
    </div>
  )
}
