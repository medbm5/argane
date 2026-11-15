import { SignIn, SignUp } from '@clerk/react'
import type { ReactNode } from 'react'
import { useSearchParams } from 'react-router'

const CLERK_ENABLED = Boolean(import.meta.env.VITE_CLERK_PUBLISHABLE_KEY)

function AuthFrame({
  title,
  intro,
  children,
}: {
  title: string
  intro: string
  children: ReactNode
}) {
  return (
    <div className="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_auto] lg:items-center lg:py-20">
      <div className="max-w-md">
        <p className="small-caps text-sm text-accent">Billetterie</p>
        <h1 className="mt-2 font-display text-5xl leading-none">{title}</h1>
        <p className="mt-4 text-muted">{intro}</p>
        <p className="mt-8 border-l-2 border-gilt-400 pl-4 font-display text-xl italic">
          Chaque place raconte une histoire.
        </p>
      </div>
      <div className="flex justify-center">
        {CLERK_ENABLED ? (
          children
        ) : (
          <p role="alert" className="max-w-sm rounded-md border border-line bg-surface p-6 text-sm">
            L’authentification n’est pas configurée (VITE_CLERK_PUBLISHABLE_KEY manquant).
          </p>
        )}
      </div>
    </div>
  )
}

/** Where to go after auth: a same-site `redirect` param, or the programme. */
function useRedirectTarget(): string {
  const [params] = useSearchParams()
  const target = params.get('redirect')
  return target?.startsWith('/') && !target.startsWith('//') ? target : '/programme'
}

export function SignInPage() {
  const redirect = useRedirectTarget()
  return (
    <AuthFrame
      title="Connexion"
      intro="Retrouvez vos billets, réservez vos places et suivez vos commandes."
    >
      <SignIn
        routing="path"
        path="/connexion"
        signUpUrl={`/inscription?redirect=${encodeURIComponent(redirect)}`}
        fallbackRedirectUrl={redirect}
      />
    </AuthFrame>
  )
}

export function SignUpPage() {
  const redirect = useRedirectTarget()
  return (
    <AuthFrame
      title="Créer un compte"
      intro="Un compte suffit pour réserver en ligne et garder vos billets à portée de main."
    >
      <SignUp
        routing="path"
        path="/inscription"
        signInUrl={`/connexion?redirect=${encodeURIComponent(redirect)}`}
        fallbackRedirectUrl={redirect}
      />
    </AuthFrame>
  )
}
