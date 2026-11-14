import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Plus } from 'lucide-react'
import { describe, expect, it, vi } from 'vitest'
import { StatusBadge } from './Badge'
import { Button } from './Button'
import { IconButton } from './IconButton'

describe('Button', () => {
  it('says what happens and fires on click', async () => {
    const onClick = vi.fn()
    render(<Button onClick={onClick}>Réserver ces places</Button>)

    await userEvent.click(screen.getByRole('button', { name: 'Réserver ces places' }))

    expect(onClick).toHaveBeenCalledOnce()
  })

  it('is disabled and busy while loading', () => {
    render(<Button loading>Payer 450 MAD</Button>)
    const button = screen.getByRole('button', { name: 'Payer 450 MAD' })

    expect(button).toBeDisabled()
    expect(button).toHaveAttribute('aria-busy', 'true')
  })

  it('renders its child with button styles when asChild', () => {
    render(
      <Button asChild variant="secondary">
        <a href="/programme">Voir la programmation</a>
      </Button>,
    )

    expect(screen.getByRole('link', { name: 'Voir la programmation' })).toHaveClass('border-fg')
  })
})

describe('IconButton', () => {
  it('has an accessible name', () => {
    render(<IconButton label="Ajouter" icon={<Plus />} />)
    expect(screen.getByRole('button', { name: 'Ajouter' })).toBeInTheDocument()
  })
})

describe('StatusBadge', () => {
  it('translates API statuses', () => {
    render(<StatusBadge status="CANCELLED" />)
    expect(screen.getByText('Annulée')).toBeInTheDocument()
  })
})
