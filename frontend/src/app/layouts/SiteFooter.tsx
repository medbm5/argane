import { Link } from 'react-router'

export function SiteFooter() {
  return (
    <footer className="mt-auto border-t border-line">
      <div className="mx-auto grid max-w-7xl gap-8 px-4 py-10 text-sm sm:grid-cols-3 sm:px-6">
        <div>
          <p className="font-display text-xl">Théâtre Argane</p>
          <p className="mt-1 text-muted italic">Chaque place raconte une histoire.</p>
        </div>
        <address className="text-muted not-italic">
          Boulevard du 20 Août
          <br />
          Agadir, Maroc
        </address>
        <nav aria-label="Liens de pied de page" className="flex flex-col gap-1 sm:items-end">
          <Link to="/programme" className="hover:text-accent">
            Programmation
          </Link>
          <Link to="/mes-billets" className="hover:text-accent">
            Mes billets
          </Link>
          <p className="small-caps mt-2 text-xs text-muted">Saison 26/27 · lieu fictif</p>
        </nav>
      </div>
    </footer>
  )
}
