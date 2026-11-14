import type { BadgeTone } from './Badge'

/** Maps API statuses to badge tones and French labels. */
export const STATUS_BADGES: Record<string, { label: string; tone: BadgeTone }> = {
  ON_SALE: { label: 'En vente', tone: 'success' },
  SCHEDULED: { label: 'Programmée', tone: 'neutral' },
  SALES_CLOSED: { label: 'Ventes closes', tone: 'accent' },
  CANCELLED: { label: 'Annulée', tone: 'danger' },
  SOLD_OUT: { label: 'Complet', tone: 'highlight' },
  PAST: { label: 'Passée', tone: 'neutral' },
  DRAFT: { label: 'Brouillon', tone: 'neutral' },
  PUBLISHED: { label: 'Publié', tone: 'success' },
  ARCHIVED: { label: 'Archivé', tone: 'neutral' },
  PAID: { label: 'Payée', tone: 'success' },
  PENDING_PAYMENT: { label: 'En attente de paiement', tone: 'gilt' },
  REFUNDED: { label: 'Remboursée', tone: 'accent' },
  PARTIALLY_REFUNDED: { label: 'Remboursée en partie', tone: 'accent' },
  EXPIRED: { label: 'Expirée', tone: 'neutral' },
  FAILED: { label: 'Échouée', tone: 'danger' },
  VALID: { label: 'Valide', tone: 'success' },
  CHECKED_IN: { label: 'Scanné', tone: 'ink' },
}
