import * as RadixDialog from '@radix-ui/react-dialog'
import { X } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '../cn'

export interface SheetProps {
  open?: boolean
  onOpenChange?: (open: boolean) => void
  trigger?: ReactNode
  title: string
  description?: string
  side?: 'right' | 'bottom'
  children: ReactNode
  className?: string
}

/** Side panel (mobile nav, selection summary on small screens, studio forms). */
export function Sheet({
  open,
  onOpenChange,
  trigger,
  title,
  description,
  side = 'right',
  children,
  className,
}: SheetProps) {
  return (
    <RadixDialog.Root open={open} onOpenChange={onOpenChange}>
      {trigger ? <RadixDialog.Trigger asChild>{trigger}</RadixDialog.Trigger> : null}
      <RadixDialog.Portal>
        <RadixDialog.Overlay className="fixed inset-0 z-40 bg-ink-900/40" />
        <RadixDialog.Content
          className={cn(
            'fixed z-50 flex flex-col overflow-y-auto border-line bg-surface text-fg shadow-float',
            side === 'right' &&
              'inset-y-0 right-0 w-[min(28rem,100vw)] border-l data-[state=open]:animate-[slide-in-right_240ms_var(--ease-out)]',
            side === 'bottom' &&
              'inset-x-0 bottom-0 max-h-[85vh] rounded-t-md border-t data-[state=open]:animate-[slide-in-up_240ms_var(--ease-out)]',
            className,
          )}
        >
          <header className="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
            <div>
              <RadixDialog.Title className="font-display text-2xl">{title}</RadixDialog.Title>
              <RadixDialog.Description className={description ? 'text-sm text-muted' : 'sr-only'}>
                {description ?? title}
              </RadixDialog.Description>
            </div>
            <RadixDialog.Close
              aria-label="Fermer"
              className="grid size-8 place-items-center rounded-pill text-muted hover:bg-surface-alt hover:text-fg"
            >
              <X aria-hidden className="size-4" />
            </RadixDialog.Close>
          </header>
          <div className="flex-1 px-6 py-5">{children}</div>
        </RadixDialog.Content>
      </RadixDialog.Portal>
    </RadixDialog.Root>
  )
}
