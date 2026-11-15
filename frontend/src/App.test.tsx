import { render, screen } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { App } from './App'
import { routes } from './app/routes'

function renderAt(path: string) {
  const router = createMemoryRouter(routes, { initialEntries: [path] })
  return render(<RouterProvider router={router} />)
}

// The API answers healthy with an empty catalogue.
beforeEach(() => {
  vi.stubGlobal(
    'fetch',
    vi.fn<typeof fetch>(() => Promise.resolve(Response.json([]))),
  )
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('App', () => {
  it('renders the landing inside the site shell', async () => {
    render(<App />)
    expect(await screen.findByRole('heading', { level: 1 })).toHaveTextContent('Le rideau se lève.')
    expect(screen.getByRole('navigation', { name: 'Navigation principale' })).toBeInTheDocument()
    expect(screen.getByRole('contentinfo')).toBeInTheDocument()
  })

  it('serves the styleguide in development', async () => {
    renderAt('/styleguide')
    expect(await screen.findByRole('heading', { level: 1, name: 'Styleguide' })).toBeVisible()
  })

  it('renders the sign-in page shell', async () => {
    renderAt('/connexion')
    expect(await screen.findByRole('heading', { level: 1, name: 'Connexion' })).toBeVisible()
  })

  it('shows a designed not found page', async () => {
    renderAt('/nulle-part')
    expect(
      await screen.findByRole('heading', { name: 'Cette page n’est pas à l’affiche.' }),
    ).toBeVisible()
  })
})
