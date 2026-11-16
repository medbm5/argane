import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { renderRoutes } from '../../test/renderWithProviders'
import { SIGNED_OUT } from '../auth/AuthContext'
import type { Me } from '../auth/useMe'
import { SiteHeader } from './SiteHeader'

function me(overrides: Partial<Me>): Me {
  return {
    id: 'u1',
    email: 'nadia@example.com',
    displayName: 'Nadia Benali',
    roles: ['ROLE_CUSTOMER'],
    isBoxOffice: false,
    isOrganizer: false,
    ...overrides,
  }
}

function signedInAs(user: Me) {
  vi.stubGlobal(
    'fetch',
    vi.fn<typeof fetch>().mockImplementation(() => Promise.resolve(Response.json(user))),
  )
}

afterEach(() => {
  vi.unstubAllGlobals()
})

const desktopNav = () => screen.getByRole('navigation', { name: 'Navigation principale' })

describe('SiteHeader', () => {
  it('puts the Studio in the header for the organizer', async () => {
    signedInAs(me({ isOrganizer: true, isBoxOffice: true }))
    renderRoutes([{ path: '/', element: <SiteHeader /> }])

    expect(await within(desktopNav()).findByRole('link', { name: 'Studio' })).toHaveAttribute(
      'href',
      '/studio',
    )
    expect(within(desktopNav()).getByRole('link', { name: 'Programmation' })).toBeInTheDocument()
  })

  it('puts the Guichet in the header for box-office staff, and no Studio', async () => {
    signedInAs(me({ isBoxOffice: true }))
    renderRoutes([{ path: '/', element: <SiteHeader /> }])

    expect(await within(desktopNav()).findByRole('link', { name: 'Guichet' })).toBeInTheDocument()
    expect(within(desktopNav()).queryByRole('link', { name: 'Studio' })).not.toBeInTheDocument()
  })

  it('closes the phone menu when Connexion is tapped', async () => {
    const user = userEvent.setup()
    const { router } = renderRoutes(
      [
        { path: '/', element: <SiteHeader /> },
        { path: '/connexion', element: <SiteHeader /> },
      ],
      { auth: SIGNED_OUT },
    )

    await user.click(screen.getByRole('button', { name: 'Ouvrir le menu' }))
    const drawer = await screen.findByRole('dialog', { name: 'Menu' })
    await user.click(within(drawer).getByRole('link', { name: 'Connexion' }))

    expect(router.state.location.pathname).toBe('/connexion')
    await waitFor(() => {
      expect(screen.queryByRole('dialog', { name: 'Menu' })).not.toBeInTheDocument()
    })
  })

  it('lists the account links flat in the phone menu and closes on any of them', async () => {
    signedInAs(me({ isOrganizer: true, isBoxOffice: true }))
    const user = userEvent.setup()
    const { router } = renderRoutes([
      { path: '/', element: <SiteHeader /> },
      { path: '/controle', element: <SiteHeader /> },
    ])

    await user.click(screen.getByRole('button', { name: 'Ouvrir le menu' }))
    const drawer = await screen.findByRole('dialog', { name: 'Menu' })
    expect(await within(drawer).findByText('Nadia Benali')).toBeInTheDocument()
    await user.click(within(drawer).getByRole('link', { name: 'Contrôle des billets' }))

    expect(router.state.location.pathname).toBe('/controle')
    await waitFor(() => {
      expect(screen.queryByRole('dialog', { name: 'Menu' })).not.toBeInTheDocument()
    })
  })
})
