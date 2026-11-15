import QRCode from 'qrcode'
import { useMemo } from 'react'

/**
 * A ticket's QR code as crisp inline SVG, computed synchronously from the module matrix
 * (no canvas, works in tests and prints sharply). Level M, 2-module quiet zone.
 */
export function QrCode({ value, label }: { value: string; label: string }) {
  const { size, path } = useMemo(() => {
    const { modules } = QRCode.create(value, { errorCorrectionLevel: 'M' })
    const quiet = 2
    let d = ''
    for (let y = 0; y < modules.size; y++) {
      for (let x = 0; x < modules.size; x++) {
        if (modules.get(x, y)) d += `M${String(x + quiet)} ${String(y + quiet)}h1v1h-1z`
      }
    }
    return { size: modules.size + quiet * 2, path: d }
  }, [value])

  return (
    <svg
      viewBox={`0 0 ${String(size)} ${String(size)}`}
      role="img"
      aria-label={label}
      shapeRendering="crispEdges"
      className="size-full"
    >
      <rect width={size} height={size} fill="#fff" />
      <path d={path} fill="#15121e" />
    </svg>
  )
}
