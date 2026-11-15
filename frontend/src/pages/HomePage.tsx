import { useEvents } from '../features/catalog/api'
import { LandingGrid } from '../features/catalog/landing/LandingGrid'
import { LandingHero } from '../features/catalog/landing/LandingHero'
import { ProgrammeSearchBar } from '../features/catalog/landing/ProgrammeSearchBar'
import { UpcomingPerformances } from '../features/catalog/landing/UpcomingPerformances'

export function HomePage() {
  const events = useEvents()
  const list = events.data ?? []

  return (
    <div className="mx-auto max-w-7xl">
      <ProgrammeSearchBar events={list} />
      <LandingHero events={list} />
      <UpcomingPerformances events={list} isLoading={events.isPending} />
      <LandingGrid events={list} />
    </div>
  )
}
