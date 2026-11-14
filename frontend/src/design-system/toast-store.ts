import { useSyncExternalStore } from 'react'

export type ToastTone = 'neutral' | 'success' | 'danger'

export interface ToastMessage {
  id: number
  title: string
  description?: string
  tone: ToastTone
}

let toasts: ToastMessage[] = []
let nextId = 1
const listeners = new Set<() => void>()

function emit() {
  for (const listener of listeners) listener()
}

/**
 * Show a toast. Copy rule: reuse the verb of the button that triggered it
 * ("Annuler la commande" → "Commande annulée").
 */
export function toast(title: string, options: { description?: string; tone?: ToastTone } = {}) {
  const message: ToastMessage = {
    id: nextId++,
    title,
    description: options.description,
    tone: options.tone ?? 'neutral',
  }
  toasts = [...toasts, message]
  emit()
  return message.id
}

export function dismissToast(id: number) {
  toasts = toasts.filter((t) => t.id !== id)
  emit()
}

export function useToasts(): ToastMessage[] {
  return useSyncExternalStore(
    (listener) => {
      listeners.add(listener)
      return () => listeners.delete(listener)
    },
    () => toasts,
    () => toasts,
  )
}
