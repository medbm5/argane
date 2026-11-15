import { Menu } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Link, NavLink } from 'react-router'
import { cn } from '../../design-system/cn'
import { IconButton } from '../../design-system/primitives/IconButton'
import { Sheet } from '../../design-system/primitives/Sheet'
import { PUBLIC_NAV, type NavItem } from './nav'

function navClass({ isActive }: { isActive: boolean }) {
  return cn(
    'text-sm transition-colors duration-200 hover:text-accent',
    isActive ? 'text-accent underline decoration-1 underline-offset-8' : 'text-fg',
  )
}

export function Logo({ className }: { className?: string }) {
  return (
    <Link to="/" className={cn('group flex items-baseline gap-2', className)}>
      <span className="font-display text-2xl leading-none font-semibold tracking-tight">
        Théâtre Argane
      </span>
      <span className="small-caps hidden text-xs text-muted sm:inline">Agadir · 1974</span>
    </Link>
  )
}

export function SiteHeader({
  nav = PUBLIC_NAV,
  actions,
}: {
  nav?: NavItem[]
  actions?: ReactNode
}) {
  const [open, setOpen] = useState(false)

  return (
    <header className="border-b border-line">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
        <Logo />
        <nav aria-label="Navigation principale" className="hidden items-center gap-6 md:flex">
          {nav.map((item) => (
            <NavLink key={item.to} to={item.to} className={navClass}>
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
            <nav aria-label="Navigation mobile" className="flex flex-col gap-4">
              {nav.map((item) => (
                <NavLink
                  key={item.to}
                  to={item.to}
                  className={navClass}
                  onClick={() => {
                    setOpen(false)
                  }}
                >
                  {item.label}
                </NavLink>
              ))}
              {actions}
            </nav>
          </Sheet>
        </div>
      </div>
    </header>
  )
}
