import { Link, isRouteErrorResponse, useRouteError } from 'react-router'
import { StatePanel } from '../design-system/components/StatePanel'
import { Button } from '../design-system/primitives/Button'

export function NotFoundPage() {
  return (
    <div className="px-4 py-16">
      <StatePanel
        kind="notFound"
        kicker="entracte"
        title="Cette page n’est pas à l’affiche."
        headingLevel={1}
        actions={
          <Button asChild variant="gilt">
            <Link to="/programme">Voir la programmation</Link>
          </Button>
        }
      >
        Le lien est peut-être ancien. La programmation, elle, est bien à jour.
      </StatePanel>
    </div>
  )
}

export function RouteErrorPage() {
  const error = useRouteError()
  if (isRouteErrorResponse(error) && error.status === 404) return <NotFoundPage />
  return (
    <div className="paper-grain wall min-h-dvh bg-bg px-4 py-16 text-fg">
      <StatePanel
        kind="error"
        kicker="incident technique"
        title="La représentation est interrompue."
        headingLevel={1}
        actions={
          <>
            <Button
              variant="gilt"
              onClick={() => {
                window.location.reload()
              }}
            >
              Recharger la page
            </Button>
            <Button asChild variant="secondary">
              <a href="/">Retour à l’accueil</a>
            </Button>
          </>
        }
      >
        Une erreur inattendue s’est produite. Rechargez la page ; si le problème persiste, réessayez
        un peu plus tard.
      </StatePanel>
    </div>
  )
}
