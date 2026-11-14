import * as RadixToast from '@radix-ui/react-toast'
import { CircleAlert, CircleCheck, X } from 'lucide-react'
import { cn } from '../cn'
import { dismissToast, useToasts } from '../toast-store'

/** Mount once near the root. Trigger with `toast()` from toast-store. */
export function Toaster() {
  const toasts = useToasts()

  return (
    <RadixToast.Provider swipeDirection="right" duration={5000} label="Notification">
      {toasts.map((t) => (
        <RadixToast.Root
          key={t.id}
          onOpenChange={(open) => {
            if (!open) dismissToast(t.id)
          }}
          className={cn(
            'flex items-start gap-3 rounded-md border bg-surface p-4 text-fg shadow-float',
            'data-[state=open]:animate-[rise-in_200ms_var(--ease-out)]',
            t.tone === 'success' && 'border-success/50',
            t.tone === 'danger' && 'border-danger/50',
            t.tone === 'neutral' && 'border-line',
          )}
        >
          {t.tone === 'success' ? (
            <CircleCheck aria-hidden className="mt-0.5 size-5 shrink-0 text-success" />
          ) : null}
          {t.tone === 'danger' ? (
            <CircleAlert aria-hidden className="mt-0.5 size-5 shrink-0 text-danger" />
          ) : null}
          <div className="flex-1">
            <RadixToast.Title className="font-medium">{t.title}</RadixToast.Title>
            {t.description ? (
              <RadixToast.Description className="mt-0.5 text-sm text-muted">
                {t.description}
              </RadixToast.Description>
            ) : null}
          </div>
          <RadixToast.Close aria-label="Fermer" className="text-muted hover:text-fg">
            <X aria-hidden className="size-4" />
          </RadixToast.Close>
        </RadixToast.Root>
      ))}
      <RadixToast.Viewport className="fixed right-4 bottom-4 z-[60] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2 outline-none" />
    </RadixToast.Provider>
  )
}
