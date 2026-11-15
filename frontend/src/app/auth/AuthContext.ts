import { createContext, useContext } from 'react'

/**
 * The slice of auth state the app depends on. Backed by Clerk in the browser,
 * by a signed-out stub when no publishable key is configured, and by fakes in tests.
 */
export interface AuthState {
  isLoaded: boolean
  isSignedIn: boolean
  getToken: () => Promise<string | null>
  signOut: () => Promise<void>
}

export const SIGNED_OUT: AuthState = {
  isLoaded: true,
  isSignedIn: false,
  getToken: () => Promise.resolve(null),
  signOut: () => Promise.resolve(),
}

export const AuthContext = createContext<AuthState>(SIGNED_OUT)

export function useAuthState(): AuthState {
  return useContext(AuthContext)
}
