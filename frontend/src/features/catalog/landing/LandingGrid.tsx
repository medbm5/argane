import { ArrowRight } from 'lucide-react'
import { Link } from 'react-router'
import { Button } from '../../../design-system/primitives/Button'
import { formatShortDate } from '../../../lib/format'
import type { EventListItem } from '../../../lib/api/types'
import { CATEGORIES } from '../api'

const PARTNERS = ['Atlas Imprimerie', 'Souss Radio', 'Maison Tifaout', 'Café Bab Al Bahr']

/** Strip colours cycle like paint pots; light text only where contrast needs it. */
const STRIPS = [
  'bg-pop-yellow text-ink-900',
  'bg-pop-blue text-paper-50',
  'bg-pop-pink text-ink-900',
  'bg-pop-orange text-ink-900',
  'bg-pop-teal text-ink-900',
  'bg-pop-red text-paper-50',
]

/** Categories whose colour needs light text for contrast. */
const LIGHT_TEXT = new Set(['THEATRE', 'DANCE'])

export function SectionTitle({
  id,
  kicker,
  children,
  bar = 'bg-pop-blue',
}: {
  id: string
  kicker: string
  children: string
  bar?: string
}) {
  return (
    <div>
      <p className="-rotate-1 font-marker text-lg text-accent">{kicker}</p>
      <h2 id={id} className="font-display text-[clamp(2.5rem,6vw,4.5rem)]">
        {children}
      </h2>
      <span aria-hidden className={`mt-3 block h-1.5 w-24 ${bar}`} />
    </div>
  )
}

/** A pasted poster with a looping title strip underneath. */
function PosterCard({ event, strip }: { event: EventListItem; strip: string }) {
  const label = `${event.title} ★ `
  return (
    <li className="min-w-0">
      <Link
        to={`/spectacles/${event.slug}`}
        aria-label={`À l’affiche : ${event.title}`}
        className="group block focus-visible:outline-offset-4"
      >
        <div className="brutal overflow-hidden bg-paper-50">
          <img
            src={event.posterUrl ?? ''}
            alt=""
            className="aspect-[4/5] w-full object-cover transition-transform duration-500 ease-theatre group-hover:scale-105"
          />
          <div className={`overflow-hidden border-t-2 border-ink-900 py-1.5 ${strip}`}>
            <div
              aria-hidden
              className="marquee-track font-display text-base tracking-wide uppercase"
            >
              {Array.from({ length: 8 }, (_, i) => (
                <span key={i} className="px-2 whitespace-nowrap">
                  {label}
                </span>
              ))}
            </div>
          </div>
        </div>
        <p className="mt-3 flex items-center justify-between text-sm">
          <span className="font-bold tracking-wide uppercase">{event.categoryLabel}</span>
          {event.nextPerformance ? (
            <span className="tabular">{formatShortDate(event.nextPerformance.startsAt)}</span>
          ) : null}
        </p>
      </Link>
    </li>
  )
}

/** À l'affiche poster wall · categories as paint blocks · news · partners. */
export function LandingGrid({ events }: { events: EventListItem[] }) {
  const onWall = events.filter((e) => e.posterUrl).slice(0, 6)

  return (
    <>
      <section
        aria-labelledby="wall-title"
        className="border-b-2 border-ink-900 px-4 py-14 sm:px-8"
      >
        <SectionTitle id="wall-title" kicker="ce qui se joue">
          À l’affiche
        </SectionTitle>
        {onWall.length === 0 ? (
          <div className="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            {Array.from({ length: 3 }, (_, i) => (
              <div key={i} className="brutal aspect-[4/5] animate-pulse bg-surface-alt" />
            ))}
          </div>
        ) : (
          <ul className="mt-10 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            {onWall.map((event, i) => (
              <PosterCard key={event.slug} event={event} strip={STRIPS[i % STRIPS.length] ?? ''} />
            ))}
          </ul>
        )}
        <div className="mt-12 flex justify-center">
          <Button asChild variant="secondary" size="lg">
            <Link to="/programme">
              Toute la saison <ArrowRight aria-hidden className="size-5" />
            </Link>
          </Button>
        </div>
      </section>

      <section
        aria-label="Découvrir"
        className="grid border-b-2 border-ink-900 lg:grid-cols-[1.4fr_1fr]"
      >
        <nav
          aria-labelledby="categories-title"
          className="border-b-2 border-ink-900 px-4 py-12 sm:px-8 lg:border-r-2 lg:border-b-0"
        >
          <SectionTitle id="categories-title" kicker="choisir son genre" bar="bg-pop-pink">
            Catégories
          </SectionTitle>
          <ul className="mt-8 flex flex-wrap gap-4">
            {CATEGORIES.map((c) => (
              <li key={c.value}>
                <Link
                  to={`/programme?categorie=${c.value}`}
                  style={{ background: c.color }}
                  className={`brutal brutal-press inline-flex items-center gap-2 px-5 py-3 font-display text-2xl uppercase ${LIGHT_TEXT.has(c.value) ? 'text-paper-50' : 'text-ink-900'}`}
                >
                  {c.label}
                  <ArrowRight aria-hidden className="size-5" />
                </Link>
              </li>
            ))}
          </ul>
        </nav>

        <article
          aria-labelledby="news-title"
          className="bg-pop-yellow px-4 py-12 text-ink-900 sm:px-8"
        >
          <p className="-rotate-1 font-marker text-lg">quoi de neuf</p>
          <h2 id="news-title" className="font-display text-[clamp(2.5rem,6vw,4.5rem)]">
            Nouvelles
          </h2>
          <p className="mt-6 font-display text-3xl leading-tight">
            La fosse ouvre en janvier : trois cents places debout.
          </p>
          <p className="mt-4">
            La corbeille et les loges restent assises. Premier essai avec{' '}
            <strong>Tamra — nuits gnaoua</strong>.
          </p>
          <p className="mt-6 text-sm">
            @theatre.argane · Boulevard du 20 Août, Agadir
            <br />
            <span className="opacity-70">Lieu et comptes fictifs, pour la démonstration.</span>
          </p>
        </article>
      </section>

      <section aria-labelledby="partners-title" className="px-4 py-10 sm:px-8">
        <h2 id="partners-title" className="mb-6 text-center font-display text-xl tracking-[0.2em]">
          Avec le soutien de
        </h2>
        <ul className="flex flex-wrap items-center justify-center gap-x-12 gap-y-4">
          {PARTNERS.map((name, i) => (
            <li
              key={name}
              className={
                i % 2 === 0
                  ? 'font-marker text-2xl text-sepia-800'
                  : 'font-display text-xl tracking-[0.2em] text-sepia-800 uppercase'
              }
            >
              {name}
            </li>
          ))}
        </ul>
      </section>
    </>
  )
}
