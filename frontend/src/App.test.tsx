import { render, screen } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { describe, expect, it } from 'vitest'
import { App } from './App'
import { routes } from './app/routes'

describe('App', () => {
  it('renders the venue name', async () => {
    render(<App />)
    expect(await screen.findByRole('heading', { level: 1 })).toHaveTextContent('Théâtre Argane')
  })

  it('serves the styleguide in development', async () => {
    const router = createMemoryRouter(routes, { initialEntries: ['/styleguide'] })
    render(<RouterProvider router={router} />)
    expect(await screen.findByRole('heading', { level: 1, name: 'Styleguide' })).toBeVisible()
  })
})
