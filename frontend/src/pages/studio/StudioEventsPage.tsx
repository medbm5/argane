import { Plus } from 'lucide-react'
import { Link } from 'react-router'
import { StatusBadge } from '../../design-system/primitives/Badge'
import { Button } from '../../design-system/primitives/Button'
import { useStudioEvents } from '../../features/studio/api'
import { EventFormDialog } from '../../features/studio/EventFormDialog'
import { StudioPage, StudioTable } from '../../features/studio/StudioPage'
import { formatMoney, formatShortDate } from '../../lib/format'

/** /studio/spectacles — every event with its fill and revenue. */
export function StudioEventsPage() {
  const events = useStudioEvents()

  return (
    <StudioPage
      kicker="la programmation"
      title="Spectacles"
      actions={
        <EventFormDialog
          trigger={
            <Button variant="gilt" leadingIcon={<Plus aria-hidden className="size-4" />}>
              Nouveau spectacle
            </Button>
          }
        />
      }
    >
      <StudioTable label="Spectacles">
        <thead>
          <tr>
            <th>Spectacle</th>
            <th>Statut</th>
            <th>Dates</th>
            <th className="text-right">Remplissage</th>
            <th className="text-right">Recettes</th>
          </tr>
        </thead>
        <tbody>
          {(events.data ?? []).map((event) => {
            const next = event.performances.find(
              (p) => p.status !== 'PAST' && p.status !== 'CANCELLED',
            )
            const fill = event.capacity > 0 ? Math.round((event.sold / event.capacity) * 100) : 0
            return (
              <tr key={event.id}>
                <td>
                  <Link
                    to={`/studio/spectacles/${event.id}`}
                    className="font-display text-xl hover:text-accent"
                  >
                    {event.title}
                  </Link>
                  <span className="block text-xs text-muted">{event.categoryLabel}</span>
                </td>
                <td>
                  <StatusBadge status={event.status} />
                </td>
                <td>
                  {event.performances.length} date(s)
                  {next ? (
                    <span className="block text-xs text-muted">
                      prochaine {formatShortDate(next.startsAt)}
                    </span>
                  ) : null}
                </td>
                <td className="tabular text-right">
                  {event.sold} / {event.capacity} <span className="text-muted">({fill} %)</span>
                </td>
                <td className="tabular text-right">{formatMoney(event.revenueCents, 'MAD')}</td>
              </tr>
            )
          })}
        </tbody>
      </StudioTable>
    </StudioPage>
  )
}
