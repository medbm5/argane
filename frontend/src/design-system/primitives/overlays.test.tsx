import { act, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { toast } from '../toast-store'
import { Button } from './Button'
import { Dialog } from './Dialog'
import { Tabs } from './Tabs'
import { Toaster } from './Toaster'

describe('Dialog', () => {
  it('opens from its trigger with an accessible title', async () => {
    render(
      <Dialog
        title="Annuler la commande ?"
        description="Les billets seront remboursés."
        trigger={<Button>Annuler la commande</Button>}
      />,
    )

    await userEvent.click(screen.getByRole('button', { name: 'Annuler la commande' }))

    expect(screen.getByRole('dialog', { name: 'Annuler la commande ?' })).toBeInTheDocument()
  })
})

describe('Tabs', () => {
  it('switches panels', async () => {
    render(
      <Tabs
        label="Vues"
        items={[
          { value: 'a', label: 'Ventes', content: 'Panneau ventes' },
          { value: 'b', label: 'Remboursements', content: 'Panneau remboursements' },
        ]}
      />,
    )

    await userEvent.click(screen.getByRole('tab', { name: 'Remboursements' }))

    expect(screen.getByRole('tabpanel')).toHaveTextContent('Panneau remboursements')
  })
})

describe('Toaster', () => {
  it('shows toasts pushed from anywhere', () => {
    render(<Toaster />)
    act(() => {
      toast('Commande annulée', { tone: 'success' })
    })

    expect(screen.getByText('Commande annulée')).toBeInTheDocument()
  })
})
