import { useQuery } from '@tanstack/react-query'
import { useApi } from '../../lib/useApi'
import { useAuthState } from './AuthContext'

export type AppRole = 'ROLE_CUSTOMER' | 'ROLE_BOX_OFFICE' | 'ROLE_ORGANIZER'

export interface Me {
  id: string
  email: string
  displayName: string
  roles: AppRole[]
  isBoxOffice: boolean
  isOrganizer: boolean
}

export const meQueryKey = ['me'] as const

/** The signed-in user as known by our API (roles live in our database, not in Clerk). */
export function useMe() {
  const { isLoaded, isSignedIn } = useAuthState()
  const api = useApi()
  return useQuery({
    queryKey: meQueryKey,
    queryFn: ({ signal }) => api.get<Me>('/me', { signal }),
    enabled: isLoaded && isSignedIn,
    staleTime: 5 * 60_000,
  })
}
