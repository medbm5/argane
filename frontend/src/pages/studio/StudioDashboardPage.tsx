import { AlertTriangle } from 'lucide-react'
import type { ReactNode } from 'react'
import { useState } from 'react'
import { Link } from 'react-router'
import { DotSparkline } from '../../design-system/components/DotSparkline'
import { HalfGauge } from '../../design-system/components/HalfGauge'
import { KpiTile } from '../../design-system/components/KpiTile'
import { Input } from '../../design-system/primitives/Input'
import { Pill } from '../../design-system/primitives/Pill'
import { Select } from '../../design-system/primitives/Select'
import {
  rangeOfDays,
  useAtRisk,
  useBookingCurve,
  useFill,
  useHeatmap,
  useOverview,
  useRevenue,
  type Range,
} from '../../features/analytics/api'
import { CurveChart, DailyBars, RankBars, SeatHeat } from '../../features/analytics/charts'
import { MasksArt } from '../../features/analytics/MasksArt'
import { useLayouts } from '../../features/studio/api'
import { formatMoney, formatShortDate, formatTime } from '../../lib/format'
import { usePageTitle } from '../../lib/usePageTitle'

const PRESETS = [7, 30, 90] as const
const CURRENCY = 'MAD'
const percent = (rate: number) => `${(rate * 100).toFixed(rate < 0.1 ? 1 : 0)} %`

function Panel({
  title,
  children,
  className,
}: {
  title: string
  children: ReactNode
  className?: string
}) {
  return (
    <section aria-label={title} className={`brutal bg-surface p-5 ${className ?? ''}`}>
      <h2 className="mb-4 font-display text-2xl">{title}</h2>
      {children}
    </section>
  )
}

