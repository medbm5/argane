import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { describe, expect, it } from 'vitest'
import { DatePicker, DateTimePicker } from './DatePicker'

function DateHarness({ initial = '2026-12-12', min }: { initial?: string; min?: string }) {
  const [value, setValue] = useState(initial)
  return (
    <>
      <DatePicker label="Du" value={value} min={min} clearable onChange={setValue} />
      <output>{value || 'empty'}</output>
    </>
  )
}

describe('DatePicker', () => {
  it('shows the date like the rest of the site and picks a day from the calendar', async () => {
    const user = userEvent.setup()
    render(<DateHarness />)

    const trigger = screen.getByRole('button', { name: /Du/ })
    expect(trigger).toHaveTextContent('12 déc. 2026')
    await user.click(trigger)

    expect(screen.getByText('décembre 2026')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'samedi 12 décembre 2026' })).toHaveFocus()
    await user.click(screen.getByRole('button', { name: 'jeudi 24 décembre 2026' }))

    expect(screen.getByText('2026-12-24')).toBeInTheDocument()
    expect(screen.queryByText('décembre 2026')).not.toBeInTheDocument()
  })

  it('moves with the keyboard: arrows by day and week, Page Down by month', async () => {
    const user = userEvent.setup()
    render(<DateHarness />)
    await user.click(screen.getByRole('button', { name: /Du/ }))

    await user.keyboard('{ArrowRight}{ArrowDown}')
    expect(screen.getByRole('button', { name: 'dimanche 20 décembre 2026' })).toHaveFocus()
    await user.keyboard('{PageDown}')
    expect(screen.getByText('janvier 2027')).toBeInTheDocument()
    await user.keyboard('{Enter}')

    expect(screen.getByText('2027-01-20')).toBeInTheDocument()
  })

  it('cannot pick a day before the minimum, and can be cleared', async () => {
    const user = userEvent.setup()
    render(<DateHarness min="2026-12-10" />)
    await user.click(screen.getByRole('button', { name: /Du/ }))

    expect(screen.getByRole('button', { name: 'mercredi 9 décembre 2026' })).toBeDisabled()
    await user.click(screen.getByRole('button', { name: 'Effacer' }))
    expect(screen.getByText('empty')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Du/ })).toHaveTextContent('Choisir une date')
  })
})

describe('DateTimePicker', () => {
  it('builds a local date-time from the calendar and the hour and minute menus', async () => {
    function Harness() {
      const [value, setValue] = useState('')
      return (
        <>
          <DateTimePicker label="Début" value={value} onChange={setValue} />
          <output>{value || 'empty'}</output>
        </>
      )
    }
    const user = userEvent.setup()
    render(<Harness />)

    await user.click(screen.getByRole('button', { name: /Début — date/ }))
    await user.click(screen.getByRole('button', { name: 'Aujourd’hui' }))
    expect(screen.getByRole('status')).toHaveTextContent(/T20:00$/)

    await user.click(screen.getByRole('combobox', { name: /Début — heure/ }))
    await user.click(await screen.findByRole('option', { name: '19 h' }))
    await user.click(screen.getByRole('combobox', { name: /Début — minutes/ }))
    await user.click(await screen.findByRole('option', { name: '30' }))

    expect(screen.getByRole('status')).toHaveTextContent(/T19:30$/)
  })
})
