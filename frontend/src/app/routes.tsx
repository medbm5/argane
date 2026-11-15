import type { RouteObject } from 'react-router'
import { SignInPage, SignUpPage } from '../pages/AuthPages'
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
          { path: 'connexion/*', element: <SignInPage /> },
          { path: 'inscription/*', element: <SignUpPage /> },
          ...devRoutes,
          { path: '*', element: <NotFoundPage /> },
        ],
      },
    ],
  },
]
