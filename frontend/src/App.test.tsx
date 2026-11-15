import { render, screen } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { describe, expect, it } from 'vitest'
import { App } from './App'
import { routes } from './app/routes'

function renderAt(path: string) {
  const router = createMemoryRouter(routes, { initialEntries: [path] })
  return render(<RouterProvider router={router} />)
}

describe('App', () => {
  it('renders the venue name inside the site shell', async () => {
    render(<App />)
    expect(await screen.findByRole('heading', { level: 1 })).toHaveTextContent('Théâtre Argane')
    expect(screen.getByRole('navigation', { name: 'Navigation principale' })).toBeInTheDocument()
    expect(screen.getByRole('contentinfo')).toBeInTheDocument()
  })

  it('serves the styleguide in development', async () => {
    renderAt('/styleguide')
    expect(await screen.findByRole('heading', { level: 1, name: 'Styleguide' })).toBeVisible()
  })

  it('shows a designed not found page', async () => {
    renderAt('/nulle-part')
    expect(
      await screen.findByRole('heading', { name: 'Cette page n’est pas à l’affiche.' }),
    ).toBeVisible()
  })
})
