import type { RouteObject } from 'react-router'
import { SignInPage, SignUpPage } from '../pages/AuthPages'
import { CheckoutPage } from '../pages/CheckoutPage'
import { ControlePage } from '../pages/ControlePage'
import { EventPage } from '../pages/EventPage'
import { GuichetPage } from '../pages/GuichetPage'
import { HomePage } from '../pages/HomePage'
import { NotFoundPage, RouteErrorPage } from '../pages/NotFoundPage'
import { OrderConfirmationPage } from '../pages/OrderConfirmationPage'
import { OrderDetailPage } from '../pages/OrderDetailPage'
import { ProgrammePage } from '../pages/ProgrammePage'
import { SeatSelectionPage } from '../pages/SeatSelectionPage'
import { WalletPage } from '../pages/WalletPage'
import { RequireRole } from './auth/RequireRole'
import { NightLayout, PublicLayout, StaffLayout } from './layouts/layouts'
import { StudioLayout } from './layouts/StudioLayout'
import { StudioEventPage } from '../pages/studio/StudioEventPage'
import { StudioEventsPage } from '../pages/studio/StudioEventsPage'
import { StudioLayoutsPage } from '../pages/studio/StudioLayoutsPage'
import { StudioOverviewPage } from '../pages/studio/StudioOverviewPage'
import { StudioPricesPage } from '../pages/studio/StudioPricesPage'
import { StudioSalesPage } from '../pages/studio/StudioSalesPage'
import { StudioTariffsPage } from '../pages/studio/StudioTariffsPage'
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
          { path: 'programme', element: <ProgrammePage /> },
          { path: 'spectacles/:slug', element: <EventPage /> },
          { path: 'connexion/*', element: <SignInPage /> },
          { path: 'inscription/*', element: <SignUpPage /> },
          {
            element: <RequireRole requires="ROLE_CUSTOMER" />,
            children: [
              { path: 'mes-billets', element: <WalletPage /> },
              { path: 'mes-billets/:orderId', element: <OrderDetailPage /> },
              { path: 'commande/:holdId', element: <CheckoutPage /> },
              { path: 'commandes/:orderId/confirmation', element: <OrderConfirmationPage /> },
            ],
          },
          ...devRoutes,
          { path: '*', element: <NotFoundPage /> },
        ],
      },
      {
        element: <NightLayout />,
        children: [{ path: 'representations/:id', element: <SeatSelectionPage /> }],
      },
      {
        element: <RequireRole requires="ROLE_BOX_OFFICE" />,
        children: [
          {
            element: <StaffLayout />,
            children: [
              { path: 'guichet', element: <GuichetPage /> },
              { path: 'controle', element: <ControlePage /> },
            ],
          },
        ],
      },
      {
        element: <RequireRole requires="ROLE_ORGANIZER" />,
        children: [
          {
            path: 'studio',
            element: <StudioLayout />,
            children: [
              { index: true, element: <StudioOverviewPage /> },
              { path: 'spectacles', element: <StudioEventsPage /> },
              { path: 'spectacles/:id', element: <StudioEventPage /> },
              { path: 'representations/:id/prix', element: <StudioPricesPage /> },
              { path: 'configurations', element: <StudioLayoutsPage /> },
              { path: 'tarifs', element: <StudioTariffsPage /> },
              { path: 'ventes', element: <StudioSalesPage /> },
              { path: '*', element: <NotFoundPage /> },
            ],
          },
        ],
      },
    ],
  },
]
