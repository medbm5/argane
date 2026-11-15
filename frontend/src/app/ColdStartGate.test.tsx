import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ColdStartGate } from './ColdStartGate'

afterEach(() => {
  vi.unstubAllGlobals()
})

const down = () => Promise.reject(new TypeError('Failed to fetch'))
const up = () => Promise.resolve(Response.json({ status: 'ok' }))

describe('ColdStartGate', () => {
  it('renders the app straight away when the API is up', async () => {
    vi.stubGlobal('fetch', vi.fn<typeof fetch>(up))
    render(<ColdStartGate>app</ColdStartGate>)
    expect(await screen.findByText('app')).toBeInTheDocument()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('shows the loader and retries until the API wakes up', async () => {
    const fetchMock = vi
      .fn<typeof fetch>()
      .mockImplementationOnce(down)
      .mockImplementationOnce(down)
      .mockImplementation(up)
    vi.stubGlobal('fetch', fetchMock)
    render(<ColdStartGate delays={[5, 5, 5]}>app</ColdStartGate>)

    expect(await screen.findByText('Le rideau se lève…')).toBeInTheDocument()
    expect(await screen.findByText('app')).toBeInTheDocument()
    expect(fetchMock).toHaveBeenCalledTimes(3)
  })

  it('offers a retry after giving up', async () => {
    const user = userEvent.setup()
    const fetchMock = vi.fn<typeof fetch>(down)
    vi.stubGlobal('fetch', fetchMock)
    render(<ColdStartGate delays={[5]}>app</ColdStartGate>)

    expect(await screen.findByText('Entracte prolongé')).toBeInTheDocument()
    fetchMock.mockImplementation(up)
    await user.click(screen.getByRole('button', { name: 'Réessayer' }))
    expect(await screen.findByText('app')).toBeInTheDocument()
  })
})
