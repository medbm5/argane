import { useEffect, useMemo, useReducer, useState } from 'react'
import { Link, useParams } from 'react-router'
import { toast } from '../design-system/toast-store'
import { useEvent, usePerformance } from '../features/catalog/api'
import { useAvailability, useSeatMap } from '../features/seatmap/api'
import { CurtainReveal } from '../features/seatmap/CurtainReveal'
import { EventStrip } from '../features/seatmap/EventStrip'
import { SeatMap } from '../features/seatmap/SeatMap'
import {
  EMPTY_SELECTION,
  selectedCount,
  selectionReducer,
  type SelectionAction,
} from '../features/seatmap/selection'
import { SelectionSummary } from '../features/seatmap/SelectionSummary'
import { ApiError } from '../lib/api'
import { NotFoundPage } from './NotFoundPage'

const LEGEND = [
  { label: 'Libre', swatch: 'bg-pop-teal' },
  { label: 'Votre choix', swatch: 'bg-paper-50 ring-4 ring-pop-yellow ring-inset' },
  {
    label: 'En cours',
    swatch: 'bg-[repeating-linear-gradient(45deg,var(--night-800)_0_3px,var(--night-600)_3px_5px)]',
  },
  { label: 'Vendue', swatch: 'bg-night-800' },
]

/** /representations/:id — choose seats on the plan (night theme). */
export function SeatSelectionPage() {
  const { id = '' } = useParams()
  const performance = usePerformance(id)
  const event = useEvent(performance.data?.eventSlug ?? '')
  const seatMap = useSeatMap(id)
  const availability = useAvailability(id, seatMap.isSuccess)

  // Reset the basket when switching performance.
  return seatMap.data && performance.data ? (
    <SeatSelection
      key={id}
      performanceId={id}
      performance={performance.data}
      event={event.data}
      map={seatMap.data}
      availability={availability.data}
    />
  ) : performance.isError || seatMap.isError ? (
    performance.error instanceof ApiError && performance.error.status === 404 ? (
      <NotFoundPage />
    ) : (
      <p className="mx-auto max-w-xl px-4 py-20 text-center">
        Le plan de salle n’a pas pu être chargé. Réessayez dans un instant.
      </p>
    )
  ) : (
    <div className="grid min-h-[70dvh] place-items-center" aria-busy="true">
      <p className="font-display text-4xl">Les lumières baissent…</p>
    </div>
  )
}

function SeatSelection({
  performanceId,
  performance,
  event,
  map,
  availability,
}: {
  performanceId: string
  performance: NonNullable<ReturnType<typeof usePerformance>['data']>
  event: ReturnType<typeof useEvent>['data']
  map: NonNullable<ReturnType<typeof useSeatMap>['data']>
  availability: ReturnType<typeof useAvailability>['data']
}) {
  const reducer = useMemo(() => selectionReducer(map.maxSeatsPerOrder), [map.maxSeatsPerOrder])
  const [selection, rawDispatch] = useReducer(reducer, EMPTY_SELECTION)
  const [activeStanding, setActiveStanding] = useState<string | null>(null)
  const [lost, setLost] = useState<string[]>([])

  function dispatch(action: SelectionAction) {
    const before = selection
    const after = reducer(before, action)
    if (action.type === 'toggleSeat' && after === before) {
      toast(`${String(map.maxSeatsPerOrder)} places maximum par commande`, {
        description: 'Retirez une place pour en choisir une autre.',
      })
    }
    rawDispatch(action)
  }

  // A poll shows one of our picks taken by someone else: drop it (adjusting state while
  // rendering, the React-recommended way to react to new props) and flash it.
  const [seenAvailability, setSeenAvailability] = useState(availability)
  if (availability !== seenAvailability) {
    setSeenAvailability(availability)
    const taken = new Set([
      ...(availability?.held ?? []),
      ...(availability?.sold ?? []),
      ...(availability?.blocked ?? []),
    ])
    const gone = selection.seatIds.filter((sid) => taken.has(sid))
    if (gone.length > 0) {
      rawDispatch({ type: 'dropSeats', seatIds: gone })
      setLost(gone)
    }
  }

  // Tell the buyer, then end the red flash after two pulses.
  useEffect(() => {
    if (lost.length === 0) return
    toast(
      lost.length > 1
        ? `${String(lost.length)} places viennent d’être prises`
        : 'Une place vient d’être prise',
      {
        description: 'Un autre spectateur a été plus rapide. Choisissez-en une autre.',
        tone: 'danger',
      },
    )
    const timer = setTimeout(() => {
      setLost([])
    }, 2600)
    return () => {
      clearTimeout(timer)
    }
  }, [lost])

  const closed = availability ? !availability.isOnSale : !performance.isOnSale

  return (
    <div className="mx-auto grid max-w-[96rem] gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[1fr_22rem] lg:gap-8">
      <div className="flex min-w-0 flex-col gap-5">
        <EventStrip performance={performance} event={event} />

        <div className="relative mx-auto aspect-[10/7] w-full max-w-[calc(74dvh*10/7)] overflow-hidden border-2 border-paper-100 bg-night-950">
          <CurtainReveal open />
          <SeatMap
            map={map}
            availability={availability}
            selection={selection}
            onToggleSeat={(seatId) => {
              dispatch({ type: 'toggleSeat', seatId })
            }}
            onStandingZone={(zoneId) => {
              setActiveStanding(zoneId)
              if (!selection.standing[zoneId]) {
                dispatch({ type: 'setStanding', zoneId, quantity: 1 })
              }
            }}
            lostSeatIds={lost}
          />
          {closed ? (
            <div className="absolute inset-x-0 bottom-0 z-20 bg-pop-red px-4 py-3 text-center font-display text-xl text-paper-50">
              {performance.status === 'CANCELLED'
                ? 'Représentation annulée'
                : 'La vente en ligne est fermée pour cette date'}
            </div>
          ) : null}
        </div>

        <ul aria-label="Légende" className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
          {LEGEND.map((item) => (
            <li key={item.label} className="flex items-center gap-2">
              <span
                aria-hidden
                className={`size-4 rounded-full border-2 border-ink-900 ${item.swatch}`}
              />
              {item.label}
            </li>
          ))}
          <li className="flex items-center gap-2">
            <span aria-hidden>♿</span> Accès fauteuil roulant
          </li>
        </ul>
      </div>

      <aside className="lg:sticky lg:top-24 lg:self-start">
        <SelectionSummary
          map={map}
          availability={availability}
          selection={selection}
          dispatch={dispatch}
          activeStandingZoneId={activeStanding}
          continueLabel={closed ? 'Vente fermée' : 'Continuer'}
          onContinue={() => {
            toast('Réservation bientôt disponible', {
              description: `${String(selectedCount(selection))} place(s) choisie(s) pour ${performanceId.slice(0, 8)}…`,
            })
          }}
        />
        <p className="mt-4 text-center text-sm">
          <Link
            to={`/spectacles/${performance.eventSlug}`}
            className="underline underline-offset-4 hover:text-pop-yellow"
          >
            Retour au spectacle
          </Link>
        </p>
      </aside>
    </div>
  )
}