/** /studio — the BI dashboard. */
export function StudioDashboardPage() {
  usePageTitle('Studio')
  const [now] = useState(() => new Date())
  const [preset, setPreset] = useState<number | null>(30)
  const [range, setRange] = useState<Range>(() => rangeOfDays(30, now))
  const overview = useOverview(range)
  const revenue = useRevenue(range)
  const fill = useFill()
  const atRisk = useAtRisk()
  const layouts = useLayouts()
  const upcoming = (fill.data ?? []).filter((p) => new Date(p.startsAt) > now)
  const [curveFor, setCurveFor] = useState<string | null>(null)
  const curve = useBookingCurve(curveFor ?? upcoming[0]?.performanceId ?? null)
  const [heatLayout, setHeatLayout] = useState<string | null>(null)
  const layoutId = heatLayout ?? layouts.data?.find((l) => l.isDefault)?.id ?? null
  const heat = useHeatmap(layoutId)

  const k = overview.data?.kpis
  const daily = overview.data?.daily ?? []
  const sold = upcoming.reduce((n, p) => n + p.sold, 0)
  const capacity = upcoming.reduce((n, p) => n + p.capacity, 0)
  const zones = new Map<string, { sold: number; capacity: number }>()
  for (const p of upcoming)
    for (const z of p.zones) {
      const prev = zones.get(z.name) ?? { sold: 0, capacity: 0 }
      zones.set(z.name, { sold: prev.sold + z.sold, capacity: prev.capacity + z.capacity })
    }

  return (
    <div className="mx-auto max-w-7xl">
      <header className="mb-8 grid items-center gap-6 border-b-2 border-ink-900 pb-6 lg:grid-cols-[auto_1fr_auto]">
        <MasksArt className="hidden h-24 w-auto lg:block" />
        <div>
          <p className="-rotate-1 font-marker text-lg text-accent">la saison en chiffres</p>
          <h1 className="font-display text-5xl leading-none">Tableau de bord</h1>
        </div>
        <div className="flex flex-wrap items-end gap-2">
          {PRESETS.map((days) => (
            <Pill
              key={days}
              selected={preset === days}
              onClick={() => {
                setPreset(days)
                setRange(rangeOfDays(days, now))
              }}
            >
              {days} jours
            </Pill>
          ))}
          <Input
            label="Du"
            type="date"
            value={range.from}
            className="w-40"
            onChange={(e) => {
              setPreset(null)
              setRange((r) => ({ ...r, from: e.target.value }))
            }}
          />
          <Input
            label="Au"
            type="date"
            value={range.to}
            className="w-40"
            onChange={(e) => {
              setPreset(null)
              setRange((r) => ({ ...r, to: e.target.value }))
            }}
          />
        </div>
      </header>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <KpiTile
          label="Recettes"
          value={k ? formatMoney(k.revenueCents.value, CURRENCY) : '—'}
          delta={k?.revenueCents.delta}
          hint="vs période précédente"
          chart={
            <DotSparkline values={daily.map((d) => d.revenueCents)} label="Recettes par jour" />
          }
        />
        <KpiTile
          label="Billets vendus"
          value={k ? String(k.tickets.value) : '—'}
          delta={k?.tickets.delta}
          hint={k ? `${String(k.orders.value)} commandes` : undefined}
          chart={<DotSparkline values={daily.map((d) => d.tickets)} label="Billets par jour" />}
        />
        <KpiTile
          label="Panier moyen"
          value={k ? formatMoney(k.averageBasketCents.value, CURRENCY) : '—'}
          delta={k?.averageBasketCents.delta}
          hint={k ? `${percent(k.onlineShare.value)} en ligne` : undefined}
        />
        <KpiTile
          label="Remboursements"
          value={k ? String(k.refundedOrders.value) : '—'}
          delta={k?.refundedOrders.delta}
          invertDelta
          hint={k ? `remplissage des dates : ${percent(k.fillRate.value)}` : undefined}
        />
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_1.4fr]">
        <Panel title="Remplissage à venir">
          <HalfGauge
            segments={[
              { label: 'Vendu', value: sold, className: 'stroke-pop-red' },
              { label: 'Libre', value: Math.max(0, capacity - sold), className: 'stroke-pop-teal' },
            ]}
            total={capacity}
            centerLabel={capacity > 0 ? percent(sold / capacity) : '—'}
            centerCaption={`${String(sold)} / ${String(capacity)} places`}
          />
          <ul className="mt-4 space-y-2 text-sm">
            {[...zones.entries()].map(([name, z]) => (
              <li key={name} className="flex items-center gap-3">
                <span className="w-28 font-bold">{name}</span>
                <span aria-hidden className="h-3 flex-1 border-2 border-ink-900 bg-paper-50">
                  <span
                    className="block h-full bg-pop-red"
                    style={{ width: `${String(z.capacity ? (z.sold / z.capacity) * 100 : 0)}%` }}
                  />
                </span>
                <span className="tabular w-24 text-right">
                  {z.sold} / {z.capacity}
                </span>
              </li>
            ))}
          </ul>
        </Panel>
        <Panel title="Dates à surveiller">
          {(atRisk.data ?? []).length === 0 ? (
            <p className="text-muted">Aucune date ne se vend moins vite que d’habitude.</p>
          ) : (
            <ul className="space-y-3">
              {(atRisk.data ?? []).map((r) => (
                <li
                  key={r.performanceId}
                  className="flex items-start gap-3 border-2 border-ink-900 bg-pop-yellow/30 p-3"
                >
                  <AlertTriangle aria-hidden className="mt-0.5 size-5 shrink-0 text-danger" />
                  <div className="flex-1">
                    <p className="font-bold">
                      {r.eventTitle} · {formatShortDate(r.startsAt)} {formatTime(r.startsAt)}
                    </p>
                    <p className="text-sm">
                      J-{r.daysLeft} : {percent(r.rate)} vendu ({r.sold} / {r.capacity})
                      {r.expected > 0 ? `, ${percent(r.expected)} attendu à ce stade` : ''}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </Panel>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <Panel title="Ventes par jour">
          <DailyBars series={daily} currency={CURRENCY} />
        </Panel>
        <Panel title="Recettes par tarif et canal">
          <RankBars
            currency={CURRENCY}
            rows={(revenue.data?.byTariff ?? []).map((t) => ({
              label: t.tariff,
              value: t.revenueCents,
              detail: `${String(t.tickets)} billets`,
            }))}
          />
          <div className="mt-6 border-t-2 border-fg/10 pt-4">
            <RankBars
              currency={CURRENCY}
              rows={(revenue.data?.byChannel ?? []).map((c) => ({
                label: c.channel === 'ONLINE' ? 'En ligne' : 'Guichet',
                value: c.revenueCents,
                detail: `${String(c.tickets)} billets`,
              }))}
            />
          </div>
        </Panel>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <Panel title="Courbe de réservation">
          <Select
            label="Date"
            value={curveFor ?? upcoming[0]?.performanceId}
            onValueChange={setCurveFor}
            options={upcoming.map((p) => ({
              value: p.performanceId,
              label: `${p.eventTitle} · ${formatShortDate(p.startsAt)}`,
            }))}
            className="mb-4"
          />
          <CurveChart
            reference={curve.data?.reference ?? []}
            performance={curve.data?.performance ?? []}
          />
        </Panel>
        <Panel title="Fauteuils les plus demandés">
          <Select
            label="Configuration"
            value={layoutId ?? undefined}
            onValueChange={setHeatLayout}
            options={(layouts.data ?? []).map((l) => ({ value: l.id ?? '', label: l.name }))}
            className="mb-4"
          />
          {heat.data ? (
            <>
              <SeatHeat seats={heat.data.seats} />
              <p className="mt-2 text-xs text-muted">
                Part des {heat.data.performances} représentations où chaque fauteuil a été vendu
                (plus rouge = plus demandé).{' '}
                <Link to="/studio/ventes" className="underline">
                  Détail des ventes
                </Link>
              </p>
            </>
          ) : null}
        </Panel>
      </div>
    </div>
  )
}
