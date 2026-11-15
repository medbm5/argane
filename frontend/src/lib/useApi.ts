import { useMemo } from 'react'
import { useAuthState } from '../app/auth/AuthContext'
import { apiRequest, type RequestOptions } from './api'

export interface ApiClient {
  get: <T>(path: string, options?: Omit<RequestOptions, 'method' | 'body'>) => Promise<T>
  post: <T>(
    path: string,
    body?: unknown,
    options?: Omit<RequestOptions, 'method' | 'body'>,
  ) => Promise<T>
  put: <T>(
    path: string,
    body?: unknown,
    options?: Omit<RequestOptions, 'method' | 'body'>,
  ) => Promise<T>
  del: <T>(path: string, options?: Omit<RequestOptions, 'method' | 'body'>) => Promise<T>
}

/** API client that attaches the current Clerk session token to every request. */
export function useApi(): ApiClient {
  const { getToken } = useAuthState()
  return useMemo(
    () => ({
      get: (path, options) => apiRequest(path, { ...options, method: 'GET' }, getToken),
      post: (path, body, options) =>
        apiRequest(path, { ...options, method: 'POST', body }, getToken),
      put: (path, body, options) => apiRequest(path, { ...options, method: 'PUT', body }, getToken),
      del: (path, options) => apiRequest(path, { ...options, method: 'DELETE' }, getToken),
    }),
    [getToken],
  )
}
