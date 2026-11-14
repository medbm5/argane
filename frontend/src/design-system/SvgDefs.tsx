/**
 * Global SVG filter definitions, mounted once at the app root.
 *
 * - `argane-duotone`: maps luminance onto ink-900 → paper-100 so every image
 *   shares one sepia colour world.
 * - `argane-duotone-night`: night-950 → stage-glow, for the seat selection route.
 */

/** Hex like #2b1a10 → [r, g, b] in 0..1 */
function rgb(hex: string): [number, number, number] {
  const n = Number.parseInt(hex.slice(1), 16)
  return [((n >> 16) & 255) / 255, ((n >> 8) & 255) / 255, (n & 255) / 255]
}

function Duotone({ id, shadow, highlight }: { id: string; shadow: string; highlight: string }) {
  const [sr, sg, sb] = rgb(shadow)
  const [hr, hg, hb] = rgb(highlight)
  return (
    <filter id={id} colorInterpolationFilters="sRGB">
      <feColorMatrix
        type="matrix"
        values="0.299 0.587 0.114 0 0  0.299 0.587 0.114 0 0  0.299 0.587 0.114 0 0  0 0 0 1 0"
      />
      <feComponentTransfer>
        <feFuncR type="table" tableValues={`${sr} ${hr}`} />
        <feFuncG type="table" tableValues={`${sg} ${hg}`} />
        <feFuncB type="table" tableValues={`${sb} ${hb}`} />
      </feComponentTransfer>
    </filter>
  )
}

export function SvgDefs() {
  return (
    <svg
      aria-hidden
      width="0"
      height="0"
      className="pointer-events-none absolute"
      focusable="false"
    >
      <defs>
        <Duotone id="argane-duotone" shadow="#2b1a10" highlight="#f2e8d5" />
        <Duotone id="argane-duotone-night" shadow="#140e0a" highlight="#e9b96e" />
      </defs>
    </svg>
  )
}
