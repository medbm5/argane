import { ArrowLeft } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useParams } from 'react-router'
import { Button } from '../../design-system/primitives/Button'
import { toast } from '../../design-system/toast-store'
import { usePriceGrid, usePriceGridMutations, type PriceGrid } from '../../features/studio/api'
import { StudioPage } from '../../features/studio/StudioPage'
import { parseAmount } from '../../features/studio/prices'
import { ApiError } from '../../lib/api'

const key = (zoneId: string, tariffId: string) => `${zoneId}|${tariffId}`

/** /studio/representations/:id/prix — zones as rows, tariffs as columns. */
export function StudioPricesPage() {
  const { id = '' } = useParams()
  const grid = usePriceGrid(id)
  if (!grid.data) return <p className="font-display text-3xl">Chargement…</p>
  return <GridEditor key={grid.dataUpdatedAt} performanceId={id} grid={grid.data} />
}

function GridEditor({ performanceId, grid }: { performanceId: string; grid: PriceGrid }) {
  const navigate = useNavigate()
  const { save, applyToEvent } = usePriceGridMutations(performanceId)
  const [values, setValues] = useState<Record<string, string>>(() =>
    Object.fromEntries(
      grid.cells.map((c) => [
        key(c.zoneId, c.tariffId),
        (c.amountCents / 100).toString().replace('.', ','),
      ]),
    ),
  )
  const tariffs = grid.tariffs.filter(
    (t) => t.active || grid.cells.some((c) => c.tariffId === t.id),
  )
  const invalid = Object.values(values).some((v) => parseAmount(v) === 'invalid')

  function cells() {
    return grid.zones.flatMap((zone) =>
      tariffs.map((tariff) => {
        const amount = parseAmount(values[key(zone.id, tariff.id)] ?? '')
        return {
          zoneId: zone.id,
          tariffId: tariff.id,
          amountCents: amount === 'invalid' ? null : amount,
        }
      }),
    )
  }

  return (
    <StudioPage
      kicker="grille tarifaire"
      title="Prix de la date"
      actions={
        <>
          <Button
            variant="ghost"
            leadingIcon={<ArrowLeft aria-hidden className="size-4" />}
            onClick={() => void navigate(-1)}
          >
            Retour
          </Button>
          <Button
            variant="secondary"
            loading={applyToEvent.isPending}
            onClick={() => {
              applyToEvent.mutate(undefined, {
                onSuccess: ({ updated }) => {
                  toast(`Grille copiée sur ${String(updated)} autre(s) date(s)`, {
                    tone: 'success',
                  })
                },
              })
            }}
          >
            Appliquer à toutes les dates
          </Button>
          <Button
            variant="gilt"
            disabled={invalid}
            loading={save.isPending}
            onClick={() => {
              save.mutate(cells(), {
                onSuccess: () => {
                  toast('Prix enregistrés', { tone: 'success' })
                },
                onError: (e) => {
                  toast(e instanceof ApiError ? e.message : 'Enregistrement impossible', {
                    tone: 'danger',
                  })
                },
              })
            }}
          >
            Enregistrer
          </Button>
        </>
      }
    >
      <p className="mb-4 text-sm text-muted">
        Montants en {grid.currency}. Laissez vide pour ne pas vendre ce tarif dans la zone. Les
        billets déjà vendus gardent leur prix.
      </p>
      <div className="brutal overflow-x-auto bg-surface">
        <table className="w-full text-sm" aria-label="Grille de prix">
          <thead className="bg-ink-900 text-paper-100">
            <tr>
              <th className="px-3 py-2 text-left text-xs tracking-[0.12em] uppercase">Zone</th>
              {tariffs.map((t) => (
                <th key={t.id} className="px-3 py-2 text-left text-xs tracking-[0.12em] uppercase">
                  {t.label}
                  {!t.active ? (
                    <span className="block text-[0.65rem] text-pop-yellow">inactif</span>
                  ) : null}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {grid.zones.map((zone) => (
              <tr key={zone.id} className="border-t border-fg/10">
                <th scope="row" className="px-3 py-2 text-left font-display text-lg">
                  {zone.name}
                  {zone.kind === 'STANDING' ? (
                    <span className="block text-xs text-muted">debout</span>
                  ) : null}
                </th>
                {tariffs.map((t) => {
                  const k = key(zone.id, t.id)
                  const bad = parseAmount(values[k] ?? '') === 'invalid'
                  return (
                    <td key={t.id} className="px-2 py-1.5">
                      <input
                        aria-label={`${zone.name}, ${t.label}`}
                        inputMode="decimal"
                        value={values[k] ?? ''}
                        aria-invalid={bad || undefined}
                        onChange={(e) => {
                          setValues((v) => ({ ...v, [k]: e.target.value }))
                        }}
                        placeholder="—"
                        className="tabular h-10 w-28 border-2 border-fg bg-paper-50 px-2 text-right focus-visible:shadow-[3px_3px_0_var(--pop-blue)] focus-visible:outline-none aria-invalid:border-danger"
                      />
                    </td>
                  )
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </StudioPage>
  )
}
