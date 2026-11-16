import { useState, type ReactNode } from 'react'
import { useNavigate } from 'react-router'
import { Button } from '../../design-system/primitives/Button'
import { Dialog } from '../../design-system/primitives/Dialog'
import { fieldClasses } from '../../design-system/primitives/fieldStyles'
import { Input } from '../../design-system/primitives/Input'
import { Select } from '../../design-system/primitives/Select'
import { ApiError } from '../../lib/api'
import { CATEGORIES } from '../catalog/api'
import { useSaveEvent, type EventForm, type StudioEvent } from './api'
import { PosterField } from './PosterField'

const EMPTY: EventForm = {
  title: '',
  subtitle: null,
  category: 'THEATRE',
  description: '',
  durationMinutes: 90,
  ageRating: null,
  posterPath: null,
}

/** Create or edit an event (title, category, duration, age, poster path, text). */
export function EventFormDialog({ event, trigger }: { event?: StudioEvent; trigger: ReactNode }) {
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState<EventForm>(EMPTY)
  const save = useSaveEvent(event?.id)
  const navigate = useNavigate()
  const set = <K extends keyof EventForm>(key: K, value: EventForm[K]) => {
    setForm((f) => ({ ...f, [key]: value }))
  }
  const error = save.error instanceof ApiError ? save.error : null
  const fieldError = (name: string) =>
    error?.problem.violations && Array.isArray(error.problem.violations)
      ? (error.problem.violations as { propertyPath: string; title: string }[]).find(
          (v) => v.propertyPath === name,
        )?.title
      : undefined

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        setOpen(next)
        if (next) {
          save.reset()
          setForm(
            event
              ? {
                  title: event.title,
                  subtitle: event.subtitle ?? null,
                  category: event.category,
                  description: event.description,
                  durationMinutes: event.durationMinutes,
                  ageRating: event.ageRating ?? null,
                  posterPath: event.posterPath ?? null,
                }
              : EMPTY,
          )
        }
      }}
      trigger={trigger}
      title={event ? 'Modifier le spectacle' : 'Nouveau spectacle'}
      className="w-[min(40rem,calc(100vw-2rem))]"
      footer={
        <Button
          variant="gilt"
          loading={save.isPending}
          onClick={() => {
            save.mutate(form, {
              onSuccess: (saved) => {
                setOpen(false)
                if (!event) void navigate(`/studio/spectacles/${saved.id}`)
              },
            })
          }}
        >
          {event ? 'Enregistrer' : 'Créer le brouillon'}
        </Button>
      }
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Input
          label="Titre"
          className="sm:col-span-2"
          value={form.title}
          error={fieldError('title')}
          onChange={(e) => {
            set('title', e.target.value)
          }}
        />
        <Input
          label="Sous-titre"
          className="sm:col-span-2"
          value={form.subtitle ?? ''}
          onChange={(e) => {
            set('subtitle', e.target.value || null)
          }}
        />
        <Select
          label="Catégorie"
          value={form.category}
          onValueChange={(value) => {
            set('category', value)
          }}
          options={CATEGORIES.map((c) => ({ value: c.value, label: c.label, swatch: c.color }))}
        />
        <Input
          label="Durée (minutes)"
          type="number"
          min={10}
          value={form.durationMinutes}
          error={fieldError('durationMinutes')}
          onChange={(e) => {
            set('durationMinutes', Number(e.target.value))
          }}
        />
        <Input
          label="Âge minimum"
          type="number"
          min={0}
          max={21}
          value={form.ageRating ?? ''}
          onChange={(e) => {
            set('ageRating', e.target.value === '' ? null : Number(e.target.value))
          }}
        />
        <PosterField
          value={form.posterPath ?? null}
          error={fieldError('posterPath')}
          onChange={(path) => {
            set('posterPath', path)
          }}
        />
        <label className="flex flex-col gap-1.5 sm:col-span-2">
          <span className="text-xs font-bold tracking-[0.12em] uppercase">Présentation</span>
          <textarea
            rows={5}
            className={`${fieldClasses} h-auto py-2`}
            value={form.description}
            onChange={(e) => {
              set('description', e.target.value)
            }}
          />
        </label>
      </div>
      {error && !error.problem.violations ? (
        <p role="alert" className="mt-4 font-bold text-danger">
          {error.message}
        </p>
      ) : null}
    </Dialog>
  )
}
