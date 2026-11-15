import type { ReactNode } from 'react'

/** Title row shared by Studio pages: marker kicker, display title, actions on the right. */
export function StudioPage({
  kicker,
  title,
  actions,
  children,
}: {
  kicker: string
  title: string
  actions?: ReactNode
  children: ReactNode
}) {
  return (
    <div className="mx-auto max-w-7xl">
      <header className="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="-rotate-1 font-marker text-lg text-accent">{kicker}</p>
          <h1 className="font-display text-5xl leading-none">{title}</h1>
        </div>
        {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
      </header>
      {children}
    </div>
  )
}

/** A brutal-style data table wrapper with horizontal scroll on small screens. */
export function StudioTable({ children, label }: { children: ReactNode; label: string }) {
  return (
    <div className="brutal overflow-x-auto bg-surface">
      <table
        aria-label={label}
        className="w-full text-left text-sm [&_tbody_tr]:border-t [&_tbody_tr]:border-fg/10 [&_td]:px-3 [&_td]:py-2.5 [&_th]:px-3 [&_th]:py-2 [&_th]:text-xs [&_th]:font-bold [&_th]:tracking-[0.12em] [&_th]:uppercase [&_thead]:bg-ink-900 [&_thead]:text-paper-100"
      >
        {children}
      </table>
    </div>
  )
}
