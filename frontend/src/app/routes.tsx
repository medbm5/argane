import type { RouteObject } from 'react-router'
import { HomePage } from '../pages/HomePage'
import { NotFoundPage, RouteErrorPage } from '../pages/NotFoundPage'
import { PublicLayout } from './layouts/layouts'
import { RootProviders } from './RootProviders'

const devRoutes: RouteObject[] = import.meta.env.DEV
  ? [
      {
        path: 'styleguide',
        lazy: async () => {
          const { StyleguidePage } = await import('../pages/StyleguidePage')
          return { Component: StyleguidePage }
        },
      },
    ]
  : []

export const routes: RouteObject[] = [
  {
    element: <RootProviders />,
    errorElement: <RouteErrorPage />,
    children: [
      {
        element: <PublicLayout />,
        children: [
          { index: true, element: <HomePage /> },
          ...devRoutes,
          { path: '*', element: <NotFoundPage /> },
        ],
      },
    ],
  },
]
