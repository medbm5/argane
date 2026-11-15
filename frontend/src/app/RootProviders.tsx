import { QueryClientProvider } from '@tanstack/react-query'
import { useState } from 'react'
import { Outlet } from 'react-router'
import { AuthProvider } from './auth/AuthProvider'
import { createQueryClient } from './queryClient'

/** Providers that need the router (Clerk navigates with it), mounted as the root route. */
export function RootProviders() {
  const [queryClient] = useState(createQueryClient)
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <Outlet />
      </AuthProvider>
    </QueryClientProvider>
  )
}
