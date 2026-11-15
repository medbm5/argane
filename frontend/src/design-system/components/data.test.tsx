import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { DotSparkline } from './DotSparkline'
import { HalfGauge } from './HalfGauge'
import { KpiTile } from './KpiTile'

describe('KpiTile', () => {
  it('shows a positive delta as good', () => {
    render(<KpiTile label="Recette nette" value="12 400 MAD" delta={0.12} />)
    const delta = screen.getByText(/12 %/)
    expect(delta).toHaveClass('text-success')
  })

  it('treats a rise as bad when the metric is inverted', () => {
    render(<KpiTile label="Taux de remboursement" value="3 %" delta={0.5} invertDelta />)
    expect(screen.getByText(/50 %/)).toHaveClass('text-danger')
  })

  it('hides the delta when there is no previous period', () => {
    render(<KpiTile label="Billets" value="0" delta={null} />)
    expect(screen.queryByText(/%/)).not.toBeInTheDocument()
  })
})

describe('DotSparkline', () => {
  it('lights dots in proportion to the maximum', () => {
    const { container } = render(<DotSparkline values={[0, 5, 10]} rows={4} />)
    expect(container.querySelectorAll('circle')).toHaveLength(12)
    // 0 + 2 + 4 lit dots
    expect(container.querySelectorAll('circle[data-on]')).toHaveLength(6)
  })

  it('handles an all-zero series', () => {
    const { container } = render(<DotSparkline values={[0, 0]} />)
    expect(container.querySelectorAll('circle[data-on]')).toHaveLength(0)
  })
})

describe('HalfGauge', () => {
  it('renders the centre label and a legend entry per segment', () => {
    render(
      <HalfGauge
        centerLabel="72 %"
        segments={[
          { label: 'Vendues', value: 288, className: 'stroke-bordeaux-700' },
          { label: 'Réservées', value: 12, className: 'stroke-gilt-400' },
          { label: 'Libres', value: 100, className: 'stroke-paper-300' },
        ]}
      />,
    )
    expect(screen.getByText('72 %')).toBeInTheDocument()
    expect(screen.getByText('Vendues')).toBeInTheDocument()
    expect(screen.getByText('288')).toBeInTheDocument()
  })
})
