import { useState } from 'react'
import { createBrowserRouter, RouterProvider } from 'react-router'
import { routes } from './app/routes'
import { SvgDefs } from './design-system/SvgDefs'
import { Toaster } from './design-system/primitives/Toaster'

export function App() {
  const [router] = useState(() => createBrowserRouter(routes))
  return (
    <>
      <SvgDefs />
      <RouterProvider router={router} />
      <Toaster />
    </>
  )
}
