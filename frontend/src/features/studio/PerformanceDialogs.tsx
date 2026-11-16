import { DateTimePicker } from '../../design-system/primitives/DatePicker'
import { useState, type ReactNode } from 'react'
import { Button } from '../../design-system/primitives/Button'
import { Dialog } from '../../design-system/primitives/Dialog'
import { Input } from '../../design-system/primitives/Input'
import { Select } from '../../design-system/primitives/Select'
import { toast } from '../../design-system/toast-store'
import { ApiError } from '../../lib/api'
import { formatLongDate, formatTime, toVenueLocalInput } from '../../lib/format'
import {
  useCancelPerformance,
  useLayouts,
  useSchedulePerformance,
  type PerformanceForm,
  type StudioPerformance,
} from './api'

/** Schedule a new date, or reschedule one (layout locked once published). */
export function ScheduleDialog({
  eventId,
  performance,
  trigger,
}: {
  eventId: string
  performance?: StudioPerformance
  trigger: ReactNode
}) {
  const [open, setOpen] = useState(false)
  const layouts = useLayouts()
  const schedule = useSchedulePerformance(eventId)
  const [form, setForm] = useState<PerformanceForm>({
    startsAt: '',
    salesOpenAt: '',
    salesCloseAt: null,
    doorsMinutesBefore: 30,
    layoutId: '',
  })
  const set = <K extends keyof PerformanceForm>(key: K, value: PerformanceForm[K]) => {
    setForm((f) => ({ ...f, [key]: value }))
  }
  const options = (layouts.data ?? [])
    .filter((l) => !l.archived || l.id === performance?.layoutId)
    .map((l) => ({ value: l.id ?? '', label: l.name, description: `${String(l.capacity)} places` }))

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        setOpen(next)
        if (next) {
          schedule.reset()
          setForm(
            performance
              ? {
                  startsAt: toVenueLocalInput(performance.startsAt),
                  salesOpenAt: toVenueLocalInput(performance.salesOpenAt),
                  salesCloseAt: toVenueLocalInput(performance.salesCloseAt),
                  doorsMinutesBefore: Math.round(
                    (new Date(performance.startsAt).getTime() -
                      new Date(performance.doorsAt).getTime()) /
                      60_000,
                  ),
                  layoutId: performance.layoutId,
                }
              : {
                  startsAt: '',
                  salesOpenAt: toVenueLocalInput(new Date().toISOString()),
                  salesCloseAt: null,
                  doorsMinutesBefore: 30,
                  layoutId: layouts.data?.find((l) => l.isDefault && !l.archived)?.id ?? '',
                },
          )
        }
      }}
      trigger={trigger}
      title={performance ? 'Modifier la date' : 'Nouvelle date'}
      description="Heures locales du théâtre (Agadir)."
      footer={
        <Button
          variant="gilt"
          loading={schedule.isPending}
          onClick={() => {
            schedule.mutate(
              { id: performance?.id, form: { ...form, salesCloseAt: form.salesCloseAt || null } },
              {
                onSuccess: () => {
                  setOpen(false)
                },
              },
            )
          }}
        >
          {performance ? 'Enregistrer' : 'Programmer'}
        </Button>
      }
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <DateTimePicker
          label="Début"
          value={form.startsAt}
          onChange={(v) => {
            set('startsAt', v)
          }}
        />
        <Input
          label="Ouverture des portes (min avant)"
          type="number"
          min={0}
          max={180}
          value={form.doorsMinutesBefore}
          onChange={(e) => {
            set('doorsMinutesBefore', Number(e.target.value))
          }}
        />
        <DateTimePicker
          label="Ouverture des ventes"
          defaultTime="10:00"
          value={form.salesOpenAt}
          onChange={(v) => {
            set('salesOpenAt', v)
          }}
        />
        <DateTimePicker
          label="Fin des ventes en ligne"
          hint="Vide : jusqu’au lever de rideau"
          clearable
          value={form.salesCloseAt ?? ''}
          onChange={(v) => {
            set('salesCloseAt', v || null)
          }}
        />
        <Select
          className="sm:col-span-2"
          label="Configuration de salle"
          value={form.layoutId || undefined}
          onValueChange={(value) => {
            set('layoutId', value)
          }}
          disabled={performance?.published}
          options={options}
          hint={
            performance?.published ? 'Publiée : la configuration ne peut plus changer.' : undefined
          }
        />
      </div>
      {schedule.error instanceof ApiError ? (
        <p role="alert" className="mt-4 font-bold text-danger">
          {schedule.error.message}
        </p>
      ) : null}
    </Dialog>
  )
}

/** Cancelling a date refunds everyone: the reason is shown to spectators. */
export function CancelPerformanceDialog({
  eventId,
  performance,
  trigger,
}: {
  eventId: string
  performance: StudioPerformance
  trigger: ReactNode
}) {
  const [open, setOpen] = useState(false)
  const [reason, setReason] = useState('')
  const cancel = useCancelPerformance(eventId)

  return (
    <Dialog
      open={open}
      onOpenChange={setOpen}
      trigger={trigger}
      title="Annuler cette représentation ?"
      description={`${formatLongDate(performance.startsAt)} à ${formatTime(performance.startsAt)} · ${String(performance.sold)} billet(s) vendus. Les ventes s’arrêtent, tous les spectateurs sont remboursés (carte automatiquement, guichet à rendre). Définitif.`}
      footer={
        <Button
          variant="danger"
          disabled={reason.trim() === ''}
          loading={cancel.isPending}
          onClick={() => {
            cancel.mutate(
              { id: performance.id, reason },
              {
                onSuccess: () => {
                  setOpen(false)
                  toast('Représentation annulée, remboursements en cours', { tone: 'success' })
                },
                onError: (e) => {
                  toast(e instanceof ApiError ? e.message : 'Annulation impossible', {
                    tone: 'danger',
                  })
                },
              },
            )
          }}
        >
          Annuler et rembourser
        </Button>
      }
    >
      <Input
        label="Raison (communiquée aux spectateurs)"
        value={reason}
        onChange={(e) => {
          setReason(e.target.value)
        }}
      />
    </Dialog>
  )
}
