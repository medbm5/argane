import { ArrowLeft, Plus } from 'lucide-react'
import { Link, useParams } from 'react-router'
import { StatusBadge } from '../../design-system/primitives/Badge'
import { Button } from '../../design-system/primitives/Button'
import { toast } from '../../design-system/toast-store'
import {
  useEventAction,
  usePerformanceAction,
  useStudioEvent,
  type PerformanceAction,
  type StudioPerformance,
} from '../../features/studio/api'
import { EventFormDialog } from '../../features/studio/EventFormDialog'
import { CancelPerformanceDialog, ScheduleDialog } from '../../features/studio/PerformanceDialogs'
import { StudioPage, StudioTable } from '../../features/studio/StudioPage'
import { ApiError } from '../../lib/api'
import { formatMoney, formatShortDate, formatTime } from '../../lib/format'
import { usePageTitle } from '../../lib/usePageTitle'

/** /studio/spectacles/:id — the event, its dates and every lifecycle action. */
export function StudioEventPage() {
  const { id = '' } = useParams()
  const event = useStudioEvent(id)
  usePageTitle(event.data ? `${event.data.title} · Studio` : 'Studio')
  const eventAction = useEventAction(id)
  const performanceAction = usePerformanceAction()

  if (!event.data) {
    return (
      <p className="font-display text-3xl">
        {event.isError ? 'Spectacle introuvable.' : 'Chargement…'}
      </p>
    )
  }
  const e = event.data

  function run(p: StudioPerformance, action: PerformanceAction, done: string) {
    performanceAction.mutate(
      { id: p.id, action },
      {
        onSuccess: () => {
          toast(done, { tone: 'success' })
        },
        onError: (err) => {
          toast(err instanceof ApiError ? err.message : 'Action impossible', { tone: 'danger' })
        },
      },
    )
  }

  return (
    <StudioPage
      kicker={e.categoryLabel}
      title={e.title}
      actions={
        <>
          <EventFormDialog event={e} trigger={<Button variant="secondary">Modifier</Button>} />
          {e.status === 'DRAFT' ? (
            <Button
              variant="gilt"
              loading={eventAction.isPending}
              onClick={() => {
                eventAction.mutate('publish')
              }}
            >
              Publier le spectacle
            </Button>
          ) : null}
          {e.status === 'PUBLISHED' ? (
            <Button
              variant="ghost"
              onClick={() => {
                eventAction.mutate('archive')
              }}
            >
              Archiver
            </Button>
          ) : null}
        </>
      }
    >
      <Link
        to="/studio/spectacles"
        className="mb-6 inline-flex items-center gap-2 text-sm font-bold uppercase hover:text-accent"
      >
        <ArrowLeft aria-hidden className="size-4" /> Spectacles
      </Link>
      <p className="mb-6 flex flex-wrap items-center gap-3">
        <StatusBadge status={e.status} />
        <span>
          {e.durationMinutes} min · {e.sold} / {e.capacity} vendus ·{' '}
          {formatMoney(e.revenueCents, 'MAD')}
        </span>
        {e.status === 'PUBLISHED' ? (
          <Link to={`/spectacles/${e.slug}`} className="underline underline-offset-4">
            Voir la page publique
          </Link>
        ) : null}
      </p>

      <div className="mb-4 flex items-center justify-between">
        <h2 className="font-display text-3xl">Dates</h2>
        <ScheduleDialog
          eventId={e.id}
          trigger={
            <Button variant="gilt" size="sm" leadingIcon={<Plus aria-hidden className="size-4" />}>
              Nouvelle date
            </Button>
          }
        />
      </div>
      <StudioTable label="Dates">
        <thead>
          <tr>
            <th>Date</th>
            <th>Configuration</th>
            <th>Statut</th>
            <th className="text-right">Vendus</th>
            <th>Prix</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          {e.performances.map((p) => {
            const live = p.status !== 'CANCELLED' && p.status !== 'PAST'
            return (
              <tr key={p.id}>
                <td className="whitespace-nowrap">
                  <span className="font-bold">{formatShortDate(p.startsAt)}</span>{' '}
                  {formatTime(p.startsAt)}
                </td>
                <td>{p.layoutName}</td>
                <td>
                  <StatusBadge status={p.published ? p.status : 'DRAFT'} />
                  {p.cancelReason ? (
                    <span className="block text-xs text-muted">{p.cancelReason}</span>
                  ) : null}
                </td>
                <td className="tabular text-right">
                  {p.sold} / {p.capacity}
                </td>
                <td>
                  <Link
                    to={`/studio/representations/${p.id}/prix`}
                    className="underline underline-offset-4"
                  >
                    {p.pricedCells > 0 ? `${String(p.pricedCells)} prix` : 'À définir'}
                  </Link>
                </td>
                <td>
                  <div className="flex flex-wrap gap-1.5">
                    {live && !p.published ? (
                      <>
                        <Button
                          size="sm"
                          variant="secondary"
                          disabled={e.status !== 'PUBLISHED' || p.pricedCells === 0}
                          onClick={() => {
                            run(p, 'publish', 'Date publiée')
                          }}
                        >
                          Publier
                        </Button>
                        <Button
                          size="sm"
                          variant="gilt"
                          disabled={e.status !== 'PUBLISHED' || p.pricedCells === 0}
                          onClick={() => {
                            run(p, 'publish-open', 'Date publiée, ventes ouvertes')
                          }}
                        >
                          Publier + vendre
                        </Button>
                      </>
                    ) : null}
                    {live && p.published && p.status !== 'ON_SALE' ? (
                      <Button
                        size="sm"
                        variant="gilt"
                        onClick={() => {
                          run(p, 'open-sales', 'Ventes ouvertes')
                        }}
                      >
                        Ouvrir les ventes
                      </Button>
                    ) : null}
                    {live && p.status === 'ON_SALE' ? (
                      <Button
                        size="sm"
                        variant="secondary"
                        onClick={() => {
                          run(p, 'close-sales', 'Ventes fermées')
                        }}
                      >
                        Fermer les ventes
                      </Button>
                    ) : null}
                    {live ? (
                      <>
                        <ScheduleDialog
                          eventId={e.id}
                          performance={p}
                          trigger={
                            <Button size="sm" variant="ghost">
                              Modifier
                            </Button>
                          }
                        />
                        <CancelPerformanceDialog
                          eventId={e.id}
                          performance={p}
                          trigger={
                            <Button size="sm" variant="danger">
                              Annuler
                            </Button>
                          }
                        />
                      </>
                    ) : null}
                  </div>
                </td>
              </tr>
            )
          })}
        </tbody>
      </StudioTable>
      {e.status !== 'PUBLISHED' ? (
        <p className="mt-3 text-sm text-muted">
          Publiez le spectacle, puis chaque date une fois ses prix définis.
        </p>
      ) : null}
    </StudioPage>
  )
}
