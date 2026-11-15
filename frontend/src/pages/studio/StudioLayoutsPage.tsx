import { Copy, Lock, Plus } from 'lucide-react'
import { useState } from 'react'
import { Badge } from '../../design-system/primitives/Badge'
import { Button } from '../../design-system/primitives/Button'
import { toast } from '../../design-system/toast-store'
import { useLayoutMutations, useLayouts } from '../../features/studio/api'
import { LayoutGenerator } from '../../features/studio/LayoutGenerator'
import { StudioPage } from '../../features/studio/StudioPage'
import { usePageTitle } from '../../lib/usePageTitle'

/** /studio/configurations — hall layouts and the generator. */
export function StudioLayoutsPage() {
  usePageTitle('Configurations · Studio')
  const layouts = useLayouts()
  const { clone, archive } = useLayoutMutations()
  const [creating, setCreating] = useState(false)

  return (
    <StudioPage
      kicker="la salle"
      title="Configurations"
      actions={
        creating ? (
          <Button
            variant="ghost"
            onClick={() => {
              setCreating(false)
            }}
          >
            Fermer le générateur
          </Button>
        ) : (
          <Button
            variant="gilt"
            leadingIcon={<Plus aria-hidden className="size-4" />}
            onClick={() => {
              setCreating(true)
            }}
          >
            Nouvelle configuration
          </Button>
        )
      }
    >
      {creating ? (
        <section aria-label="Générateur" className="mb-10">
          <LayoutGenerator
            onSaved={() => {
              setCreating(false)
            }}
          />
        </section>
      ) : null}
      <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {(layouts.data ?? []).map((layout) => (
          <li
            key={layout.id}
            className={`brutal bg-surface p-5 ${layout.archived ? 'opacity-60' : ''}`}
          >
            <p className="font-display text-2xl leading-tight">{layout.name}</p>
            <p className="mt-1 text-sm">
              {layout.capacity} places · {layout.zones.map((z) => z.name).join(', ')}
            </p>
            <p className="mt-2 flex flex-wrap gap-2">
              {layout.isDefault ? <Badge tone="gilt">par défaut</Badge> : null}
              {layout.locked ? (
                <Badge tone="ink">
                  <Lock aria-hidden className="size-3" /> verrouillée (ventes)
                </Badge>
              ) : null}
              {layout.archived ? <Badge>archivée</Badge> : null}
              {layout.clonedFromName ? (
                <Badge tone="accent">copie de {layout.clonedFromName}</Badge>
              ) : null}
            </p>
            <div className="mt-4 flex gap-2">
              <Button
                size="sm"
                variant="secondary"
                leadingIcon={<Copy aria-hidden className="size-4" />}
                onClick={() => {
                  clone.mutate(layout.id ?? '', {
                    onSuccess: () => {
                      toast('Copie créée', { tone: 'success' })
                    },
                  })
                }}
              >
                Dupliquer
              </Button>
              {!layout.archived ? (
                <Button
                  size="sm"
                  variant="ghost"
                  onClick={() => {
                    archive.mutate(layout.id ?? '')
                  }}
                >
                  Archiver
                </Button>
              ) : null}
            </div>
          </li>
        ))}
      </ul>
    </StudioPage>
  )
}
