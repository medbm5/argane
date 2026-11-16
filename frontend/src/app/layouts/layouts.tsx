import { useEffect } from 'react'
import { Outlet, ScrollRestoration } from 'react-router'
import { SiteFooter } from './SiteFooter'
import { STAFF_NAV } from './nav'
import { SiteHeader } from './SiteHeader'

function SkipLink() {
  return (
    <a
      href="#contenu"
      className="sr-only rounded-sm bg-primary px-3 py-2 text-on-primary focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50"
    >
      Aller au contenu
    </a>
  )
}

/** Paper surfaces: landing, programme, event pages, wallet. */
export function PublicLayout() {
  return (
    <div className="paper-grain wall flex min-h-dvh flex-col bg-bg text-fg">
      <SkipLink />
      <SiteHeader />
      <main id="contenu" className="flex-1">
        <Outlet />
      </main>
      <SiteFooter />
      <ScrollRestoration />
    </div>
  )
}

/**
 * Immersive "house lights down" layout for seat selection.
 * Sets data-theme on <html> so portals (dialogs, toasts) are night-themed too.
 */
export function NightLayout() {
  useEffect(() => {
    const root = document.documentElement
    const previous = root.dataset.theme
    root.dataset.theme = 'night'
    return () => {
      if (previous) root.dataset.theme = previous
      else delete root.dataset.theme
    }
  }, [])

  return (
    <div className="flex min-h-dvh flex-col bg-bg text-fg">
      <SkipLink />
      <SiteHeader />
      <main id="contenu" className="flex-1">
        <Outlet />
      </main>
      <ScrollRestoration />
    </div>
  )
}

/** Staff tools (box office, check-in): no marketing footer, full width. */
export function StaffLayout() {
  return (
    <div className="flex min-h-dvh flex-col bg-bg text-fg">
      <SkipLink />
      <SiteHeader nav={STAFF_NAV} />
      <main id="contenu" className="flex-1">
        <Outlet />
      </main>
    </div>
  )
}
