import type { ReactNode } from 'react'
import { cn } from '../cn'

export type StateKind = 'empty' | 'error' | 'notFound'

/** Bare stage under a spotlight. */
function EmptyArt() {
  return (
    <svg viewBox="0 0 200 120" aria-hidden className="h-28 w-auto">
      <path d="M100 0 L60 96 H140 Z" fill="var(--pop-yellow)" fillOpacity="0.55" />
      <rect x="92" y="0" width="16" height="10" fill="var(--ink-900)" />
      <rect
        x="20"
        y="96"
        width="160"
        height="14"
        fill="var(--paper-50)"
        stroke="var(--ink-900)"
        strokeWidth="4"
      />
      <path
        d="M20 96 V20 M180 96 V20"
        stroke="var(--pop-red)"
        strokeWidth="10"
        strokeLinecap="round"
      />
      <ellipse cx="100" cy="96" rx="34" ry="5" fill="var(--pop-yellow)" />
    </svg>
  )
}

/** A curtain fallen off its rail. */
function ErrorArt() {
  return (
    <svg viewBox="0 0 200 120" aria-hidden className="h-28 w-auto">
      <path d="M14 14 H186" stroke="var(--ink-900)" strokeWidth="6" strokeLinecap="round" />
      <path
        d="M22 14 C30 50 18 80 30 110 H96 C88 80 98 46 92 14 Z"
        fill="var(--pop-red)"
        stroke="var(--ink-900)"
        strokeWidth="4"
      />
      <path
        d="M104 26 C120 40 150 50 178 96 L186 110 H110 C116 80 100 50 104 26 Z"
        fill="var(--pop-red)"
        stroke="var(--ink-900)"
        strokeWidth="4"
      />
      <path
        d="M44 30 C48 60 40 84 48 108 M66 22 C70 56 62 82 70 108 M128 52 C138 70 150 84 160 104"
        stroke="var(--ink-900)"
        strokeOpacity="0.4"
        strokeWidth="3"
        fill="none"
      />
      <path
        d="M172 20 l8 -8 M176 30 l10 -4"
        stroke="var(--pop-yellow)"
        strokeWidth="4"
        strokeLinecap="round"
      />
    </svg>
  )
}

/** A torn ticket stub reading 404. */
function NotFoundArt() {
  return (
    <svg viewBox="0 0 200 120" aria-hidden className="h-28 w-auto">
      <g transform="rotate(-6 100 60)">
        <path
          d="M18 24 H120 L112 36 L122 48 L110 60 L122 72 L112 84 L120 96 H18 Z"
          fill="var(--paper-50)"
          stroke="var(--ink-900)"
          strokeWidth="4"
        />
        <text
          x="66"
          y="72"
          textAnchor="middle"
          className="font-display"
          fontSize="36"
          fill="var(--ink-900)"
        >
          404
        </text>
      </g>
      <g transform="rotate(10 160 60)">
        <path
          d="M134 30 L144 40 L134 52 L146 64 L134 76 L144 88 H184 V30 Z"
          fill="var(--pop-yellow)"
          stroke="var(--ink-900)"
          strokeWidth="4"
        />
        <path d="M156 44 V78" stroke="var(--ink-900)" strokeWidth="3" strokeDasharray="4 4" />
      </g>
    </svg>
  )
}

const ART: Record<StateKind, () => ReactNode> = {
  empty: EmptyArt,
  error: ErrorArt,
  notFound: NotFoundArt,
}

/**
 * Designed empty / error / not-found state: an illustration, a sprayed kicker, a title,
 * a sentence of explanation and what to do next.
 */
export function StatePanel({
  kind,
  kicker,
  title,
  children,
  actions,
  className,
  headingLevel = 2,
}: {
  kind: StateKind
  kicker: string
  title: string
  children?: ReactNode
  actions?: ReactNode
  className?: string
  headingLevel?: 1 | 2
}) {
  const Heading = headingLevel === 1 ? 'h1' : 'h2'
  const Art = ART[kind]
  return (
    <section
      role={kind === 'error' ? 'alert' : undefined}
      className={cn(
        'brutal mx-auto flex max-w-2xl flex-col items-center bg-surface px-6 py-10 text-center',
        className,
      )}
    >
      <Art />
      <p
        className={cn(
          'mt-4 -rotate-1 font-marker text-lg',
          kind === 'error' ? 'text-danger' : 'text-accent',
        )}
      >
        {kicker}
      </p>
      <Heading className="mt-1 font-display text-4xl leading-none sm:text-5xl">{title}</Heading>
      {children ? <div className="mt-4 max-w-md text-muted">{children}</div> : null}
      {actions ? <div className="mt-6 flex flex-wrap justify-center gap-3">{actions}</div> : null}
    </section>
  )
}
