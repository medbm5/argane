import { ArrowRight } from 'lucide-react'
import { useId, useMemo, useState, type ReactNode, type SyntheticEvent } from 'react'
import { useNavigate } from 'react-router'
import { formatMonthYear, venueMonth } from '../../../lib/format'
import type { EventCategory, EventListItem } from '../../../lib/api/types'
import { CATEGORIES } from '../api'

const cellSelect =
  'w-full appearance-none bg-transparent pt-6 pb-3 pr-8 text-base text-paper-50 outline-none focus-visible:outline-none [&>option]:text-ink-900'

function Cell({ label, children }: { label: string; children: (id: string) => ReactNode }) {
  const id = useId()
  return (
    <div className="relative flex-1 border-b border-paper-50/15 px-4 focus-within:bg-paper-50/5 sm:border-r sm:border-b-0">
      <label htmlFor={id} className="small-caps absolute top-2 left-4 text-xs text-sepia-400">
        {label}
      </label>
      {children(id)}
      <span aria-hidden className="pointer-events-none absolute top-1/2 right-4 text-sepia-400">
        ▾
      </span>
    </div>
  )
}

/**
 * The ink-coloured search bar split into cells: Spectacle · Mois · Catégorie · action.
 * Choosing a show goes straight to its page; otherwise filters the programme.
 */
export function ProgrammeSearchBar({ events }: { events: EventListItem[] }) {
  const navigate = useNavigate()
  const [slug, setSlug] = useState('')
  const [month, setMonth] = useState('')
  const [category, setCategory] = useState<EventCategory | ''>('')

  const months = useMemo(() => {
    const seen = new Map<string, string>()
    for (const event of events) {
      const startsAt = event.nextPerformance?.startsAt
      if (startsAt) seen.set(venueMonth(startsAt), formatMonthYear(startsAt))
    }
    return [...seen.entries()].sort(([a], [b]) => a.localeCompare(b))
  }, [events])

  function submit(e: SyntheticEvent<HTMLFormElement>) {
    e.preventDefault()
    if (slug) {
      void navigate(`/spectacles/${slug}`)
      return
    }
    const params = new URLSearchParams()
    if (month) params.set('mois', month)
    if (category) params.set('categorie', category)
    const qs = params.toString()
    void navigate(qs ? `/programme?${qs}` : '/programme')
  }

  return (
    <form
      role="search"
      aria-label="Rechercher dans la programmation"
      onSubmit={submit}
      className="flex flex-col border-b border-line bg-ink-900 text-paper-50 sm:flex-row"
    >
      <Cell label="Spectacle">
        {(id) => (
          <select
            id={id}
            value={slug}
            onChange={(e) => {
              setSlug(e.target.value)
            }}
            className={cellSelect}
          >
            <option value="">Tous les spectacles</option>
            {events.map((event) => (
              <option key={event.slug} value={event.slug}>
                {event.title}
              </option>
            ))}
          </select>
        )}
      </Cell>
      <Cell label="Mois">
        {(id) => (
          <select
            id={id}
            value={month}
            onChange={(e) => {
              setMonth(e.target.value)
            }}
            className={cellSelect}
          >
            <option value="">Toute la saison</option>
            {months.map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        )}
      </Cell>
      <Cell label="Catégorie">
        {(id) => (
          <select
            id={id}
            value={category}
            onChange={(e) => {
              setCategory(e.target.value as EventCategory | '')
            }}
            className={cellSelect}
          >
            <option value="">Toutes</option>
            {CATEGORIES.map((c) => (
              <option key={c.value} value={c.value}>
                {c.label}
              </option>
            ))}
          </select>
        )}
      </Cell>
      <button
        type="submit"
        className="group flex items-center justify-between gap-4 px-6 py-5 text-left font-medium transition-colors duration-200 hover:bg-gilt-400 hover:text-ink-900 focus-visible:bg-gilt-400 focus-visible:text-ink-900 focus-visible:outline-none sm:w-72"
      >
        Voir la programmation
        <ArrowRight
          aria-hidden
          className="size-5 transition-transform duration-200 ease-theatre group-hover:translate-x-1"
        />
      </button>
    </form>
  )
}
