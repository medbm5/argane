import { Plus, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Button } from '../../design-system/primitives/Button'
import { Input } from '../../design-system/primitives/Input'
import { Select } from '../../design-system/primitives/Select'
import { toast } from '../../design-system/toast-store'
import { ApiError } from '../../lib/api'
import { useLayoutMutations, useLayoutPreview, type LayoutForm, type ZoneForm } from './api'
import { PlanPreview } from './PlanPreview'

const COLORS = [
  { value: 'bordeaux-700', label: 'Rouge', swatch: 'var(--pop-red)' },
  { value: 'gilt-400', label: 'Jaune', swatch: 'var(--pop-yellow)' },
  { value: 'sepia-600', label: 'Orange', swatch: 'var(--pop-orange)' },
  { value: 'olive-600', label: 'Turquoise', swatch: 'var(--pop-teal)' },
]

const SEATED: ZoneForm = {
  code: 'ORCHESTRE',
  name: 'Orchestre',
  kind: 'SEATED',
  x: 150,
  y: 110,
  width: 700,
  height: 330,
  colorToken: 'bordeaux-700',
  rowFrom: 'A',
  rowTo: 'L',
  skipConfusableRows: true,
  seatsPerRow: 20,
  aisleAfter: 10,
  curve: 12,
  blockedSeats: [],
}

const STANDING: ZoneForm = {
  code: 'FOSSE',
  name: 'Fosse',
  kind: 'STANDING',
  x: 150,
  y: 470,
  width: 700,
  height: 200,
  colorToken: 'olive-600',
  capacity: 300,
}

const csv = (list: string[] | undefined) => (list ?? []).join(', ')
const fromCsv = (text: string) =>
  text
    .split(',')
    .map((s) => s.trim().toUpperCase())
    .filter(Boolean)

