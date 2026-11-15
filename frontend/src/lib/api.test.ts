import { afterEach, describe, expect, it, vi } from 'vitest'
import { API_URL, ApiError, apiRequest } from './api'

function mockFetch(response: Response) {
  const spy = vi.fn<typeof fetch>().mockResolvedValue(response)
  vi.stubGlobal('fetch', spy)
  return spy
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('apiRequest', () => {
  it('attaches the bearer token and serializes the body', async () => {
    const spy = mockFetch(Response.json({ ok: true }))

    const result = await apiRequest<{ ok: boolean }>(
      '/holds',
      { method: 'POST', body: { seatIds: ['a'] } },
      () => Promise.resolve('jwt-123'),
    )

    expect(result).toEqual({ ok: true })
    const [url, init] = spy.mock.calls[0] ?? []
    expect(url).toBe(`${API_URL}/holds`)
    const headers = init?.headers as Record<string, string>
    expect(headers.Authorization).toBe('Bearer jwt-123')
    expect(headers['Content-Type']).toBe('application/json')
    expect(init?.body).toBe('{"seatIds":["a"]}')
  })

  it('omits the header when signed out and drops empty query values', async () => {
    const spy = mockFetch(Response.json([]))

    await apiRequest('/events', { query: { category: 'THEATRE', month: '' } }, () =>
      Promise.resolve(null),
    )

    const [url, init] = spy.mock.calls[0] ?? []
    expect(url).toBe(`${API_URL}/events?category=THEATRE`)
    expect((init?.headers as Record<string, string>).Authorization).toBeUndefined()
  })

  it('turns problem+json into an ApiError with its code', async () => {
    mockFetch(
      new Response(
        JSON.stringify({ title: 'Places indisponibles.', code: 'SEAT_UNAVAILABLE', seats: ['x'] }),
        { status: 409, headers: { 'Content-Type': 'application/problem+json' } },
      ),
    )

    const error = await apiRequest('/holds', { method: 'POST' }).catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).is('SEAT_UNAVAILABLE')).toBe(true)
    expect((error as ApiError).status).toBe(409)
    expect((error as ApiError).problem.seats).toEqual(['x'])
  })

  it('reports network failures with a stable code', async () => {
    vi.stubGlobal('fetch', vi.fn<typeof fetch>().mockRejectedValue(new TypeError('offline')))

    const error = await apiRequest('/health').catch((e: unknown) => e)

    expect((error as ApiError).code).toBe('NETWORK_ERROR')
  })
})
