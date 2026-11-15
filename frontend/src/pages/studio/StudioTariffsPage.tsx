import { Plus } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Badge } from '../../design-system/primitives/Badge'
import { Button } from '../../design-system/primitives/Button'
import { Dialog } from '../../design-system/primitives/Dialog'
import { Input } from '../../design-system/primitives/Input'
import { Pill } from '../../design-system/primitives/Pill'
import { toast } from '../../design-system/toast-store'
import {
  useTariffMutations,
  useTariffs,
  type Tariff,
  type TariffForm,
} from '../../features/studio/api'
import { StudioPage, StudioTable } from '../../features/studio/StudioPage'
import { ApiError } from '../../lib/api'
import { usePageTitle } from '../../lib/usePageTitle'

const EMPTY: TariffForm = {
  code: '',
  label: '',
  description: null,
  channels: ['ONLINE', 'BOX_OFFICE'],
  allowedRoles: [],
  minQuantity: 1,
  maxQuantity: null,
  requiresProofAtEntry: false,
  isFree: false,
  sortOrder: 0,
}

function TariffDialog({ tariff, trigger }: { tariff?: Tariff; trigger: ReactNode }) {
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState<TariffForm>(EMPTY)
  const { save } = useTariffMutations()
  const set = <K extends keyof TariffForm>(k: K, v: TariffForm[K]) => {
    setForm((f) => ({ ...f, [k]: v }))
  }
  const toggle = (list: string[], value: string) =>
    list.includes(value) ? list.filter((v) => v !== value) : [...list, value]

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        setOpen(next)
        if (next) {
          save.reset()
          setForm(tariff ? { ...tariff } : EMPTY)
        }
      }}
      trigger={trigger}
      title={tariff ? `Tarif ${tariff.label}` : 'Nouveau tarif'}
      footer={
        <Button
          variant="gilt"
          loading={save.isPending}
          onClick={() => {
            save.mutate(
              { id: tariff?.id, form },
              {
                onSuccess: () => {
                  setOpen(false)
                },
              },
            )
          }}
        >
          Enregistrer
        </Button>
      }
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Input
          label="Code"
          disabled={tariff !== undefined}
          value={form.code}
          onChange={(e) => {
            set('code', e.target.value.toUpperCase())
          }}
        />
        <Input
          label="Libellé"
          value={form.label}
          onChange={(e) => {
            set('label', e.target.value)
          }}
        />
        <Input
          className="sm:col-span-2"
          label="Description"
          value={form.description ?? ''}
          onChange={(e) => {
            set('description', e.target.value || null)
          }}
        />
        <Input
          label="Minimum de places"
          type="number"
          min={1}
          value={form.minQuantity}
          onChange={(e) => {
            set('minQuantity', Number(e.target.value))
          }}
        />
        <Input
          label="Maximum"
          type="number"
          min={1}
          value={form.maxQuantity ?? ''}
          onChange={(e) => {
            set('maxQuantity', e.target.value === '' ? null : Number(e.target.value))
          }}
        />
        <Input
          label="Ordre d’affichage"
          type="number"
          min={0}
          value={form.sortOrder}
          onChange={(e) => {
            set('sortOrder', Number(e.target.value))
          }}
        />
      </div>
      <div className="mt-4 flex flex-wrap gap-2" role="group" aria-label="Options">
        <Pill
          selected={form.channels.includes('ONLINE')}
          onClick={() => {
            set('channels', toggle(form.channels, 'ONLINE'))
          }}
        >
          En ligne
        </Pill>
        <Pill
          selected={form.channels.includes('BOX_OFFICE')}
          onClick={() => {
            set('channels', toggle(form.channels, 'BOX_OFFICE'))
          }}
        >
          Guichet
        </Pill>
        <Pill
          selected={form.allowedRoles.includes('ROLE_BOX_OFFICE')}
          onClick={() => {
            set('allowedRoles', toggle(form.allowedRoles, 'ROLE_BOX_OFFICE'))
          }}
        >
          Réservé au personnel
        </Pill>
        <Pill
          selected={form.requiresProofAtEntry}
          onClick={() => {
            set('requiresProofAtEntry', !form.requiresProofAtEntry)
          }}
        >
          Justificatif
        </Pill>
        <Pill
          selected={form.isFree}
          onClick={() => {
            set('isFree', !form.isFree)
          }}
        >
          Gratuit (motif)
        </Pill>
      </div>
      {save.error instanceof ApiError ? (
        <p role="alert" className="mt-4 font-bold text-danger">
          {save.error.message}
        </p>
      ) : null}
    </Dialog>
  )
}

/** /studio/tarifs */
export function StudioTariffsPage() {
  usePageTitle('Tarifs · Studio')
  const tariffs = useTariffs()
  const { toggle } = useTariffMutations()

  return (
    <StudioPage
      kicker="qui paie quoi"
      title="Tarifs"
      actions={
        <TariffDialog
          trigger={
            <Button variant="gilt" leadingIcon={<Plus aria-hidden className="size-4" />}>
              Nouveau tarif
            </Button>
          }
        />
      }
    >
      <StudioTable label="Tarifs">
        <thead>
          <tr>
            <th>Tarif</th>
            <th>Canaux</th>
            <th>Règles</th>
            <th>État</th>
            <th />
          </tr>
        </thead>
        <tbody>
          {(tariffs.data ?? []).map((t) => (
            <tr key={t.id} className={t.active ? undefined : 'opacity-60'}>
              <td>
                <span className="font-bold">{t.label}</span>{' '}
                <span className="text-xs text-muted">{t.code}</span>
                {t.description ? (
                  <span className="block text-xs text-muted">{t.description}</span>
                ) : null}
              </td>
              <td>
                {t.channels.map((c) => (c === 'ONLINE' ? 'en ligne' : 'guichet')).join(' · ')}
              </td>
              <td className="space-x-1">
                {t.minQuantity > 1 || t.maxQuantity ? (
                  <Badge>
                    {t.minQuantity}–{t.maxQuantity ?? '∞'} places
                  </Badge>
                ) : null}
                {t.requiresProofAtEntry ? <Badge tone="gilt">justificatif</Badge> : null}
                {t.isFree ? <Badge tone="accent">gratuit</Badge> : null}
                {t.allowedRoles.length > 0 ? <Badge tone="ink">personnel</Badge> : null}
              </td>
              <td>{t.active ? <Badge tone="success">actif</Badge> : <Badge>inactif</Badge>}</td>
              <td className="space-x-1 text-right whitespace-nowrap">
                <TariffDialog
                  tariff={t}
                  trigger={
                    <Button size="sm" variant="ghost">
                      Modifier
                    </Button>
                  }
                />
                <Button
                  size="sm"
                  variant="secondary"
                  onClick={() => {
                    toggle.mutate(t, {
                      onError: (e) => {
                        toast(e instanceof ApiError ? e.message : 'Impossible', { tone: 'danger' })
                      },
                    })
                  }}
                >
                  {t.active ? 'Désactiver' : 'Activer'}
                </Button>
              </td>
            </tr>
          ))}
        </tbody>
      </StudioTable>
    </StudioPage>
  )
}
