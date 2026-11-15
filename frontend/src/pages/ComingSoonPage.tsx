/** Placeholder for areas built in later slices. */
export function ComingSoonPage({ title }: { title: string }) {
  return (
    <section className="mx-auto max-w-3xl px-6 py-20">
      <p className="small-caps text-sm text-accent">En préparation</p>
      <h1 className="mt-2 font-display text-5xl">{title}</h1>
      <p className="mt-4 text-muted">Cet espace ouvre bientôt.</p>
    </section>
  )
}
