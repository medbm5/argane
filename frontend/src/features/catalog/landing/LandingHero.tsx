import { mediaUrl } from '../../../lib/api'
import { ArrowRight } from 'lucide-react'
import { Link } from 'react-router'
import { Button } from '../../../design-system/primitives/Button'
import type { EventListItem } from '../../../lib/api/types'

const TILTS = ['-rotate-6', 'rotate-3', '-rotate-2']
const OFFSETS = ['left-0 top-6', 'right-0 top-0', 'left-1/2 -translate-x-1/2 bottom-0']

/**
 * The landing hero, "mural" direction: a sprayed kicker, a giant condensed
 * headline, a red rule, and a tilted stack of current posters pasted on the wall.
 */
export function LandingHero({ events = [] }: { events?: EventListItem[] }) {
  const posters = events.filter((e) => e.posterUrl).slice(0, 3)

  return (
    <section
      aria-labelledby="hero-title"
      className="relative grid gap-10 overflow-hidden border-b-2 border-ink-900 px-4 pt-14 pb-16 sm:px-8 md:grid-cols-[1.15fr_1fr] md:pt-20 md:pb-20"
    >
      <div>
        <p className="mb-4 -rotate-2 font-marker text-xl text-accent">Saison 26/27 · Agadir</p>
        <h1
          id="hero-title"
          className="font-display text-[clamp(3.5rem,10vw,var(--fs-hero))] leading-[0.9] text-fg"
        >
          <span className="block">Le rideau</span>{' '}
          <span className="mt-3 block">
            se{' '}
            <span className="inline-block bg-pop-yellow px-3 pt-2 leading-[0.82] text-ink-900">
              lève.
            </span>
          </span>
        </h1>
        <span aria-hidden className="mt-6 block h-2 w-28 bg-pop-red" />
        <p className="mt-6 max-w-xl text-lg">
          Quatre cents places, huit spectacles, une saison au bord de l’Atlantique.{' '}
          <strong>Choisissez votre soir, puis votre fauteuil.</strong>
        </p>
        <div className="mt-8 flex flex-wrap gap-4">
          <Button asChild variant="gilt" size="lg">
            <Link to="/programme">
              La programmation <ArrowRight aria-hidden className="size-5" />
            </Link>
          </Button>
          <Button asChild variant="secondary" size="lg">
            <Link to="/mes-billets">Mes billets</Link>
          </Button>
        </div>
      </div>

      <div aria-hidden className="relative mx-auto hidden h-[30rem] w-full max-w-md md:block">
        {posters.map((event, i) => (
          <div
            key={event.slug}
            className={`brutal absolute w-56 bg-paper-50 p-2 ${OFFSETS[i] ?? ''} ${TILTS[i] ?? ''}`}
          >
            <img
              src={mediaUrl(event.posterUrl) ?? ''}
              alt=""
              className="aspect-[3/4] w-full border-2 border-ink-900 object-cover"
            />
          </div>
        ))}
        <span className="absolute -right-2 bottom-10 rotate-6 font-marker text-2xl text-accent">
          à l’affiche ↓
        </span>
      </div>
    </section>
  )
}
