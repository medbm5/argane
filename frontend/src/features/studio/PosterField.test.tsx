import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { API_URL, mediaUrl } from '../../lib/api'
import { renderRoutes } from '../../test/renderWithProviders'
import { PosterField } from './PosterField'

function Harness() {
  const [path, setPath] = useState<string | null>(null)
  return (
    <>
      <PosterField value={path} onChange={setPath} />
      <output>{path ?? 'none'}</output>
    </>
  )
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('mediaUrl', () => {
  it('loads uploaded posters from the API and site posters from the site', () => {
    expect(mediaUrl('/api/posters/abc')).toBe(`${API_URL}/posters/abc`)
    expect(mediaUrl('/posters/le-misanthrope.svg')).toBe('/posters/le-misanthrope.svg')
    expect(mediaUrl(null)).toBeUndefined()
  })
})

describe('PosterField', () => {
  it('uploads the chosen image and uses the returned path, with a preview', async () => {
    const fetchSpy = vi
      .fn<typeof fetch>()
      .mockResolvedValue(Response.json({ path: '/api/posters/p1' }, { status: 201 }))
    vi.stubGlobal('fetch', fetchSpy)
    const user = userEvent.setup()
    renderRoutes([{ path: '/', element: <Harness /> }])

    const file = new File(['png'], 'affiche.png', { type: 'image/png' })
    await user.upload(screen.getByLabelText('Fichier de l’affiche'), file)

    expect(await screen.findByText('/api/posters/p1')).toBeInTheDocument()
    expect(screen.getByRole('img', { name: 'Aperçu de l’affiche' })).toHaveAttribute(
      'src',
      `${API_URL}/posters/p1`,
    )
    const [url, init] = fetchSpy.mock.calls[0] ?? []
    expect(url).toBe(`${API_URL}/studio/posters`)
    expect(init?.body).toBeInstanceOf(FormData)
    expect((init?.headers as Record<string, string>)['Content-Type']).toBeUndefined()
    expect((init?.headers as Record<string, string>).Authorization).toBe('Bearer test-token')
  })

  it('refuses an image over 2 MB without sending it', async () => {
    const fetchSpy = vi.fn<typeof fetch>()
    vi.stubGlobal('fetch', fetchSpy)
    const user = userEvent.setup()
    renderRoutes([{ path: '/', element: <Harness /> }])

    const big = new File([new Uint8Array(2 * 1024 * 1024 + 1)], 'big.jpg', { type: 'image/jpeg' })
    await user.upload(screen.getByLabelText('Fichier de l’affiche'), big)

    expect(await screen.findByRole('alert')).toHaveTextContent('dépasse 2 Mo')
    expect(fetchSpy).not.toHaveBeenCalled()
    expect(screen.getByText('none')).toBeInTheDocument()
  })
})
