import { act, render, screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { HoldCountdown } from './HoldCountdown'

beforeEach(() => {
  vi.useFakeTimers()
  vi.setSystemTime(new Date('2026-11-15T10:00:00Z'))
})

afterEach(() => {
  vi.useRealTimers()
})

describe('HoldCountdown', () => {
  it('counts down every second and turns urgent in the last two minutes', () => {
    const { container } = render(
      <HoldCountdown expiresAt="2026-11-15T10:02:05Z" onExpire={() => undefined} />,
    )
    expect(screen.getByText('2:05')).toBeInTheDocument()
    expect(container.firstElementChild).toHaveClass('bg-pop-yellow')

    act(() => {
      vi.advanceTimersByTime(6000)
    })
    expect(screen.getByText('1:59')).toBeInTheDocument()
    expect(container.firstElementChild).toHaveClass('bg-pop-red')
  })

  it('calls onExpire once when time is up', () => {
    const onExpire = vi.fn()
    render(<HoldCountdown expiresAt="2026-11-15T10:00:03Z" onExpire={onExpire} />)

    act(() => {
      vi.advanceTimersByTime(10_000)
    })
    expect(screen.getByText('0:00')).toBeInTheDocument()
    expect(onExpire).toHaveBeenCalledTimes(1)
  })
})
