import { useMemo } from 'react'
import { useSearchParams } from 'react-router'
import { StatePanel } from '../design-system/components/StatePanel'
import { Button } from '../design-system/primitives/Button'
import { Pill } from '../design-system/primitives/Pill'
import { CATEGORIES, useEvents } from '../features/catalog/api'
import { EventCard } from '../features/catalog/components/EventCard'
import { SectionTitle } from '../features/catalog/landing/LandingGrid'
import type { EventCategory } from '../lib/api/types'
import { upcomingMonths } from '../lib/format'
import { usePageTitle } from '../lib/usePageTitle'

const SEASON_MONTHS = 8

/** /programme?categorie=THEATRE&mois=2026-12 — the filters live in the URL so they can be shared. */
export function ProgrammePage() {
  usePageTitle('Programmation')
  const [params, setParams] = useSearchParams()
  const rawCategory = params.get('categorie') ?? ''
  const category = CATEGORIES.some((c) => c.value === rawCategory)
    ? (rawCategory as EventCategory)
    : ''
  const month = /^\d{4}-\d{2}$/.test(params.get('mois') ?? '') ? (params.get('mois') ?? '') : ''

  const months = useMemo(() => upcomingMonths(new Date(), SEASON_MONTHS), [])
  const events = useEvents({ category, month })

  function setFilter(key: 'categorie' | 'mois', value: string) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    setParams(next, { replace: true })
  }

  const list = events.data ?? []

  return (
    <div className="mx-auto max-w-7xl px-4 py-14 sm:px-8">
      <SectionTitle id="programme-title" kicker="saison 26/27" bar="bg-pop-red">
        Programmation
      </SectionTitle>

      <div className="mt-10 space-y-4" role="group" aria-label="Filtres">
        <div className="flex flex-wrap items-center gap-3" role="group" aria-label="Catégorie">
          <Pill
            selected={category === ''}
            onClick={() => {
              setFilter('categorie', '')
            }}
          >
            Toutes
          </Pill>
          {CATEGORIES.map((c) => (
            <Pill
              key={c.value}
              selected={category === c.value}
              onClick={() => {
                setFilter('categorie', c.value)
              }}
            >
              <span
                aria-hidden
                className="size-3 border-2 border-ink-900"
                style={{ background: c.color }}
              />
              {c.label}
            </Pill>
          ))}
        </div>
        <div className="flex flex-wrap items-center gap-3" role="group" aria-label="Mois">
          <Pill
            selected={month === ''}
            onClick={() => {
              setFilter('mois', '')
            }}
          >
            Toute la saison
          </Pill>
          {months.map((m) => (
            <Pill
              key={m.value}
              selected={month === m.value}
              onClick={() => {
                setFilter('mois', m.value)
              }}
            >
              {m.label}
            </Pill>
          ))}
        </div>
      </div>

      <p className="mt-8 text-sm font-bold tracking-[0.12em] uppercase" aria-live="polite">
        {events.isPending ? 'Chargement…' : `${list.length} spectacle${list.length > 1 ? 's' : ''}`}
      </p>

      {events.isError ? (
        <StatePanel
          kind="error"
          kicker="petit incident"
          title="Le programme ne s’affiche pas"
          className="mt-6"
          actions={
            <Button variant="gilt" onClick={() => void events.refetch()}>
              Réessayer
            </Button>
          }
        >
          Le programme n’a pas pu être chargé. Réessayez dans un instant.
        </StatePanel>
      ) : events.isPending ? (
        <ul className="mt-6 space-y-12" aria-busy="true">
          {Array.from({ length: 3 }, (_, i) => (
            <li key={i} className="brutal h-64 animate-pulse bg-surface-alt" />
          ))}
        </ul>
      ) : list.length === 0 ? (
        <StatePanel kind="empty" kicker="relâche" title="Rien ce mois-ci" className="mt-6">
          Essayez un autre mois ou une autre catégorie.
        </StatePanel>
      ) : (
        <ul
          className={`mt-6 space-y-14 transition-opacity ${events.isPlaceholderData ? 'opacity-50' : ''}`}
        >
          {list.map((event) => (
            <EventCard key={event.id} event={event} />
          ))}
        </ul>
      )}
    </div>
  )
}
