import type { RouteObject } from 'react-router'
import { HomePage } from '../pages/HomePage'

export const routes: RouteObject[] = [
  { path: '/', element: <HomePage /> },
  ...(import.meta.env.DEV
    ? [
        {
          path: '/styleguide',
          lazy: async () => {
            const { StyleguidePage } = await import('../pages/StyleguidePage')
            return { Component: StyleguidePage }
          },
        },
      ]
    : []),
]
