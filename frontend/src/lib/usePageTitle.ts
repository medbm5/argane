import { useEffect } from 'react'

export const SITE_NAME = 'Théâtre Argane'
const DEFAULT_TITLE = `${SITE_NAME} — Billetterie`

/** Sets the tab title ("Le Misanthrope · Théâtre Argane"); restores the default after. */
export function usePageTitle(title: string | null | undefined): void {
  useEffect(() => {
    document.title = title ? `${title} · ${SITE_NAME}` : DEFAULT_TITLE
    return () => {
      document.title = DEFAULT_TITLE
    }
  }, [title])
}
