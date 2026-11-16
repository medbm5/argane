import { DatePicker } from '../../design-system/primitives/DatePicker'
import { Download } from 'lucide-react'
import { useState } from 'react'
import { StatusBadge } from '../../design-system/primitives/Badge'
import { Button } from '../../design-system/primitives/Button'
import { Input } from '../../design-system/primitives/Input'
import { Select } from '../../design-system/primitives/Select'
import { useSales, useStudioEvents } from '../../features/studio/api'
import { StudioPage, StudioTable } from '../../features/studio/StudioPage'
import { useDownload } from '../../features/tickets/api'
import { formatMoney, formatShortDate, formatTime } from '../../lib/format'
import { usePageTitle } from '../../lib/usePageTitle'

const ANY = 'all'

/** /studio/ventes — orders with filters, totals and the CSV export. */
export function StudioSalesPage() {
  usePageTitle('Ventes · Studio')
  const events = useStudioEvents()
  const [filters, setFilters] = useState({
    event: ANY,
    channel: ANY,
    status: ANY,
    from: '',
    to: '',
    q: '',
  })
  const [page, setPage] = useState(1)
  const query = Object.fromEntries(
    Object.entries({ ...filters, page: String(page) }).filter(([, v]) => v !== '' && v !== ANY),
  )
  const sales = useSales(query)
  const { download, busy } = useDownload()
  const data = sales.data
  const pages = data ? Math.max(1, Math.ceil(data.total / data.perPage)) : 1
  const set = (key: keyof typeof filters, value: string) => {
    setFilters((f) => ({ ...f, [key]: value }))
    setPage(1)
  }
  const csvQuery = new URLSearchParams(
    Object.fromEntries(Object.entries(query).filter(([k]) => k !== 'page')),
  ).toString()

  return (
    <StudioPage
      kicker="les recettes"
      title="Ventes"
      actions={
        <Button
          variant="gilt"
          loading={busy !== null}
          leadingIcon={<Download aria-hidden className="size-4" />}
          onClick={() =>
            void download(`/studio/sales.csv${csvQuery ? `?${csvQuery}` : ''}`, 'ventes-argane.csv')
          }
        >
          Exporter en CSV
        </Button>
      }
    >
      <div className="brutal mb-6 grid gap-3 bg-surface p-4 sm:grid-cols-2 lg:grid-cols-6">
        <Select
          label="Spectacle"
          value={filters.event}
          onValueChange={(v) => {
            set('event', v)
          }}
          options={[
            { value: ANY, label: 'Tous' },
            ...(events.data ?? []).map((e) => ({ value: e.id, label: e.title })),
          ]}
        />
        <Select
          label="Canal"
          value={filters.channel}
          onValueChange={(v) => {
            set('channel', v)
          }}
          options={[
            { value: ANY, label: 'Tous' },
            { value: 'ONLINE', label: 'En ligne' },
            { value: 'BOX_OFFICE', label: 'Guichet' },
          ]}
        />
        <Select
          label="Statut"
          value={filters.status}
          onValueChange={(v) => {
            set('status', v)
          }}
          options={[
            { value: ANY, label: 'Tous' },
            { value: 'PAID', label: 'Payée' },
            { value: 'CANCELLED', label: 'Annulée' },
            { value: 'REFUNDED', label: 'Remboursée' },
          ]}
        />
        <DatePicker
          label="Du"
          value={filters.from}
          max={filters.to || undefined}
          clearable
          onChange={(from) => {
            set('from', from)
          }}
        />
        <DatePicker
          label="Au"
          value={filters.to}
          min={filters.from || undefined}
          clearable
          onChange={(to) => {
            set('to', to)
          }}
        />
        <Input
          label="Recherche"
          placeholder="Référence, nom, email"
          value={filters.q}
          onChange={(e) => {
            set('q', e.target.value)
          }}
        />
      </div>

      {data ? (
        <p className="mb-4 flex flex-wrap gap-x-8 gap-y-1 font-display text-2xl" aria-live="polite">
          <span>{data.total} commandes</span>
          <span>{data.ticketsSold} billets</span>
          <span>{formatMoney(data.revenueCents, data.currency)}</span>
        </p>
      ) : null}

      <StudioTable label="Commandes">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Date</th>
            <th>Spectacle</th>
            <th>Client</th>
            <th>Canal</th>
            <th>Statut</th>
            <th className="text-right">Billets</th>
            <th className="text-right">Total</th>
          </tr>
        </thead>
        <tbody className={sales.isPlaceholderData ? 'opacity-50' : undefined}>
          {(data?.rows ?? []).map((row) => (
            <tr key={row.id}>
              <td className="tabular font-bold whitespace-nowrap">{row.reference}</td>
              <td className="whitespace-nowrap">
                {formatShortDate(row.createdAt)} {formatTime(row.createdAt)}
              </td>
              <td>
                {row.eventTitle}
                <span className="block text-xs text-muted">
                  {formatShortDate(row.performanceStartsAt)}
                </span>
              </td>
              <td>
                {row.buyer ?? '—'}
                {row.buyerEmail ? (
                  <span className="block text-xs text-muted">{row.buyerEmail}</span>
                ) : null}
              </td>
              <td>
                {row.channel === 'ONLINE'
                  ? 'en ligne'
                  : `guichet${row.seller ? ` (${row.seller})` : ''}`}
              </td>
              <td>
                <StatusBadge status={row.status} />
              </td>
              <td className="tabular text-right">{row.tickets}</td>
              <td className="tabular text-right">
                {formatMoney(row.totalCents, data?.currency ?? 'MAD')}
              </td>
            </tr>
          ))}
        </tbody>
      </StudioTable>
      <div className="mt-4 flex items-center justify-end gap-3">
        <Button
          size="sm"
          variant="secondary"
          disabled={page <= 1}
          onClick={() => {
            setPage((p) => p - 1)
          }}
        >
          Précédent
        </Button>
        <span className="tabular text-sm">
          {page} / {pages}
        </span>
        <Button
          size="sm"
          variant="secondary"
          disabled={page >= pages}
          onClick={() => {
            setPage((p) => p + 1)
          }}
        >
          Suivant
        </Button>
      </div>
    </StudioPage>
  )
}
