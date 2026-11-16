import { ArrowRight, Menu } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Link, NavLink } from 'react-router'
import { cn } from '../../design-system/cn'
import { IconButton } from '../../design-system/primitives/IconButton'
import { Sheet } from '../../design-system/primitives/Sheet'
import { AccountPanel, UserMenu } from '../auth/UserMenu'
import { PUBLIC_NAV, useNav, type NavItem } from './nav'

function navClass({ isActive }: { isActive: boolean }) {
  return cn(
    'font-display text-base tracking-[0.1em] uppercase transition-colors duration-200 hover:text-accent',
    isActive ? 'text-accent underline decoration-2 underline-offset-8' : 'text-fg',
  )
}

export function Logo({ className }: { className?: string }) {
  return (
    <Link
      to="/"
      aria-label="Théâtre Argane, accueil"
      className={cn('group flex flex-col items-start leading-none', className)}
    >
      <span className="-rotate-3 font-marker text-2xl transition-transform duration-200 group-hover:rotate-0">
        Argane
      </span>
      <span className="font-display text-[0.65rem] tracking-[0.35em] text-accent uppercase">
        Théâtre · Agadir
      </span>
    </Link>
  )
}

export function SiteHeader({
  nav: base = PUBLIC_NAV,
  actions = <UserMenu />,
}: {
  nav?: NavItem[]
  actions?: ReactNode
}) {
  const [open, setOpen] = useState(false)
  const nav = useNav(base)
  const close = () => {
    setOpen(false)
  }

  return (
    <header
      data-theme="night"
      className="sticky top-0 z-40 border-b-2 border-ink-900 bg-ink-900 text-paper-100"
    >
      <div className="mx-auto flex h-18 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
        <Logo />
        <nav aria-label="Navigation principale" className="hidden items-center gap-6 md:flex">
          {nav.map((item) => (
            <NavLink key={item.to} to={item.to} end={item.end} className={navClass}>
              {item.label}
            </NavLink>
          ))}
          {actions}
        </nav>
        <div className="md:hidden">
          <Sheet
            open={open}
            onOpenChange={setOpen}
            title="Menu"
            trigger={<IconButton label="Ouvrir le menu" icon={<Menu className="size-5" />} />}
          >
            <div className="flex flex-col gap-8">
              <nav
                aria-label="Navigation mobile"
                className="flex flex-col border-t-2 border-ink-900"
              >
                {nav.map((item) => (
                  <NavLink
                    key={item.to}
                    to={item.to}
                    end={item.end}
                    onClick={close}
                    className={({ isActive }) =>
                      cn(
                        'flex items-center justify-between border-b-2 border-ink-900 py-4 font-display text-3xl tracking-[0.04em] uppercase',
                        isActive ? 'text-accent' : 'text-fg',
                      )
                    }
                  >
                    {item.label}
                    <ArrowRight aria-hidden className="size-6" />
                  </NavLink>
                ))}
              </nav>
              <AccountPanel onNavigate={close} shown={nav.map((item) => item.to)} />
            </div>
          </Sheet>
        </div>
      </div>
    </header>
  )
}
