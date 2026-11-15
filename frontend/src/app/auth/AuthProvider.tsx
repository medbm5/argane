import { frFR } from '@clerk/localizations'
import { ClerkProvider, useAuth, useClerk } from '@clerk/react'
import { useMemo, type ReactNode } from 'react'
import { useNavigate } from 'react-router'
import { AuthContext, SIGNED_OUT, type AuthState } from './AuthContext'
import { clerkAppearance } from './clerkAppearance'

const PUBLISHABLE_KEY = import.meta.env.VITE_CLERK_PUBLISHABLE_KEY

function ClerkBridge({ children }: { children: ReactNode }) {
  const { isLoaded, isSignedIn, getToken } = useAuth()
  const clerk = useClerk()

  const value = useMemo<AuthState>(
    () => ({
      isLoaded,
      isSignedIn: isSignedIn === true,
      getToken: () => getToken(),
      signOut: () => clerk.signOut(),
    }),
    [isLoaded, isSignedIn, getToken, clerk],
  )

  return <AuthContext value={value}>{children}</AuthContext>
}

/** Must render inside the router: Clerk navigates with React Router. */
export function AuthProvider({ children }: { children: ReactNode }) {
  const navigate = useNavigate()

  if (!PUBLISHABLE_KEY) {
    if (import.meta.env.DEV) {
      console.warn('VITE_CLERK_PUBLISHABLE_KEY is not set: running signed out.')
    }
    return <AuthContext value={SIGNED_OUT}>{children}</AuthContext>
  }

  return (
    <ClerkProvider
      publishableKey={PUBLISHABLE_KEY}
      appearance={clerkAppearance}
      localization={frFR}
      signInUrl="/connexion"
      signUpUrl="/inscription"
      afterSignOutUrl="/"
      routerPush={(to) => navigate(to)}
      routerReplace={(to) => navigate(to, { replace: true })}
    >
      <ClerkBridge>{children}</ClerkBridge>
    </ClerkProvider>
  )
}
