import { Link, isRouteErrorResponse, useRouteError } from 'react-router'
import { Button } from '../design-system/primitives/Button'

export function NotFoundPage() {
  return (
    <section className="mx-auto max-w-2xl px-6 py-24 text-center">
      <p className="small-caps text-sm text-accent">Entracte</p>
      <h1 className="mt-2 font-display text-5xl">Cette page n’est pas à l’affiche.</h1>
      <p className="mt-4 text-muted">
        Le lien est peut-être ancien. La programmation, elle, est bien à jour.
      </p>
      <Button asChild className="mt-8">
        <Link to="/programme">Voir la programmation</Link>
      </Button>
    </section>
  )
}

export function RouteErrorPage() {
  const error = useRouteError()
  if (isRouteErrorResponse(error) && error.status === 404) return <NotFoundPage />
  return (
    <section className="mx-auto max-w-2xl px-6 py-24 text-center">
      <p className="small-caps text-sm text-danger">Incident technique</p>
      <h1 className="mt-2 font-display text-5xl">La représentation est interrompue.</h1>
      <p className="mt-4 text-muted">
        Rechargez la page. Si le problème persiste, réessayez plus tard.
      </p>
      <Button
        className="mt-8"
        onClick={() => {
          window.location.reload()
        }}
      >
        Recharger la page
      </Button>
    </section>
  )
}
