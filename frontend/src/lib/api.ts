/**
 * Thin fetch wrapper for the Symfony API.
 * Errors are RFC 7807 problem+json with a stable `code` the UI can switch on.
 */

export const API_URL = (import.meta.env.VITE_API_URL ?? 'http://localhost:8080/api').replace(
  /\/$/,
  '',
)

export interface Problem {
  type?: string
  title: string
  status: number
  code: string
  detail?: string
  [extra: string]: unknown
}

export class ApiError extends Error {
  readonly status: number
  readonly code: string
  readonly problem: Problem

  constructor(problem: Problem) {
    super(problem.title)
    this.name = 'ApiError'
    this.status = problem.status
    this.code = problem.code
    this.problem = problem
  }

  is(code: string): boolean {
    return this.code === code
  }
}

export type TokenGetter = () => Promise<string | null>

export interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  query?: Record<string, string | number | boolean | null | undefined>
  signal?: AbortSignal
  headers?: Record<string, string>
}

function buildUrl(path: string, query?: RequestOptions['query']): string {
  const url = new URL(API_URL + (path.startsWith('/') ? path : `/${path}`))
  for (const [key, value] of Object.entries(query ?? {})) {
    if (value !== undefined && value !== null && value !== '')
      url.searchParams.set(key, String(value))
  }
  return url.toString()
}

async function toProblem(response: Response): Promise<Problem> {
  const fallback: Problem = {
    title:
      response.status >= 500
        ? 'Le service est momentanément indisponible.'
        : 'La requête n’a pas abouti.',
    status: response.status,
    code: response.status >= 500 ? 'SERVER_ERROR' : `HTTP_${String(response.status)}`,
  }
  try {
    const body = (await response.json()) as Partial<Problem>
    return { ...fallback, ...body, status: response.status }
  } catch {
    return fallback
  }
}

export async function apiRequest<T>(
  path: string,
  options: RequestOptions = {},
  getToken?: TokenGetter,
): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json', ...options.headers }
  // A FormData body (file upload) sets its own multipart Content-Type with the boundary.
  const isForm = options.body instanceof FormData
  if (options.body !== undefined && !isForm) headers['Content-Type'] = 'application/json'

  const token = getToken ? await getToken() : null
  if (token) headers.Authorization = `Bearer ${token}`

  let response: Response
  try {
    response = await fetch(buildUrl(path, options.query), {
      method: options.method ?? 'GET',
      headers,
      body:
        options.body === undefined
          ? undefined
          : isForm
            ? (options.body as FormData)
            : JSON.stringify(options.body),
      signal: options.signal,
    })
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') throw error
    throw new ApiError({
      title: 'Impossible de joindre la billetterie. Vérifiez votre connexion.',
      status: 0,
      code: 'NETWORK_ERROR',
    })
  }

  if (!response.ok) throw new ApiError(await toProblem(response))
  if (response.status === 204) return undefined as T
  return (await response.json()) as T
}

/**
 * Where to load a poster from. Posters uploaded in the Studio live on the API
 * (`/api/posters/…`), which is another origin than the site; the demo posters ship
 * with the site (`/posters/…`) and stay as they are.
 */
export function mediaUrl(path: string): string
export function mediaUrl(path: string | null | undefined): string | undefined
export function mediaUrl(path: string | null | undefined): string | undefined {
  if (!path) return undefined
  return path.startsWith('/api/') ? API_URL + path.slice('/api'.length) : path
}

/** Authenticated binary download (PDF tickets): fetches with the session token. */
export async function apiBlob(path: string, getToken?: TokenGetter): Promise<Blob> {
  const token = getToken ? await getToken() : null
  const response = await fetch(buildUrl(path), {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  })
  if (!response.ok) throw new ApiError(await toProblem(response))
  return response.blob()
}
