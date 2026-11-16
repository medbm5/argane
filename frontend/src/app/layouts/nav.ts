import { useMe, type Me } from '../auth/useMe'

export interface NavItem {
  to: string
  label: string
  /** Match the path exactly (for section roots such as /studio). */
  end?: boolean
}

export const PUBLIC_NAV: NavItem[] = [
  { to: '/programme', label: 'Programmation' },
  { to: '/mes-billets', label: 'Mes billets' },
]

export const STAFF_NAV: NavItem[] = [
  { to: '/guichet', label: 'Guichet' },
  { to: '/controle', label: 'Contrôle' },
]

const STUDIO: NavItem = { to: '/studio', label: 'Studio' }
const GUICHET: NavItem = { to: '/guichet', label: 'Guichet' }

/**
 * The header links for who is signed in: the organizer reaches the Studio from every
 * page, box-office staff reach the Guichet. Everything else lives in the account menu.
 */
export function withRoleLinks(base: NavItem[], me: Me | undefined): NavItem[] {
  const extra = me?.isOrganizer ? [STUDIO] : me?.isBoxOffice ? [GUICHET] : []
  return [...base, ...extra.filter((item) => !base.some((b) => b.to === item.to))]
}

export function useNav(base: NavItem[]): NavItem[] {
  const me = useMe()
  return withRoleLinks(base, me.data)
}

/** Links of the account menu (desktop pop-over and phone drawer), by role. */
export function accountLinks(me: Me | undefined): NavItem[] {
  return [
    { to: '/mes-billets', label: 'Mes billets' },
    ...(me?.isBoxOffice
      ? [
          { to: '/guichet', label: 'Guichet' },
          { to: '/controle', label: 'Contrôle des billets' },
        ]
      : []),
    ...(me?.isOrganizer ? [STUDIO] : []),
  ]
}
