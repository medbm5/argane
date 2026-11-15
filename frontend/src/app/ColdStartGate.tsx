import { useEffect, useState, type ReactNode } from 'react'
import { Button } from '../design-system/primitives/Button'
import { API_URL } from '../lib/api'

/** Backoff between health checks while the API container wakes up (~75 s in total). */
const RETRY_DELAYS_MS = [1000, 2000, 3000, 5000, 8000, 8000, 8000, 10000, 15000, 15000]
/** Below this, the API answered fast enough that no loader is shown at all. */
const SHOW_LOADER_AFTER_MS = 600

type Phase = 'checking' | 'waking' | 'ready' | 'failed'

async function isHealthy(signal: AbortSignal): Promise<boolean> {
  try {
    const response = await fetch(`${API_URL}/health`, { signal, cache: 'no-store' })
    return response.ok
  } catch {
    return false
  }
}

const sleep = (ms: number, signal: AbortSignal) =>
  new Promise<void>((resolve) => {
    const id = setTimeout(resolve, ms)
    signal.addEventListener('abort', () => {
      clearTimeout(id)
      resolve()
    })
  })

/**
 * Free hosting tiers put the API to sleep. Before rendering the app, ping /api/health;
 * if it is slow or down, show a themed loader and retry with backoff.
 */
export function ColdStartGate({
  children,
  delays = RETRY_DELAYS_MS,
}: {
  children: ReactNode
  delays?: number[]
}) {
  const [phase, setPhase] = useState<Phase>('checking')
  const [attempt, setAttempt] = useState(0)
  const [run, setRun] = useState(0)

  useEffect(() => {
    const controller = new AbortController()
    const { signal } = controller
    const reveal = setTimeout(() => {
      setPhase((p) => (p === 'checking' ? 'waking' : p))
    }, SHOW_LOADER_AFTER_MS)

    void (async () => {
      for (let i = 0; i <= delays.length; i++) {
        if (signal.aborted) return
        setAttempt(i)
        if (await isHealthy(signal)) {
          setPhase('ready')
          return
        }
        if (i === 0) setPhase('waking')
        if (i === delays.length) break
        await sleep(delays[i] ?? 0, signal)
      }
      if (!signal.aborted) setPhase('failed')
    })()

    return () => {
      clearTimeout(reveal)
      controller.abort()
    }
  }, [delays, run])

  if (phase === 'ready') return children
  if (phase === 'checking') return null

  return (
    <div
      role="status"
      aria-live="polite"
      className="paper-grain wall fixed inset-0 z-50 flex flex-col items-center justify-center bg-bg px-6 text-center text-fg"
    >
      {/* Two curtain halves drawing apart while we wait. */}
      <div
        aria-hidden
        className="relative mb-10 h-28 w-56 overflow-hidden border-2 border-ink-900 bg-pop-yellow shadow-hard"
      >
        <span className="absolute inset-y-0 left-0 w-1/2 origin-left animate-[curtain_2.4s_ease-in-out_infinite_alternate] bg-pop-red" />
        <span className="absolute inset-y-0 right-0 w-1/2 origin-right animate-[curtain_2.4s_ease-in-out_infinite_alternate] bg-pop-red" />
      </div>
      {phase === 'failed' ? (
        <>
          <p className="font-display text-5xl">Entracte prolongé</p>
          <p className="mt-4 max-w-md">
            Le serveur de billetterie ne répond pas. Il se réveille parfois lentement, réessayez
            dans un instant.
          </p>
          <Button
            className="mt-8"
            variant="gilt"
            size="lg"
            onClick={() => {
              setPhase('waking')
              setRun((r) => r + 1)
            }}
          >
            Réessayer
          </Button>
        </>
      ) : (
        <>
          <p className="font-display text-5xl">Le rideau se lève…</p>
          <p className="mt-4 -rotate-1 font-marker text-xl text-accent">
            la billetterie se réveille{attempt > 2 ? ', encore quelques secondes' : ''}
          </p>
        </>
      )}
    </div>
  )
}
