import QrScanner from 'qr-scanner'
import { useEffect, useRef, useState } from 'react'

/**
 * Rear camera QR reader. Calls onScan once per new code (the same code is ignored for
 * a few seconds so a ticket held in front of the lens is not scanned 30 times).
 * Paused while `paused` is true (verdict on screen).
 */
export function CameraScanner({
  onScan,
  paused,
}: {
  onScan: (payload: string) => void
  paused: boolean
}) {
  const video = useRef<HTMLVideoElement>(null)
  const scanner = useRef<QrScanner | null>(null)
  const last = useRef<{ value: string; at: number }>({ value: '', at: 0 })
  const onScanRef = useRef(onScan)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    onScanRef.current = onScan
  }, [onScan])

  useEffect(() => {
    const element = video.current
    if (!element) return
    const instance = new QrScanner(
      element,
      (result) => {
        const now = Date.now()
        if (result.data === last.current.value && now - last.current.at < 4000) return
        last.current = { value: result.data, at: now }
        onScanRef.current(result.data)
      },
      { preferredCamera: 'environment', highlightScanRegion: true, maxScansPerSecond: 8 },
    )
    scanner.current = instance
    instance.start().catch(() => {
      setError('Caméra indisponible : autorisez son accès ou saisissez le code à la main.')
    })
    return () => {
      instance.destroy()
      scanner.current = null
    }
  }, [])

  useEffect(() => {
    const instance = scanner.current
    if (!instance) return
    if (paused) void instance.pause()
    else void instance.start().catch(() => undefined)
  }, [paused])

  return (
    <div className="relative aspect-square w-full overflow-hidden border-2 border-ink-900 bg-ink-900">
      <video ref={video} className="size-full object-cover" muted playsInline />
      {error ? (
        <p className="absolute inset-x-0 bottom-0 bg-pop-pink p-3 text-sm font-bold text-ink-900">
          {error}
        </p>
      ) : null}
    </div>
  )
}
