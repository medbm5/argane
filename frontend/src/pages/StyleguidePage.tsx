import { Heart, Ticket } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { DotSparkline } from '../design-system/components/DotSparkline'
import { HalfGauge } from '../design-system/components/HalfGauge'
import { KpiTile } from '../design-system/components/KpiTile'
import { RotatingBadge } from '../design-system/components/RotatingBadge'
import { TicketStub } from '../design-system/components/TicketStub'
import { WaveMask } from '../design-system/components/WaveMask'
import { Badge, StatusBadge } from '../design-system/primitives/Badge'
import { Button } from '../design-system/primitives/Button'
import { Dialog } from '../design-system/primitives/Dialog'
import { IconButton } from '../design-system/primitives/IconButton'
import { Input } from '../design-system/primitives/Input'
import { Pill } from '../design-system/primitives/Pill'
import { Select } from '../design-system/primitives/Select'
import { Sheet } from '../design-system/primitives/Sheet'
import { Stepper } from '../design-system/primitives/Stepper'
import { Tabs } from '../design-system/primitives/Tabs'
import { toast } from '../design-system/toast-store'
import { usePageTitle } from '../lib/usePageTitle'

const SWATCHES = [
  ['paper-50', '#FBF6EC'],
  ['paper-100', '#F2E8D5'],
  ['paper-200', '#E6D7BC'],
  ['paper-300', '#D3BF9C'],
  ['sepia-400', '#B08A5A'],
  ['sepia-600', '#704214'],
  ['sepia-800', '#4A2C17'],
  ['ink-900', '#2B1A10'],
  ['bordeaux-700', '#6B1E24'],
  ['gilt-400', '#D4A657'],
  ['olive-600', '#5B6B3A'],
  ['rust-700', '#9A3A26'],
  ['night-950', '#140E0A'],
  ['night-900', '#1E1510'],
  ['night-800', '#2A1E16'],
  ['night-600', '#4A3626'],
  ['stage-glow', '#E9B96E'],
] as const

const TYPE_SCALE = [
  ['xs', 'text-xs'],
  ['sm', 'text-sm'],
  ['base', 'text-base'],
  ['lg', 'text-lg'],
  ['xl', 'text-xl'],
  ['2xl', 'text-2xl'],
  ['3xl', 'text-3xl'],
  ['4xl', 'text-4xl'],
] as const

/** WCAG relative luminance contrast, used to show the ratio under each text pair. */
function contrast(a: string, b: string): number {
  const lum = (hex: string) => {
    const [r, g, bl] = [1, 3, 5].map((i) => {
      const c = parseInt(hex.slice(i, i + 2), 16) / 255
      return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
    }) as [number, number, number]
    return 0.2126 * r + 0.7152 * g + 0.0722 * bl
  }
  const [hi, lo] = [lum(a), lum(b)].sort((x, y) => y - x) as [number, number]
  return (hi + 0.05) / (lo + 0.05)
}

const PAIRS = [
  ['Encre sur papier', '#2B1A10', '#F2E8D5'],
  ['Sépia sur papier', '#704214', '#F2E8D5'],
  ['Papier sur nuit', '#F2E8D5', '#140E0A'],
  ['Sépia 400 sur nuit', '#B08A5A', '#140E0A'],
] as const

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="border-t border-line py-10">
      <h2 className="mb-6 font-display text-3xl">{title}</h2>
      {children}
    </section>
  )
}

