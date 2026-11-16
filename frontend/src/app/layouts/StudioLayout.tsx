import { BarChart3, LayoutGrid, Plug, ReceiptText, Tag, Theater } from 'lucide-react'
import { NavLink, Outlet, ScrollRestoration } from 'react-router'
import { cn } from '../../design-system/cn'
import { UserMenu } from '../auth/UserMenu'
import { Logo } from './SiteHeader'

const STUDIO_NAV = [
  { to: '/studio', label: 'Vue d’ensemble', icon: BarChart3, end: true },
  { to: '/studio/spectacles', label: 'Spectacles', icon: Theater },
  { to: '/studio/configurations', label: 'Configurations', icon: LayoutGrid },
  { to: '/studio/tarifs', label: 'Tarifs', icon: Tag },
  { to: '/studio/ventes', label: 'Ventes', icon: ReceiptText },
  { to: '/studio/integrations', label: 'Intégrations', icon: Plug },
]

/** The organiser's back office: dark sidebar, light working area. */
export function StudioLayout() {
  return (
    <div className="flex min-h-dvh flex-col bg-bg text-fg md:flex-row">
      <a
        href="#studio-main"
        className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-pop-yellow focus:p-2"
      >
        Aller au contenu
      </a>
      <aside
        data-theme="night"
        className="flex shrink-0 flex-col gap-6 bg-ink-900 p-4 text-paper-100 md:sticky md:top-0 md:h-dvh md:w-60"
      >
        <div className="flex items-center justify-between gap-3 md:block">
          <div className="flex items-end gap-3 md:block">
            <Logo />
            <p className="font-marker text-lg text-pop-yellow md:mt-2">studio</p>
          </div>
          {/* On a phone the sidebar is a top bar: keep the account menu reachable there. */}
          <div className="md:hidden">
            <UserMenu compact />
          </div>
        </div>
        <nav aria-label="Studio" className="flex gap-1 overflow-x-auto md:flex-col">
          {STUDIO_NAV.map(({ to, label, icon: Icon, end }) => (
            <NavLink
              key={to}
              to={to}
              end={end}
              className={({ isActive }) =>
                cn(
                  'flex shrink-0 items-center gap-3 px-3 py-2 font-display text-lg tracking-[0.06em] uppercase transition-colors',
                  isActive ? 'bg-pop-yellow text-ink-900' : 'hover:bg-paper-100/10',
                )
              }
            >
              <Icon aria-hidden className="size-5" />
              {label}
            </NavLink>
          ))}
        </nav>
        <div className="mt-auto hidden md:block">
          <UserMenu />
        </div>
      </aside>
      <main id="studio-main" className="min-w-0 flex-1 px-4 py-8 sm:px-8">
        <Outlet />
      </main>
      <ScrollRestoration />
    </div>
  )
}
