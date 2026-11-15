/**
 * Comedy and tragedy masks, drawn like a stencil engraving: thick ink outlines,
 * hatching inside, a sprayed pop-colour shadow behind each mask.
 */
export function MasksArt({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 260 150"
      aria-hidden
      className={className}
      fill="none"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <defs>
        <pattern
          id="hatch"
          width="6"
          height="6"
          patternUnits="userSpaceOnUse"
          patternTransform="rotate(35)"
        >
          <line x1="0" y1="0" x2="0" y2="6" stroke="var(--ink-900)" strokeWidth="1.2" />
        </pattern>
      </defs>
      {/* comedy */}
      <g transform="translate(18 14) rotate(-10 55 60)">
        <path
          d="M8 18 C8 4 102 4 102 18 C102 78 82 118 55 118 C28 118 8 78 8 18Z"
          fill="var(--pop-yellow)"
          transform="translate(6 6)"
        />
        <path
          d="M8 18 C8 4 102 4 102 18 C102 78 82 118 55 118 C28 118 8 78 8 18Z"
          fill="var(--paper-50)"
          stroke="var(--ink-900)"
          strokeWidth="4"
        />
        <path
          d="M70 92 C64 108 46 108 40 92"
          fill="url(#hatch)"
          stroke="var(--ink-900)"
          strokeWidth="3"
        />
        <path d="M26 44 C32 34 44 34 48 44" stroke="var(--ink-900)" strokeWidth="4" />
        <path d="M62 44 C66 34 78 34 84 44" stroke="var(--ink-900)" strokeWidth="4" />
        <path
          d="M22 66 C28 72 36 72 40 68 M70 68 C74 72 82 72 88 66"
          stroke="var(--pop-red)"
          strokeWidth="3"
        />
      </g>
      {/* tragedy */}
      <g transform="translate(132 22) rotate(9 55 60)">
        <path
          d="M8 18 C8 4 102 4 102 18 C102 78 82 118 55 118 C28 118 8 78 8 18Z"
          fill="var(--pop-blue)"
          transform="translate(6 6)"
        />
        <path
          d="M8 18 C8 4 102 4 102 18 C102 78 82 118 55 118 C28 118 8 78 8 18Z"
          fill="var(--paper-50)"
          stroke="var(--ink-900)"
          strokeWidth="4"
        />
        <path
          d="M38 100 C46 86 64 86 72 100"
          fill="url(#hatch)"
          stroke="var(--ink-900)"
          strokeWidth="3"
        />
        <path d="M26 40 C32 50 44 50 48 40" stroke="var(--ink-900)" strokeWidth="4" />
        <path d="M62 40 C66 50 78 50 84 40" stroke="var(--ink-900)" strokeWidth="4" />
        <path d="M36 54 L34 70 M74 54 L76 72" stroke="var(--pop-blue)" strokeWidth="3" />
      </g>
    </svg>
  )
}
