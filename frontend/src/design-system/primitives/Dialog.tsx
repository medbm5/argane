import * as RadixDialog from '@radix-ui/react-dialog'
import { X } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '../cn'

export interface DialogProps {
  open?: boolean
  onOpenChange?: (open: boolean) => void
  trigger?: ReactNode
  title: string
  description?: ReactNode
  children?: ReactNode
  footer?: ReactNode
  /** Hide the close button (e.g. blocking "hold expired" dialog). */
  dismissible?: boolean
  className?: string
}

export function Dialog({
  open,
  onOpenChange,
  trigger,
  title,
  description,
  children,
  footer,
  dismissible = true,
  className,
}: DialogProps) {
  return (
    <RadixDialog.Root open={open} onOpenChange={onOpenChange}>
      {trigger ? <RadixDialog.Trigger asChild>{trigger}</RadixDialog.Trigger> : null}
      <RadixDialog.Portal>
        <RadixDialog.Overlay className="fixed inset-0 z-40 bg-ink-900/40 backdrop-blur-[2px] data-[state=open]:animate-[fade-in_200ms_var(--ease-out)]" />
        <RadixDialog.Content
          onEscapeKeyDown={
            dismissible
              ? undefined
              : (e) => {
                  e.preventDefault()
                }
          }
          onPointerDownOutside={
            dismissible
              ? undefined
              : (e) => {
                  e.preventDefault()
                }
          }
          className={cn(
            'fixed top-1/2 left-1/2 z-50 w-[min(32rem,calc(100vw-2rem))] -translate-x-1/2 -translate-y-1/2',
            'border-2 border-fg bg-surface p-6 text-fg shadow-hard',
            'data-[state=open]:animate-[rise-in_240ms_var(--ease-out)]',
            className,
          )}
        >
          <RadixDialog.Title className="pr-8 font-display text-4xl">{title}</RadixDialog.Title>
          {description ? (
            <RadixDialog.Description className="mt-2 text-muted">
              {description}
            </RadixDialog.Description>
          ) : (
            <RadixDialog.Description className="sr-only">{title}</RadixDialog.Description>
          )}
          {children ? <div className="mt-5">{children}</div> : null}
          {footer ? <div className="mt-6 flex flex-wrap justify-end gap-3">{footer}</div> : null}
          {dismissible ? (
            <RadixDialog.Close
              aria-label="Fermer"
              className="absolute top-4 right-4 grid size-8 place-items-center rounded-pill text-muted hover:bg-surface-alt hover:text-fg"
            >
              <X aria-hidden className="size-4" />
            </RadixDialog.Close>
          ) : null}
        </RadixDialog.Content>
      </RadixDialog.Portal>
    </RadixDialog.Root>
  )
}

export const DialogClose = RadixDialog.Close
