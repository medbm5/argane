import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ExternalLink, RefreshCw, Sheet } from 'lucide-react'
import { Badge } from '../../design-system/primitives/Badge'
import { Button } from '../../design-system/primitives/Button'
import { toast } from '../../design-system/toast-store'
import { StudioPage } from '../../features/studio/StudioPage'
import { ApiError } from '../../lib/api'
import { formatLongDate, formatTime } from '../../lib/format'
import { useApi } from '../../lib/useApi'
import { usePageTitle } from '../../lib/usePageTitle'

interface SheetsStatus {
  configured: boolean
  url: string | null
  lastSyncAt: string | null
  lastAttemptAt?: string
  lastError: string | null
  rows: number
}

const KEY = ['studio', 'integrations', 'sheets'] as const

/** /studio/integrations — the Google Sheets mirror of sales. */
export function StudioIntegrationsPage() {
  usePageTitle('Intégrations · Studio')
  const api = useApi()
  const queryClient = useQueryClient()
  const status = useQuery({
    queryKey: KEY,
    queryFn: ({ signal }) => api.get<SheetsStatus>('/studio/integrations/sheets', { signal }),
  })
  const sync = useMutation({
    mutationFn: () => api.post<SheetsStatus>('/studio/integrations/sheets/sync'),
    onSuccess: (next) => {
      queryClient.setQueryData(KEY, next)
      toast(`Feuille à jour : ${String(next.rows)} billets`, { tone: 'success' })
    },
    onError: (e) => {
      void queryClient.invalidateQueries({ queryKey: KEY })
      toast(e instanceof ApiError ? e.message : 'Synchronisation impossible', { tone: 'danger' })
    },
  })
  const s = status.data

  return (
    <StudioPage kicker="les outils" title="Intégrations">
      <section aria-labelledby="sheets-title" className="brutal max-w-3xl bg-surface p-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-center gap-3">
            <Sheet aria-hidden className="size-10 text-success" />
            <div>
              <h2 id="sheets-title" className="font-display text-3xl leading-none">
                Google Sheets
              </h2>
              <p className="text-sm text-muted">
                Onglets « Ventes » (un billet par ligne) et « Synthèse » (par date).
              </p>
            </div>
          </div>
          {s ? (
            s.configured ? (
              <Badge tone="success">connecté</Badge>
            ) : (
              <Badge>non configuré</Badge>
            )
          ) : null}
        </div>

        {s?.configured ? (
          <>
            <dl className="mt-6 grid gap-4 sm:grid-cols-3">
              <div>
                <dt className="text-xs font-bold tracking-[0.12em] uppercase">Dernière synchro</dt>
                <dd className="mt-1">
                  {s.lastSyncAt
                    ? `${formatLongDate(s.lastSyncAt)} à ${formatTime(s.lastSyncAt)}`
                    : 'jamais'}
                </dd>
              </div>
              <div>
                <dt className="text-xs font-bold tracking-[0.12em] uppercase">Billets</dt>
                <dd className="tabular mt-1 font-display text-3xl">{s.rows}</dd>
              </div>
              <div>
                <dt className="text-xs font-bold tracking-[0.12em] uppercase">Automatique</dt>
                <dd className="mt-1 text-sm">
                  après chaque vente ou remboursement, et toutes les heures
                </dd>
              </div>
            </dl>
            {s.lastError ? (
              <p
                role="alert"
                className="mt-4 border-2 border-danger p-3 text-sm font-bold text-danger"
              >
                Dernier échec : {s.lastError}
              </p>
            ) : null}
            <div className="mt-6 flex flex-wrap gap-3">
              <Button
                variant="gilt"
                loading={sync.isPending}
                leadingIcon={<RefreshCw aria-hidden className="size-4" />}
                onClick={() => {
                  sync.mutate()
                }}
              >
                Synchroniser maintenant
              </Button>
              {s.url ? (
                <Button asChild variant="secondary">
                  <a href={s.url} target="_blank" rel="noreferrer">
                    Ouvrir la feuille <ExternalLink aria-hidden className="size-4" />
                  </a>
                </Button>
              ) : null}
            </div>
          </>
        ) : s ? (
          <div className="mt-6 space-y-2 text-sm">
            <p>Pour activer la synchronisation :</p>
            <ol className="list-decimal space-y-1 pl-5">
              <li>Créez un compte de service dans Google Cloud et téléchargez sa clé JSON.</li>
              <li>Partagez la feuille avec l’email du compte de service (éditeur).</li>
              <li>
                Renseignez <code>GOOGLE_SHEETS_SPREADSHEET_ID</code> et{' '}
                <code>GOOGLE_SERVICE_ACCOUNT_JSON_BASE64</code> côté serveur.
              </li>
            </ol>
            <p className="text-muted">Le détail est dans INSTALL.md.</p>
          </div>
        ) : null}
      </section>
    </StudioPage>
  )
}
