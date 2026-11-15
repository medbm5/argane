import { LoaderCircle } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link, Navigate, Outlet, useLocation } from 'react-router'
import { Button } from '../../design-system/primitives/Button'
import { useAuthState } from './AuthContext'
import { useMe, type AppRole } from './useMe'

function Waiting() {
  return (
    <div role="status" className="grid min-h-[40vh] place-items-center text-muted">
      <span className="flex items-center gap-2">
        <LoaderCircle aria-hidden className="size-5 animate-spin" /> Vérification de l’accès…
      </span>
    </div>
  )
}

function Forbidden() {
  return (
    <section className="mx-auto max-w-xl px-6 py-24 text-center">
      <p className="small-caps text-sm text-accent">Accès réservé</p>
      <h1 className="mt-2 font-display text-4xl">Cet espace est réservé à l’équipe du théâtre.</h1>
      <p className="mt-4 text-muted">
        Si vous devriez y avoir accès, demandez à l’organisateur de vous attribuer le rôle.
      </p>
      <Button asChild variant="secondary" className="mt-8">
        <Link to="/">Retour à l’accueil</Link>
      </Button>
    </section>
  )
}

/**
 * Route guard. Signed-out visitors go to sign-in and come back afterwards;
 * signed-in users without the role see a designed "reserved" page.
 * The API enforces the same rules: this only shapes the UI.
 */
export function RequireRole({ requires, children }: { requires: AppRole; children?: ReactNode }) {
  const { isLoaded, isSignedIn } = useAuthState()
  const location = useLocation()
  const me = useMe()

  if (!isLoaded) return <Waiting />
  if (!isSignedIn) {
    const redirect = encodeURIComponent(location.pathname + location.search)
    return <Navigate to={`/connexion?redirect=${redirect}`} replace />
  }
  if (me.isPending) return <Waiting />
  if (me.isError || !me.data.roles.includes(requires)) return <Forbidden />

  return children ?? <Outlet />
}
