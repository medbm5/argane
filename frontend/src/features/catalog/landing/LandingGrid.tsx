import { ArrowRight } from 'lucide-react'
import { Link } from 'react-router'
import { WaveMask } from '../../../design-system/components/WaveMask'
import { formatLongDate } from '../../../lib/format'
import type { EventListItem } from '../../../lib/api/types'
import { CATEGORIES } from '../api'

const PARTNERS = ['Atlas Imprimerie', 'Souss Radio', 'Maison Tifaout', 'Café Bab Al Bahr']

/** Bottom grid row: categories · poster in a wave mask · news · follow us. */
export function LandingGrid({ events }: { events: EventListItem[] }) {
  const featured =
    [...events]
      .filter((e) => e.nextPerformance?.isOnSale && e.posterUrl)
      .sort((a, b) => {
        const fill = (e: EventListItem) =>
          e.nextPerformance && e.nextPerformance.capacity > 0
            ? e.nextPerformance.remaining / e.nextPerformance.capacity
            : 1
        return fill(a) - fill(b)
      })[0] ?? null

  return (
    <>
      <section
        aria-label="Découvrir"
        className="grid border-b border-line sm:grid-cols-2 lg:grid-cols-[1fr_1.2fr_1fr_0.8fr]"
      >
        <nav
          aria-labelledby="categories-title"
          className="border-b border-line p-6 sm:border-r lg:border-b-0 lg:p-8"
        >
          <h2 id="categories-title" className="small-caps mb-4 text-sm text-accent">
            Catégories
          </h2>
          <ul className="space-y-1">
            {CATEGORIES.map((c) => (
              <li key={c.value}>
                <Link
                  to={`/programme?categorie=${c.value}`}
                  className="group flex items-center justify-between font-display text-2xl leading-snug hover:text-accent"
                >
                  {c.label}
                  <ArrowRight
                    aria-hidden
                    className="size-4 opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                  />
                </Link>
              </li>
            ))}
          </ul>
        </nav>

        <div className="flex items-center justify-center border-b border-line p-6 lg:border-r lg:border-b-0">
          {featured ? (
            <Link
              to={`/spectacles/${featured.slug}`}
              className="group block w-full max-w-72"
              aria-label={`À l’affiche : ${featured.title}`}
            >
              <WaveMask className="aspect-[3/4] w-full">
                <img
                  src={featured.posterUrl ?? ''}
                  alt=""
                  className="size-full object-cover transition-transform duration-500 ease-theatre group-hover:scale-105"
                />
              </WaveMask>
              <p className="mt-3 text-center text-sm text-muted">
                À l’affiche · <span className="font-display text-lg text-fg">{featured.title}</span>
              </p>
            </Link>
          ) : (
            <div className="aspect-[3/4] w-full max-w-72 animate-pulse rounded-md bg-surface-alt" />
          )}
        </div>

        <article
          aria-labelledby="news-title"
          className="border-b border-line p-6 sm:border-r sm:border-b-0 lg:p-8"
        >
          <h2 id="news-title" className="small-caps mb-4 text-sm text-accent">
            Nouvelles
          </h2>
          <p className="font-display text-2xl leading-snug">
            Nouvelle configuration concert : la fosse ouvre en janvier.
          </p>
          <p className="mt-3 text-sm text-muted">
            Trois cents places debout devant la scène, la corbeille et les loges restent assises.
            Premier essai avec <em>Tamra — nuits gnaoua</em>.
          </p>
          {featured?.nextPerformance ? (
            <p className="mt-4 text-xs text-muted">
              Prochaine date à l’affiche : {formatLongDate(featured.nextPerformance.startsAt)}
            </p>
          ) : null}
        </article>

        <aside aria-labelledby="follow-title" className="p-6 lg:p-8">
          <h2 id="follow-title" className="small-caps mb-4 text-sm text-accent">
            Suivre la saison
          </h2>
          <ul className="space-y-2 text-sm">
            <li>@theatre.argane</li>
            <li>Lettre de saison, chaque mois</li>
            <li>Boulevard du 20 Août, Agadir</li>
          </ul>
          <p className="mt-6 text-xs text-muted">Lieu et comptes fictifs, pour la démonstration.</p>
        </aside>
      </section>

      <section aria-labelledby="partners-title" className="px-4 py-8 sm:px-8">
        <h2 id="partners-title" className="small-caps mb-5 text-center text-xs text-muted">
          Avec le soutien de
        </h2>
        <ul className="flex flex-wrap items-center justify-center gap-x-10 gap-y-4">
          {PARTNERS.map((name, i) => (
            <li
              key={name}
              className={
                i % 2 === 0
                  ? 'font-display text-2xl text-sepia-600 italic'
                  : 'small-caps text-sm tracking-[0.2em] text-sepia-600'
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
