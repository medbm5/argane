import { renderHook } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { usePageTitle } from './usePageTitle'

describe('usePageTitle', () => {
  it('suffixes the site name and restores the default on unmount', () => {
    const { rerender, unmount } = renderHook<undefined, { title: string | null }>(
      ({ title }: { title: string | null }) => {
        usePageTitle(title)
      },
      { initialProps: { title: 'Programmation' } },
    )
    expect(document.title).toBe('Programmation · Théâtre Argane')

    rerender({ title: null })
    expect(document.title).toBe('Théâtre Argane — Billetterie')

    rerender({ title: 'Mes billets' })
    unmount()
    expect(document.title).toBe('Théâtre Argane — Billetterie')
  })
})
