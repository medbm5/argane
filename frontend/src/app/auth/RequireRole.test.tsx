import { screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderRoutes } from '../../test/renderWithProviders'
import { SIGNED_OUT } from './AuthContext'
import { RequireRole } from './RequireRole'

const routes = [
  {
    path: '/studio',
    element: (
      <RequireRole requires="ROLE_ORGANIZER">
        <h1>Studio</h1>
      </RequireRole>
    ),
  },
  { path: '/connexion', element: <h1>Connexion</h1> },
]

function mockMe(roles: string[]) {
  vi.stubGlobal(
    'fetch',
    vi.fn<typeof fetch>().mockResolvedValue(
      Response.json({
        id: '1',
        email: 'x@argane.test',
        displayName: 'X',
        roles,
        isBoxOffice: roles.includes('ROLE_BOX_OFFICE'),
        isOrganizer: roles.includes('ROLE_ORGANIZER'),
      }),
    ),
  )
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('RequireRole', () => {
  it('sends signed-out visitors to sign-in with a redirect back', async () => {
    const { router } = renderRoutes(routes, { path: '/studio', auth: SIGNED_OUT })

    expect(await screen.findByRole('heading', { name: 'Connexion' })).toBeVisible()
    expect(router.state.location.search).toBe('?redirect=%2Fstudio')
  })

  it('lets users with the role through', async () => {
    mockMe(['ROLE_CUSTOMER', 'ROLE_BOX_OFFICE', 'ROLE_ORGANIZER'])
    renderRoutes(routes, { path: '/studio' })

    expect(await screen.findByRole('heading', { name: 'Studio' })).toBeVisible()
  })

  it('shows a reserved page to users without the role', async () => {
    mockMe(['ROLE_CUSTOMER'])
    renderRoutes(routes, { path: '/studio' })

    expect(
      await screen.findByRole('heading', { name: /réservé à l’équipe du théâtre/ }),
    ).toBeVisible()
    expect(screen.queryByRole('heading', { name: 'Studio' })).not.toBeInTheDocument()
  })
})
