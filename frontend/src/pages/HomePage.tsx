import { useEvents } from '../features/catalog/api'
import { LandingHero } from '../features/catalog/landing/LandingHero'
import { ProgrammeSearchBar } from '../features/catalog/landing/ProgrammeSearchBar'
import { UpcomingPerformances } from '../features/catalog/landing/UpcomingPerformances'

export function HomePage() {
  const events = useEvents()
  const list = events.data ?? []

  return (
    <div className="mx-auto max-w-7xl border-x border-line">
      <ProgrammeSearchBar events={list} />
      <LandingHero />
      <UpcomingPerformances events={list} isLoading={events.isPending} />
    </div>
  )
}
