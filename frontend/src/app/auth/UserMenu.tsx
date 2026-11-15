import * as Popover from '@radix-ui/react-popover'
import { LogOut, Ticket, UserRound } from 'lucide-react'
import { Link } from 'react-router'
import { Button } from '../../design-system/primitives/Button'
import { useAuthState } from './AuthContext'
import { useMe } from './useMe'

const itemClass =
  'flex items-center gap-2 rounded-sm px-3 py-2 text-sm text-fg hover:bg-surface-alt focus-visible:bg-surface-alt'

export function UserMenu() {
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
          leadingIcon={<UserRound aria-hidden className="size-4" />}
        >
          <span className="max-w-32 truncate">{name}</span>
        </Button>
      </Popover.Trigger>
      <Popover.Portal>
        <Popover.Content
          align="end"
          sideOffset={8}
          className="z-50 w-60 rounded-md border border-line bg-surface p-2 text-fg shadow-float"
        >
          {me.data ? (
            <p className="truncate px-3 pt-1 pb-2 text-xs text-muted">{me.data.email}</p>
          ) : null}
          <nav aria-label="Mon compte" className="flex flex-col">
            <Popover.Close asChild>
              <Link to="/mes-billets" className={itemClass}>
                <Ticket aria-hidden className="size-4" /> Mes billets
              </Link>
            </Popover.Close>
            {me.data?.isBoxOffice ? (
              <>
                <Popover.Close asChild>
                  <Link to="/guichet" className={itemClass}>
                    Guichet
                  </Link>
                </Popover.Close>
                <Popover.Close asChild>
                  <Link to="/controle" className={itemClass}>
                    Contrôle des billets
                  </Link>
                </Popover.Close>
              </>
            ) : null}
            {me.data?.isOrganizer ? (
              <Popover.Close asChild>
                <Link to="/studio" className={itemClass}>
                  Studio
                </Link>
              </Popover.Close>
            ) : null}
            <button
              type="button"
              className={`${itemClass} mt-1 border-t border-line pt-3 text-left`}
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
