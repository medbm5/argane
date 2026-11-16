import { ImageUp, X } from 'lucide-react'
import { useId, useRef, useState } from 'react'
import { Button } from '../../design-system/primitives/Button'
import { ApiError, mediaUrl } from '../../lib/api'
import { useUploadPoster } from './api'

const MAX_BYTES = 2 * 1024 * 1024
const ACCEPT = 'image/jpeg,image/png,image/webp'

/**
 * The event poster: upload an image (stored by the API), with a preview.
 */
export function PosterField({
  value,
  onChange,
  error,
}: {
  value: string | null
  onChange: (path: string | null) => void
  error?: string
}) {
  const upload = useUploadPoster()
  const input = useRef<HTMLInputElement>(null)
  const statusId = useId()

  const [tooLarge, setTooLarge] = useState(false)
  const uploadError =
    error ??
    (tooLarge
      ? 'L’affiche dépasse 2 Mo. Réduisez-la puis réessayez.'
      : upload.error instanceof ApiError
        ? upload.error.problem.title
        : upload.error
          ? upload.error.message
          : undefined)

  function pick(file: File | undefined) {
    if (!file) return
    upload.reset()
    // Checked here too, so a big photo is not sent only to be refused.
    setTooLarge(file.size > MAX_BYTES)
    if (file.size > MAX_BYTES) return
    upload.mutate(file, {
      onSuccess: ({ path }) => {
        onChange(path)
      },
    })
  }

  return (
    <div className="flex flex-col gap-3 sm:col-span-2">
      <span className="text-sm font-medium text-fg">Affiche</span>
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
        <div className="flex h-36 w-24 shrink-0 items-center justify-center overflow-hidden rounded-sm border-2 border-fg bg-surface-alt">
          {value ? (
            <img
              src={mediaUrl(value)}
              alt="Aperçu de l’affiche"
              className="h-full w-full object-cover"
            />
          ) : (
            <span className="px-2 text-center text-xs text-muted">Aucune affiche</span>
          )}
        </div>
        <div className="flex min-w-0 flex-1 flex-col gap-3">
          <div className="flex flex-wrap items-center gap-3">
            <Button
              type="button"
              variant="secondary"
              size="sm"
              loading={upload.isPending}
              leadingIcon={<ImageUp aria-hidden className="size-4" />}
              aria-describedby={statusId}
              onClick={() => input.current?.click()}
            >
              {value ? 'Changer l’image' : 'Téléverser une image'}
            </Button>
            {value ? (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                leadingIcon={<X aria-hidden className="size-4" />}
                onClick={() => {
                  upload.reset()
                  setTooLarge(false)
                  onChange(null)
                }}
              >
                Retirer
              </Button>
            ) : null}
            <input
              ref={input}
              type="file"
              accept={ACCEPT}
              className="sr-only"
              tabIndex={-1}
              aria-label="Fichier de l’affiche"
              onChange={(e) => {
                pick(e.target.files?.[0])
                e.target.value = ''
              }}
            />
          </div>
          <p
            id={statusId}
            role={uploadError ? 'alert' : undefined}
            className={uploadError ? 'text-sm text-danger' : 'text-sm text-muted'}
          >
            {upload.isPending
              ? 'Envoi de l’image…'
              : (uploadError ??
                'JPEG, PNG ou WebP, 2 Mo au plus. Format portrait conseillé (2:3).')}
          </p>
        </div>
      </div>
    </div>
  )
}
