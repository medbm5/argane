export interface NavItem {
  to: string
  label: string
}

export const PUBLIC_NAV: NavItem[] = [
  { to: '/programme', label: 'Programmation' },
  { to: '/mes-billets', label: 'Mes billets' },
]