/** Generator form with a debounced live preview; saving creates a new configuration. */
export function LayoutGenerator({ onSaved }: { onSaved: () => void }) {
  const [form, setForm] = useState<LayoutForm>({ name: 'Nouvelle configuration', zones: [SEATED] })
  const [debounced, setDebounced] = useState<LayoutForm>(form)
  const preview = useLayoutPreview(debounced)
  const { create } = useLayoutMutations()

  useEffect(() => {
    const timer = setTimeout(() => {
      setDebounced(form)
    }, 400)
    return () => {
      clearTimeout(timer)
    }
  }, [form])

  function patch(index: number, change: Partial<ZoneForm>) {
    setForm((f) => ({
      ...f,
      zones: f.zones.map((z, i) => (i === index ? { ...z, ...change } : z)),
    }))
  }
  const num = (value: string) => (value === '' ? 0 : Number(value))
  const error = preview.error instanceof ApiError ? preview.error.message : null

  return (
    <div className="grid gap-6 xl:grid-cols-[1fr_minmax(0,1.1fr)]">
      <div className="space-y-4">
        <Input
          label="Nom de la configuration"
          value={form.name}
          onChange={(e) => {
            setForm((f) => ({ ...f, name: e.target.value }))
          }}
        />
        {form.zones.map((zone, i) => (
          <fieldset key={i} className="brutal space-y-3 bg-surface p-4">
            <legend className="px-1 font-display text-xl">
              {zone.kind === 'STANDING' ? 'Zone debout' : 'Zone assise'}
            </legend>
            <div className="grid gap-3 sm:grid-cols-3">
              <Input
                label="Code"
                value={zone.code}
                onChange={(e) => {
                  patch(i, { code: e.target.value.toUpperCase() })
                }}
              />
              <Input
                label="Nom"
                value={zone.name}
                onChange={(e) => {
                  patch(i, { name: e.target.value })
                }}
              />
              <Select
                label="Couleur"
                value={zone.colorToken}
                onValueChange={(v) => {
                  patch(i, { colorToken: v })
                }}
                options={COLORS}
              />
              <Input
                label="X"
                type="number"
                value={zone.x}
                onChange={(e) => {
                  patch(i, { x: num(e.target.value) })
                }}
              />
              <Input
                label="Y"
                type="number"
                value={zone.y}
                onChange={(e) => {
                  patch(i, { y: num(e.target.value) })
                }}
              />
              <Input
                label="Largeur"
                type="number"
                value={zone.width}
                onChange={(e) => {
                  patch(i, { width: num(e.target.value) })
                }}
              />
              <Input
                label="Hauteur"
                type="number"
                value={zone.height}
                onChange={(e) => {
                  patch(i, { height: num(e.target.value) })
                }}
              />
              {zone.kind === 'STANDING' ? (
                <Input
                  label="Capacité"
                  type="number"
                  value={zone.capacity ?? 0}
                  onChange={(e) => {
                    patch(i, { capacity: num(e.target.value) })
                  }}
                />
              ) : (
                <>
                  <Input
                    label="Rangs de"
                    value={zone.rowFrom ?? ''}
                    onChange={(e) => {
                      patch(i, { rowFrom: e.target.value.toUpperCase() })
                    }}
                  />
                  <Input
                    label="à"
                    value={zone.rowTo ?? ''}
                    onChange={(e) => {
                      patch(i, { rowTo: e.target.value.toUpperCase() })
                    }}
                  />
                  <Input
                    label="Places par rang"
                    type="number"
                    value={zone.seatsPerRow ?? 1}
                    onChange={(e) => {
                      patch(i, { seatsPerRow: num(e.target.value) })
                    }}
                  />
                  <Input
                    label="Allée après la place"
                    type="number"
                    value={zone.aisleAfter ?? ''}
                    onChange={(e) => {
                      patch(i, { aisleAfter: e.target.value === '' ? null : num(e.target.value) })
                    }}
                  />
                  <Input
                    label="Courbure"
                    type="number"
                    min={0}
                    max={40}
                    value={zone.curve ?? 0}
                    onChange={(e) => {
                      patch(i, { curve: num(e.target.value) })
                    }}
                  />
                  <Input
                    label="Places bloquées"
                    hint="ex. R-10, R-11"
                    value={csv(zone.blockedSeats)}
                    onChange={(e) => {
                      patch(i, { blockedSeats: fromCsv(e.target.value) })
                    }}
                  />
                </>
              )}
            </div>
            {form.zones.length > 1 ? (
              <Button
                variant="ghost"
                size="sm"
                leadingIcon={<Trash2 aria-hidden className="size-4" />}
                onClick={() => {
                  setForm((f) => ({ ...f, zones: f.zones.filter((_, j) => j !== i) }))
                }}
              >
                Retirer la zone
              </Button>
            ) : null}
          </fieldset>
        ))}
        <div className="flex flex-wrap gap-2">
          <Button
            variant="secondary"
            size="sm"
            leadingIcon={<Plus aria-hidden className="size-4" />}
            onClick={() => {
              setForm((f) => ({
                ...f,
                zones: [
                  ...f.zones,
                  {
                    ...SEATED,
                    code: `ZONE${String(f.zones.length + 1)}`,
                    name: 'Balcon',
                    y: 470,
                    height: 200,
                    rowFrom: 'M',
                    rowTo: 'P',
                    curve: 0,
                  },
                ],
              }))
            }}
          >
            Zone assise
          </Button>
          <Button
            variant="secondary"
            size="sm"
            leadingIcon={<Plus aria-hidden className="size-4" />}
            onClick={() => {
              setForm((f) => ({ ...f, zones: [...f.zones, STANDING] }))
            }}
          >
            Zone debout
          </Button>
        </div>
      </div>

      <div className="space-y-3 xl:sticky xl:top-6 xl:self-start">
        <div className="brutal overflow-hidden">
          {preview.data ? (
            <PlanPreview layout={preview.data} label={`Aperçu, ${preview.data.name}`} />
          ) : (
            <div className="aspect-[10/7] animate-pulse bg-night-900" />
          )}
        </div>
        <p aria-live="polite" className={error ? 'font-bold text-danger' : 'text-sm'}>
          {error ??
            (preview.data
              ? `${String(preview.data.capacity)} places · ${String(preview.data.zones.length)} zone(s)`
              : 'Aperçu…')}
        </p>
        <Button
          variant="gilt"
          size="lg"
          block
          disabled={error !== null}
          loading={create.isPending}
          onClick={() => {
            create.mutate(form, {
              onSuccess: (layout) => {
                toast(`Configuration « ${layout.name} » créée`, { tone: 'success' })
                onSaved()
              },
              onError: (e) => {
                toast(e instanceof ApiError ? e.message : 'Création impossible', { tone: 'danger' })
              },
            })
          }}
        >
          Enregistrer la configuration
        </Button>
      </div>
    </div>
  )
}
