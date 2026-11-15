import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { StatePanel } from './StatePanel'

describe('StatePanel', () => {
  it('renders kicker, heading, explanation and actions', () => {
    render(
      <StatePanel
        kind="empty"
        kicker="relâche"
        title="Rien ce mois-ci"
        actions={<button>Go</button>}
      >
        Essayez un autre mois.
      </StatePanel>,
    )
    expect(screen.getByRole('heading', { level: 2, name: 'Rien ce mois-ci' })).toBeInTheDocument()
    expect(screen.getByText('relâche')).toBeInTheDocument()
    expect(screen.getByText('Essayez un autre mois.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Go' })).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('announces errors and can be a page heading', () => {
    render(<StatePanel kind="error" kicker="incident" title="Interrompu" headingLevel={1} />)
    expect(screen.getByRole('alert')).toHaveTextContent('Interrompu')
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument()
  })
})
