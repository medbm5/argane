/**
 * Two pleated red curtains that part once the plan is ready. Purely decorative.
 * Under prefers-reduced-motion the global rule shortens the animation to 1 ms, so the
 * curtains are simply open.
 */
export function CurtainReveal({ open }: { open: boolean }) {
  const pleats =
    'repeating-linear-gradient(90deg, rgb(0 0 0 / 0.22) 0 6px, transparent 6px 26px, rgb(255 255 255 / 0.08) 26px 30px, transparent 30px 44px)'
  return (
    <div aria-hidden className="pointer-events-none absolute inset-0 z-10 overflow-hidden">
      {(['left', 'right'] as const).map((side) => (
        <div
          key={side}
          data-side={side}
          className={[
            'absolute inset-y-0 w-1/2 border-ink-900 bg-pop-red',
            side === 'left' ? 'left-0 border-r-4' : 'right-0 border-l-4',
            open
              ? side === 'left'
                ? 'animate-[curtain-left_1.1s_cubic-bezier(0.7,0,0.2,1)_0.15s_forwards]'
                : 'animate-[curtain-right_1.1s_cubic-bezier(0.7,0,0.2,1)_0.15s_forwards]'
              : '',
          ].join(' ')}
          style={{ backgroundImage: pleats }}
        />
      ))}
    </div>
  )
}
