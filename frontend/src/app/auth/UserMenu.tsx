import * as Popover from '@radix-ui/react-popover'
import { LogOut, UserRound } from 'lucide-react'
import { Link, NavLink } from 'react-router'
import { cn } from '../../design-system/cn'
import { Button } from '../../design-system/primitives/Button'
import { accountLinks } from '../layouts/nav'
import { useAuthState } from './AuthContext'
import { useMe } from './useMe'

const itemClass =
  'flex items-center gap-2 px-4 py-2.5 font-display text-base tracking-[0.08em] uppercase text-ink-900 hover:bg-pop-yellow focus-visible:bg-pop-yellow focus-visible:outline-none'

/** Desktop account menu: a pop-over in the style of our other menus. */
export function UserMenu({ compact = false }: { compact?: boolean }) {
  const { isLoaded, isSignedIn, signOut } = useAuthState()
  const me = useMe()

  if (!isLoaded) return null
  if (!isSignedIn) {
    return (
      <Button asChild variant="secondary" size="sm">
        <Link to="/connexion">Connexion</Link>
      </Button>
    )
  }

  const name = me.data?.displayName ?? 'Mon compte'

  return (
    <Popover.Root>
      <Popover.Trigger asChild>
        <Button
          variant="ghost"
          size="sm"
          aria-label={compact ? `Compte de ${name}` : undefined}
          leadingIcon={<UserRound aria-hidden className="size-4" />}
        >
          {compact ? null : <span className="max-w-32 truncate">{name}</span>}
        </Button>
      </Popover.Trigger>
      <Popover.Portal>
        <Popover.Content
          align="end"
          sideOffset={8}
          className={cn(
            'z-50 w-64 border-2 border-ink-900 bg-paper-50 text-ink-900 shadow-[6px_6px_0_var(--ink-900)]',
            'origin-(--radix-popover-content-transform-origin) animate-[rise-in_160ms_var(--ease-out)]',
          )}
        >
          <div className="bg-ink-900 px-4 py-2 text-paper-50">
            <p className="truncate font-display tracking-[0.08em] text-pop-yellow uppercase">
              {name}
            </p>
            {me.data ? <p className="truncate text-xs text-paper-100/70">{me.data.email}</p> : null}
          </div>
          <nav aria-label="Mon compte" className="flex flex-col py-1">
            {accountLinks(me.data).map((item) => (
              <Popover.Close asChild key={item.to}>
                <Link to={item.to} className={itemClass}>
                  {item.label}
                </Link>
              </Popover.Close>
            ))}
            <button
              type="button"
              className={cn(itemClass, 'mt-1 border-t-2 border-ink-900 text-left')}
              onClick={() => void signOut()}
            >
              <LogOut aria-hidden className="size-4" /> Se déconnecter
            </button>
          </nav>
        </Popover.Content>
      </Popover.Portal>
    </Popover.Root>
  )
}

/**
 * The same account entries laid out flat for the phone drawer: no pop-over on top of
 * the drawer, and every link is a full-width row.
 */
export function AccountPanel({
  onNavigate,
  shown = [],
}: {
  onNavigate: () => void
  /** Pages the drawer already lists above, not repeated here. */
  shown?: string[]
}) {
  const { isLoaded, isSignedIn, signOut } = useAuthState()
  const me = useMe()

  if (!isLoaded) return null
  if (!isSignedIn) {
    return (
      <Button asChild variant="primary" block>
        <Link to="/connexion" onClick={onNavigate}>
          Connexion
        </Link>
      </Button>
    )
  }

  return (
    <section aria-label="Mon compte" className="border-2 border-ink-900">
      <div className="bg-ink-900 px-4 py-3 text-paper-50">
        <p className="font-marker text-sm text-pop-yellow">connecté·e</p>
        <p className="truncate font-display text-lg tracking-[0.06em] uppercase">
          {me.data?.displayName ?? 'Mon compte'}
        </p>
        {me.data ? <p className="truncate text-xs text-paper-100/70">{me.data.email}</p> : null}
      </div>
      <nav className="flex flex-col">
        {accountLinks(me.data)
          .filter((item) => !shown.includes(item.to))
          .map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              onClick={onNavigate}
              className={({ isActive }) =>
                cn(
                  'border-b-2 border-ink-900/10 px-4 py-3 font-display text-lg tracking-[0.06em] uppercase',
                  isActive ? 'bg-pop-yellow text-ink-900' : 'hover:bg-surface-alt',
                )
              }
            >
              {item.label}
            </NavLink>
          ))}
        <button
          type="button"
          className="flex items-center gap-2 px-4 py-3 text-left font-display text-lg tracking-[0.06em] text-accent uppercase hover:bg-surface-alt"
          onClick={() => {
            onNavigate()
            void signOut()
          }}
        >
          <LogOut aria-hidden className="size-5" /> Se déconnecter
        </button>
      </nav>
    </section>
  )
}
