import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { RotatingBadge } from './RotatingBadge'
import { TicketStub } from './TicketStub'
import { WaveMask } from './WaveMask'

describe('TicketStub', () => {
  it('announces event, zone and seat', () => {
    render(
      <TicketStub
        eventTitle="Le Misanthrope"
        dateLabel="sam. 5 déc."
        timeLabel="20:30"
        zoneLabel="Orchestre"
        seatLabel="F12"
        tariffLabel="Réduit"
        priceLabel="120 MAD"
        reference="ARG-26-000123"
        status="VALID"
        requiresProof
      />,
    )

    expect(
      screen.getByRole('article', { name: 'Billet Le Misanthrope, Orchestre, place F12' }),
    ).toBeInTheDocument()
    expect(screen.getByText(/Justificatif/)).toBeInTheDocument()
  })

  it('labels standing tickets', () => {
    render(
      <TicketStub
        eventTitle="Tamra"
        dateLabel="ven. 15 janv."
        timeLabel="21:00"
        zoneLabel="Fosse debout"
        seatLabel={null}
        tariffLabel="Plein"
        priceLabel="200 MAD"
        reference="ARG-26-000124"
      />,
    )
    expect(screen.getByText('Debout')).toBeInTheDocument()
  })
})

describe('RotatingBadge', () => {
  it('keeps the circular text readable for screen readers', () => {
    render(<RotatingBadge text="Saison 26/27 · Théâtre Argane · " />)
    // The SVG copy is aria-hidden; only the sr-only span should reach assistive tech.
    expect(
      screen.getByText('Saison 26/27 · Théâtre Argane ·', {
        ignore: '[aria-hidden] *, script, style',
      }),
    ).toHaveClass('sr-only')
  })
})

describe('WaveMask', () => {
  it('renders its children', () => {
    render(
      <WaveMask>
        <img alt="Affiche" src="/posters/tamra.svg" />
      </WaveMask>,
    )
    expect(screen.getByAltText('Affiche')).toBeInTheDocument()
  })
})
