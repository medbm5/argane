import { Tabs } from '../../design-system/primitives/Tabs'
import { useLayouts } from '../../features/studio/api'
import { HallEditor } from '../../features/studio/HallEditor'
import { StudioPage } from '../../features/studio/StudioPage'
import { usePageTitle } from '../../lib/usePageTitle'

/** /studio/salle — the hall's plans (Théâtre, Concert): switch seats on and off. */
export function StudioHallPage() {
  usePageTitle('Salle · Studio')
  const layouts = useLayouts()
  const plans = (layouts.data ?? []).filter(
    (l): l is typeof l & { id: string } => !l.archived && typeof l.id === 'string',
  )

  return (
    <StudioPage kicker="la salle" title="Plan de salle">
      <p className="mb-6 max-w-[65ch]">
        Une salle, deux dispositions. Choisissez la disposition, touchez les places à retirer (ou
        une lettre pour toute la rangée), puis désactivez-les. Chaque date utilise la disposition
        choisie dans son spectacle.
      </p>
      {layouts.isPending ? (
        <p className="text-muted">Chargement…</p>
      ) : (
        <Tabs
          label="Dispositions de la salle"
          items={plans.map((plan) => ({
            value: plan.id,
            label: `${plan.name} · ${String(plan.capacity)} places`,
            content: (
              <div className="pt-6">
                <HallEditor layoutId={plan.id} name={plan.name} />
              </div>
            ),
          }))}
        />
      )}
    </StudioPage>
  )
}
