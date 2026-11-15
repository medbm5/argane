import { Link } from 'react-router'
import { RotatingBadge } from '../../../design-system/components/RotatingBadge'

/**
 * The landing hero: a very large display headline on two lines,
 * the second indented after a long rule, and the rotating season badge.
 */
export function LandingHero() {
  return (
    <section
      aria-labelledby="hero-title"
      className="relative grid border-b border-line md:grid-cols-[1fr_auto]"
    >
      <div className="px-4 pt-14 pb-12 sm:px-8 md:pt-20 md:pb-16">
        <p className="small-caps mb-6 text-sm text-accent">Saison 26/27 · Agadir</p>
        <h1
          id="hero-title"
          className="font-display text-[clamp(3rem,9vw,var(--fs-hero))] leading-[0.95] font-medium tracking-[-0.01em] text-fg"
        >
          <span className="block">Le rideau se lève.</span>
          <span className="mt-2 flex items-center gap-4 sm:gap-8">
            <span aria-hidden className="hidden h-px flex-[0_0_18%] bg-fg sm:block" />
            <span className="italic">La salle vous attend.</span>
          </span>
        </h1>
        <p className="mt-8 max-w-xl text-lg text-muted">
          Quatre cents places, huit spectacles, une saison au bord de l’Atlantique. Choisissez votre
          soir, puis votre fauteuil.
        </p>
      </div>
      <div className="flex items-center justify-center border-t border-line p-8 md:border-t-0 md:border-l md:px-14">
        <Link
          to="/programme"
          aria-label="Découvrir la saison 26/27"
          className="rounded-full text-accent transition-transform duration-200 ease-theatre hover:scale-105"
        >
          <RotatingBadge
            text="Saison 26/27 · Théâtre Argane · Saison 26/27 · Théâtre Argane · "
            size={168}
            center={
              <span className="font-display text-3xl leading-none text-fg">
                26
                <span className="text-accent">/</span>
                27
              </span>
            }
          />
        </Link>
      </div>
    </section>
  )
}
