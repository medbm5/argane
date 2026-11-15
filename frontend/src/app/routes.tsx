import type { RouteObject } from 'react-router'
import { SignInPage, SignUpPage } from '../pages/AuthPages'
import { ComingSoonPage } from '../pages/ComingSoonPage'
import { HomePage } from '../pages/HomePage'
import { NotFoundPage, RouteErrorPage } from '../pages/NotFoundPage'
import { RequireRole } from './auth/RequireRole'
import { PublicLayout, StaffLayout } from './layouts/layouts'
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
          {
            element: <RequireRole requires="ROLE_CUSTOMER" />,
            children: [{ path: 'mes-billets', element: <ComingSoonPage title="Mes billets" /> }],
          },
          ...devRoutes,
          { path: '*', element: <NotFoundPage /> },
        ],
      },
      {
        element: <RequireRole requires="ROLE_BOX_OFFICE" />,
        children: [
          {
            element: <StaffLayout />,
            children: [
              { path: 'guichet', element: <ComingSoonPage title="Guichet" /> },
              { path: 'controle', element: <ComingSoonPage title="Contrôle des billets" /> },
            ],
          },
        ],
      },
      {
        element: <RequireRole requires="ROLE_ORGANIZER" />,
        children: [{ path: 'studio/*', element: <ComingSoonPage title="Studio" /> }],
      },
    ],
  },
]
