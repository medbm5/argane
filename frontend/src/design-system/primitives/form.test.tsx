import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { Input } from './Input'
import { Pill } from './Pill'
import { Stepper } from './Stepper'

describe('Input', () => {
  it('links label, error and aria-invalid', () => {
    render(<Input label="Email" error="Adresse invalide." />)
    const input = screen.getByLabelText('Email')

    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input).toHaveAccessibleDescription('Adresse invalide.')
  })
})

describe('Stepper', () => {
  function Harness({ max }: { max: number }) {
    const [value, setValue] = useState(0)
    return <Stepper label="Fosse debout" value={value} onChange={setValue} max={max} />
  }

  it('increments and respects bounds', async () => {
    render(<Harness max={2} />)
    const plus = screen.getByRole('button', { name: /Ajouter/ })
    const minus = screen.getByRole('button', { name: /Retirer/ })

    expect(minus).toBeDisabled()
    await userEvent.click(plus)
    await userEvent.click(plus)
    expect(screen.getByText('2')).toBeInTheDocument()
    expect(plus).toBeDisabled()
  })
})

describe('Pill', () => {
  it('exposes pressed state', async () => {
    const onClick = vi.fn()
    render(
      <Pill selected onClick={onClick}>
        20:30
      </Pill>,
    )
    const pill = screen.getByRole('button', { name: '20:30' })

    expect(pill).toHaveAttribute('aria-pressed', 'true')
    await userEvent.click(pill)
    expect(onClick).toHaveBeenCalled()
  })
})
