import { useState } from 'react'
import { Tabs } from '../design-system/primitives/Tabs'
import { useCounterPerformances } from '../features/boxoffice/api'
import { CounterPerformanceList } from '../features/boxoffice/CounterPerformanceList'
import { CounterRefunds } from '../features/boxoffice/CounterRefunds'
import { CounterSale } from '../features/boxoffice/CounterSale'
import { usePageTitle } from '../lib/usePageTitle'

/** /guichet — the box office point of sale. */
export function GuichetPage() {
  usePageTitle('Guichet')
  const performances = useCounterPerformances()
  const [selected, setSelected] = useState<string | null>(null)
  const current = performances.data?.find((p) => p.id === selected)

  return (
    <div className="px-4 py-6 sm:px-6">
      <Tabs
        label="Guichet"
        defaultValue="vente"
        items={[
          {
            value: 'vente',
            label: 'Vente',
            content: (
              <div className="mt-4 grid gap-6 lg:grid-cols-[18rem_1fr]">
                <aside className="brutal max-h-[78dvh] self-start overflow-y-auto bg-surface">
                  {performances.isPending ? (
                    <p className="p-4">Chargement…</p>
                  ) : (
                    <CounterPerformanceList
                      performances={performances.data ?? []}
                      selectedId={selected}
                      onSelect={setSelected}
                    />
                  )}
                </aside>
                <section aria-label="Vente en cours" className="min-w-0">
                  {current ? (
                    <>
                      <h1 className="mb-4 font-display text-4xl leading-none">
                        {current.eventTitle}
                      </h1>
                      <CounterSale key={current.id} performanceId={current.id} />
                    </>
                  ) : (
                    <p className="brutal bg-surface p-10 text-center font-display text-3xl">
                      Choisissez une représentation
                    </p>
                  )}
                </section>
              </div>
            ),
          },
          {
            value: 'remboursements',
            label: 'Remboursements',
            content: (
              <div className="mt-4">
                <CounterRefunds />
              </div>
            ),
          },
        ]}
      />
    </div>
  )
}