export function StyleguidePage() {
  usePageTitle('Charte graphique')
  const [qty, setQty] = useState(2)
  const [tariff, setTariff] = useState<string | undefined>('FULL')
  const [pill, setPill] = useState('ven')

  return (
    <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
      <header className="mb-8">
        <p className="small-caps text-sm text-accent">Système de design</p>
        <h1 className="font-display text-hero leading-none">Styleguide</h1>
        <p className="mt-4 max-w-prose text-muted">
          Sépia, cérémoniel, théâtral. Papier de programme le jour, salle obscure au moment de
          choisir sa place.
        </p>
      </header>

      <Section title="Couleurs">
        <ul className="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6">
          {SWATCHES.map(([name, hex]) => (
            <li key={name} className="overflow-hidden rounded-md border border-line bg-surface">
              <div className="h-16" style={{ background: `var(--${name})` }} />
              <div className="p-2 text-xs">
                <p className="font-medium">--{name}</p>
                <p className="tabular text-muted">{hex}</p>
              </div>
            </li>
          ))}
        </ul>
        <h3 className="mt-8 mb-3 font-medium">Contraste du texte</h3>
        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {PAIRS.map(([label, fg, bg]) => {
            const ratio = contrast(fg, bg)
            return (
              <li key={label} className="rounded-md p-4" style={{ color: fg, background: bg }}>
                <p>{label}</p>
                <p className="tabular text-sm">
                  {ratio.toFixed(2)}:1 {ratio >= 4.5 ? '· AA' : '· < AA'}
                </p>
              </li>
            )
          })}
        </ul>
      </Section>

      <Section title="Typographie">
        <p className="font-display text-hero leading-none">Le rideau se lève.</p>
        <ul className="mt-6 space-y-2">
          {TYPE_SCALE.map(([size, cls]) => (
            <li key={size} className="flex items-baseline gap-4">
              <span className="tabular w-12 text-xs text-muted">{size}</span>
              <span className={`font-display ${cls}`}>Chaque place raconte une histoire</span>
            </li>
          ))}
        </ul>
        <p className="mt-6 max-w-prose">
          Instrument Sans pour le texte courant et les interfaces. Les prix et les compteurs
          utilisent des chiffres tabulaires : <span className="tabular">1 250,00 MAD</span>.
        </p>
        <p className="small-caps mt-2 text-accent">Rang M · Place 14</p>
      </Section>

      <Section title="Boutons et badges">
        <div className="flex flex-wrap items-center gap-3">
          <Button>Réserver ces places</Button>
          <Button variant="secondary">Voir la programmation</Button>
          <Button variant="ghost">Annuler</Button>
          <Button variant="gilt" size="lg">
            Payer 450 MAD
          </Button>
          <Button variant="danger">Annuler la commande</Button>
          <Button loading>Paiement…</Button>
          <IconButton label="Ajouter aux favoris" icon={<Heart className="size-4" />} />
        </div>
        <div className="mt-6 flex flex-wrap gap-2">
          {['ON_SALE', 'SOLD_OUT', 'CANCELLED', 'PAID', 'PENDING_PAYMENT', 'DRAFT'].map((s) => (
            <StatusBadge key={s} status={s} />
          ))}
          <Badge tone="gilt">
            <Ticket className="size-3" aria-hidden /> Tarif réduit
          </Badge>
        </div>
      </Section>

      <Section title="Formulaires">
        <div className="grid gap-6 sm:grid-cols-3">
          <Input label="Nom du client" placeholder="Fatima Zahra" />
          <Input label="Email" type="email" error="Adresse invalide." defaultValue="fz@" />
          <Select
            label="Tarif"
            value={tariff}
            onValueChange={setTariff}
            options={[
              { value: 'FULL', label: 'Plein' },
              { value: 'REDUCED', label: 'Réduit', description: 'Justificatif à l’entrée' },
              { value: 'GROUP', label: 'Groupe', disabled: true },
            ]}
          />
        </div>
        <div className="mt-6 flex flex-wrap items-center gap-6">
          <Stepper label="Fosse debout" value={qty} onChange={setQty} min={0} max={10} />
          <div className="flex gap-2">
            {[
              { key: 'ven', label: 'Ven. 12' },
              { key: 'sam', label: 'Sam. 13' },
              { key: 'dim', label: 'Dim. 14' },
            ].map(({ key, label }) => (
              <Pill
                key={key}
                selected={pill === key}
                onClick={() => {
                  setPill(key)
                }}
              >
                {label}
              </Pill>
            ))}
          </div>
        </div>
      </Section>

      <Section title="Superpositions">
        <div className="flex flex-wrap gap-3">
          <Dialog
            title="Votre réservation a expiré"
            description="Les places sont de nouveau disponibles. Choisissez-les à nouveau sur le plan."
            trigger={<Button variant="secondary">Ouvrir un dialogue</Button>}
          />
          <Sheet
            title="Votre sélection"
            trigger={<Button variant="secondary">Ouvrir un volet</Button>}
          >
            <p className="text-muted">Deux places en Orchestre, rang C.</p>
          </Sheet>
          <Button
            variant="secondary"
            onClick={() =>
              toast('Commande annulée', { tone: 'success', description: 'Remboursement en cours.' })
            }
          >
            Afficher un toast
          </Button>
        </div>
        <Tabs
          className="mt-6"
          label="Exemple d’onglets"
          defaultValue="a"
          items={[
            {
              value: 'a',
              label: 'À venir',
              content: <p className="py-4">Trois représentations.</p>,
            },
            { value: 'b', label: 'Passées', content: <p className="py-4">Une représentation.</p> },
          ]}
        />
      </Section>

      <Section title="Décor">
        <div className="grid items-center gap-8 sm:grid-cols-3">
          <RotatingBadge
            text="Saison 26/27 · Théâtre Argane · "
            center={<span className="font-display text-2xl">26/27</span>}
          />
          <WaveMask className="aspect-[3/4] w-48">
            <div className="duotone size-full bg-gradient-to-br from-gilt-400 to-bordeaux-700" />
          </WaveMask>
          <div className="paper-grain h-40 rounded-md bg-paper-200 p-4 text-sm text-muted">
            Grain papier
          </div>
        </div>
        <TicketStub
          className="mt-8"
          eventTitle="Le Misanthrope"
          dateLabel="Vendredi 12 décembre 2026"
          timeLabel="20:00"
          zoneLabel="Orchestre"
          seatLabel="C-12"
          tariffLabel="Réduit"
          priceLabel="120,00 MAD"
          reference="ARG-26-000123"
          status="VALID"
          requiresProof
        />
      </Section>

      <Section title="Données">
        <div className="grid gap-4 sm:grid-cols-3">
          <KpiTile
            label="Recette nette"
            value="84 250 MAD"
            delta={0.124}
            chart={
              <DotSparkline values={[3, 5, 4, 8, 6, 9, 12, 10, 14]} label="Recette par jour" />
            }
          />
          <KpiTile label="Taux de remboursement" value="2,1 %" delta={-0.3} invertDelta />
          <div className="rounded-md border border-line bg-surface p-5">
            <HalfGauge
              centerLabel="72 %"
              centerCaption="Remplissage moyen"
              segments={[
                { label: 'Vendues', value: 288, className: 'stroke-bordeaux-700' },
                { label: 'Réservées', value: 12, className: 'stroke-gilt-400' },
                { label: 'Libres', value: 100, className: 'stroke-paper-300' },
              ]}
            />
          </div>
        </div>
      </Section>

      <div data-theme="night" className="mt-4 rounded-md bg-bg p-8 text-fg">
        <p className="small-caps text-sm text-accent">Thème nuit</p>
        <p className="font-display text-3xl">La salle s’éteint.</p>
        <div className="mt-4 flex gap-3">
          <Button variant="gilt">Payer 900 MAD</Button>
          <Button variant="secondary">Changer de date</Button>
        </div>
      </div>
    </div>
  )
}
