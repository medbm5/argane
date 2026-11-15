import { CheckCircle2, CircleAlert, IdCard, XCircle } from 'lucide-react'
import { cn } from '../../design-system/cn'
import { formatLongDate, formatTime } from '../../lib/format'
import type { CheckInResult } from './api'

/**
 * Full-width verdict a steward can read at arm's length: green admitted, amber when a
 * proof must be checked, red otherwise. Announced to screen readers (assertive).
 */
export function Verdict({ result }: { result: CheckInResult }) {
  const admitted = result.verdict === 'ADMITTED'
  const proof = admitted && result.requiresProof
  const tone = proof
    ? 'bg-pop-yellow text-ink-900'
    : admitted
      ? 'bg-pop-green text-ink-900'
      : 'bg-pop-red text-paper-50'
  const Icon = proof
    ? IdCard
    : admitted
      ? CheckCircle2
      : result.verdict === 'ALREADY_USED'
        ? CircleAlert
        : XCircle
  const title = proof
    ? 'Justificatif !'
    : admitted
      ? 'Bienvenue'
      : ({
          ALREADY_USED: 'Déjà utilisé',
          WRONG_PERFORMANCE: 'Mauvaise date',
          NOT_VALID: 'Billet non valable',
          INVALID: 'Code invalide',
        }[result.verdict] ?? 'Refusé')

  return (
    <div role="alert" aria-live="assertive" className={cn('brutal p-6 text-center', tone)}>
      <Icon aria-hidden className="mx-auto size-16" />
      <p className="mt-2 font-display text-6xl leading-none uppercase">{title}</p>
      <p className="mt-3 text-lg font-bold">{result.message}</p>
      {result.seat || result.zone ? (
        <p className="mt-2 font-display text-3xl">
          {result.zone} {result.seat ? `· ${result.seat}` : '· debout'}
          {result.tariff ? <span className="ml-2 text-xl">({result.tariff})</span> : null}
        </p>
      ) : null}
      {result.verdict === 'WRONG_PERFORMANCE' && result.ticketPerformanceStartsAt ? (
        <p className="mt-2">
          Billet pour {result.ticketEventTitle} — {formatLongDate(result.ticketPerformanceStartsAt)}{' '}
          à {formatTime(result.ticketPerformanceStartsAt)}
        </p>
      ) : null}
      {result.verdict === 'ALREADY_USED' && result.checkedInAt ? (
        <p className="mt-2">Premier passage à {formatTime(result.checkedInAt)}</p>
      ) : null}
      {result.reference ? (
        <p className="mt-2 text-sm opacity-80">Commande {result.reference}</p>
      ) : null}
    </div>
  )
}
