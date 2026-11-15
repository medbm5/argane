import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import { createMemoryRouter, Outlet, RouterProvider, type RouteObject } from 'react-router'
import { AuthContext, type AuthState } from '../app/auth/AuthContext'

export const signedIn: AuthState = {
  isLoaded: true,
  isSignedIn: true,
  getToken: () => Promise.resolve('test-token'),
  signOut: () => Promise.resolve(),
}

/**
 * Renders routes with a fresh QueryClient and a fake auth state
 * (the real RootProviders would mount Clerk).
 */
export function renderRoutes(
  routes: RouteObject[],
  { path = '/', auth = signedIn }: { path?: string; auth?: AuthState } = {},
) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const router = createMemoryRouter(
    [
      {
        element: (
          <QueryClientProvider client={queryClient}>
            <AuthContext value={auth}>
              <Outlet />
            </AuthContext>
          </QueryClientProvider>
        ),
        children: routes,
      },
    ],
    { initialEntries: [path] },
  )
  return { router, queryClient, ...render(<RouterProvider router={router} />) }
}
