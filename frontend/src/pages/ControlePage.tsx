import { useCallback, useEffect, useState, type SyntheticEvent } from 'react'
import { Button } from '../design-system/primitives/Button'
import { Input } from '../design-system/primitives/Input'
import { Select } from '../design-system/primitives/Select'
import { useCounterPerformances } from '../features/boxoffice/api'
import { useCheckInCounts, useScan, type CheckInResult } from '../features/checkin/api'
import { CameraScanner } from '../features/checkin/CameraScanner'
import { Verdict } from '../features/checkin/Verdict'
import { formatShortDate, formatTime } from '../lib/format'
import { usePageTitle } from '../lib/usePageTitle'

const VERDICT_MS = 2500

/** /controle — door check-in for one performance, camera first, typing as a fallback. */
export function ControlePage() {
  usePageTitle('Contrôle des billets')
  const performances = useCounterPerformances()
  const [picked, setPicked] = useState<string | undefined>(undefined)
  const list = (performances.data ?? []).filter((p) => p.status !== 'CANCELLED')
  // Default to the next performance to start (the one at the door tonight).
  const performanceId = picked ?? list[0]?.id

  return (
    <div className="mx-auto max-w-3xl px-4 py-6">
      <Select
        label="Représentation"
        value={performanceId}
        onValueChange={setPicked}
        placeholder={performances.isPending ? 'Chargement…' : 'Aucune représentation'}
        options={list.map((p) => ({
          value: p.id,
          label: `${p.eventTitle} · ${formatShortDate(p.startsAt)} ${formatTime(p.startsAt)}`,
        }))}
      />
      {performanceId ? <Door key={performanceId} performanceId={performanceId} /> : null}
    </div>
  )
}

function Door({ performanceId }: { performanceId: string }) {
  const counts = useCheckInCounts(performanceId)
  const scan = useScan(performanceId)
  const [result, setResult] = useState<CheckInResult | null>(null)
  const [manual, setManual] = useState('')

  const submit = useCallback(
    (payload: string) => {
      scan.mutate(payload, { onSuccess: setResult })
    },
    [scan],
  )

  // Clear the verdict after a moment so the next spectator can be scanned.
  useEffect(() => {
    if (!result) return
    const timer = setTimeout(() => {
      setResult(null)
    }, VERDICT_MS)
    return () => {
      clearTimeout(timer)
    }
  }, [result])

  function typed(e: SyntheticEvent<HTMLFormElement>) {
    e.preventDefault()
    if (manual.trim()) {
      submit(manual.trim())
      setManual('')
    }
  }

  return (
    <div className="mt-6 space-y-5">
      <p className="flex items-baseline justify-between font-display text-3xl" aria-live="polite">
        <span>Entrées</span>
        <span className="tabular">
          {counts.data?.admitted ?? 0} / {counts.data?.expected ?? 0}
        </span>
      </p>
      {result ? <Verdict result={result} /> : null}
      <div className={result ? 'hidden' : undefined}>
        <CameraScanner onScan={submit} paused={result !== null || scan.isPending} />
      </div>
      <form onSubmit={typed} className="flex items-end gap-2">
        <Input
          label="Saisie manuelle du code"
          value={manual}
          onChange={(e) => {
            setManual(e.target.value)
          }}
          placeholder="ARG1.…"
        />
        <Button type="submit" variant="secondary" loading={scan.isPending}>
          Valider
        </Button>
      </form>
    </div>
  )
}
