#!/usr/bin/env node
/**
 * Generates the typographic, duotone demo posters in public/posters/.
 * No stock photos: each poster is a geometric motif + the title, in two tones
 * of the sepia palette with a paper grain. Run: `npm run posters`.
 */
import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const OUT = join(dirname(fileURLToPath(import.meta.url)), '..', 'public', 'posters')

const W = 600
const H = 800

/** Duotones: [dark, light, accent] taken from tokens.css. */
const TONES = {
  bordeaux: ['#3B1014', '#F2E8D5', '#D4A657'],
  ink: ['#2B1A10', '#F2E8D5', '#B08A5A'],
  sepia: ['#4A2C17', '#FBF6EC', '#D4A657'],
  olive: ['#2E3520', '#F2E8D5', '#D4A657'],
  night: ['#140E0A', '#E9B96E', '#B08A5A'],
  rust: ['#5A2216', '#FBF6EC', '#E9B96E'],
}

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')

/** Motifs are drawn in the light tone over the dark background. */
const MOTIFS = {
  // Qraqeb (metal castanets) as pairs of rings, in a trance spiral.
  qraqeb: (l, a) =>
    Array.from({ length: 9 }, (_, i) => {
      const t = i / 9
      const r = 60 + i * 22
      const x = 300 + Math.cos(t * Math.PI * 4) * r * 0.55
      const y = 300 + Math.sin(t * Math.PI * 4) * r * 0.45
      return `<g opacity="${(0.35 + t * 0.65).toFixed(2)}"><ellipse cx="${x - 16}" cy="${y}" rx="22" ry="12" fill="none" stroke="${i % 3 ? l : a}" stroke-width="3"/><ellipse cx="${x + 16}" cy="${y}" rx="22" ry="12" fill="none" stroke="${i % 3 ? l : a}" stroke-width="3"/></g>`
    }).join(''),
  // A salon mirror framing a single upright line: Alceste alone.
  mirror: (l, a) =>
    `<ellipse cx="300" cy="300" rx="150" ry="200" fill="none" stroke="${l}" stroke-width="2"/>
     <ellipse cx="300" cy="300" rx="132" ry="182" fill="none" stroke="${l}" stroke-width="1" opacity=".5"/>
     <line x1="300" y1="170" x2="300" y2="430" stroke="${a}" stroke-width="6"/>
     <circle cx="300" cy="150" r="14" fill="${a}"/>`,
  // The moon and a plume.
  moon: (l, a) =>
    `<circle cx="330" cy="270" r="150" fill="${l}"/>
     <circle cx="390" cy="230" r="140" fill="var(--bg)"/>
     <path d="M180 470 C 260 380, 330 330, 430 320 C 360 360, 300 410, 250 480 Z" fill="${a}"/>
     <path d="M200 460 L 420 330" stroke="${l}" stroke-width="1.5" opacity=".6"/>`,
  // Four strings, four seasons.
  strings: (l, a) =>
    Array.from({ length: 4 }, (_, i) => {
      const x = 180 + i * 80
      return `<line x1="${x}" y1="110" x2="${x}" y2="500" stroke="${l}" stroke-width="${2 + i}"/><circle cx="${x}" cy="${180 + i * 85}" r="${18 + i * 4}" fill="${i === 3 ? a : l}" opacity="${0.5 + i * 0.15}"/>`
    }).join(''),
  // Atlantic swell lines.
  waves: (l, a) =>
    Array.from({ length: 12 }, (_, i) => {
      const y = 140 + i * 30
      const amp = 10 + (i % 4) * 6
      return `<path d="M60 ${y} Q 135 ${y - amp} 210 ${y} T 360 ${y} T 510 ${y} T 660 ${y}" fill="none" stroke="${i === 7 ? a : l}" stroke-width="${i === 7 ? 4 : 2}" opacity="${(0.4 + (i / 12) * 0.6).toFixed(2)}"/>`
    }).join('') + `<path d="M300 120 l 18 40 h -36 z" fill="${a}"/>`,
  // Ahwach circle of dancers.
  circle: (l, a) =>
    Array.from({ length: 14 }, (_, i) => {
      const t = (i / 14) * Math.PI * 2
      const x = 300 + Math.cos(t) * 170
      const y = 300 + Math.sin(t) * 170
      return `<g transform="translate(${x.toFixed(1)} ${y.toFixed(1)}) rotate(${((t * 180) / Math.PI + 90).toFixed(1)})"><circle r="10" cy="-30" fill="${l}"/><path d="M-12 -14 L12 -14 L6 30 L-6 30 Z" fill="${l}"/></g>`
    }).join('') +
    `<circle cx="300" cy="300" r="70" fill="none" stroke="${a}" stroke-width="5"/><circle cx="300" cy="300" r="8" fill="${a}"/>`,
  // A grin made of the setting sun.
  grin: (l, a) =>
    `<circle cx="300" cy="300" r="190" fill="none" stroke="${l}" stroke-width="2"/>
     <path d="M170 300 A 130 130 0 0 0 430 300 Z" fill="${a}"/>
     <circle cx="230" cy="230" r="18" fill="${l}"/><path d="M350 230 q 20 -26 40 0" stroke="${l}" stroke-width="8" fill="none" stroke-linecap="round"/>
     <line x1="60" y1="300" x2="540" y2="300" stroke="${l}" stroke-width="1" opacity=".5"/>`,
  // Night bay, piano keys as the shoreline.
  bay: (l, a) =>
    `<circle cx="420" cy="180" r="60" fill="${a}"/>` +
    Array.from(
      { length: 14 },
      (_, i) =>
        `<rect x="${60 + i * 34}" y="380" width="${i % 7 === 2 || i % 7 === 6 ? 16 : 30}" height="${i % 7 === 2 || i % 7 === 6 ? 70 : 120}" fill="${l}" opacity="${i % 7 === 2 || i % 7 === 6 ? 1 : 0.85}"/>`,
    ).join('') +
    `<path d="M60 360 Q 300 300 540 360" stroke="${l}" stroke-width="2" fill="none"/>`,
}

const POSTERS = [
  {
    slug: 'tamra-nuits-gnaoua',
    title: ['Tamra'],
    subtitle: 'Nuits gnaoua',
    category: 'Concert',
    tone: 'night',
    motif: 'qraqeb',
  },
  {
    slug: 'le-misanthrope',
    title: ['Le', 'Misanthrope'],
    subtitle: 'Molière',
    category: 'Théâtre',
    tone: 'bordeaux',
    motif: 'mirror',
  },
  {
    slug: 'cyrano-de-bergerac',
    title: ['Cyrano', 'de Bergerac'],
    subtitle: 'Edmond Rostand',
    category: 'Théâtre',
    tone: 'ink',
    motif: 'moon',
  },
  {
    slug: 'quatuor-ambre-les-saisons',
    title: ['Les Saisons'],
    subtitle: 'Quatuor Ambre',
    category: 'Concert',
    tone: 'sepia',
    motif: 'strings',
  },
  {
    slug: 'l-atlantique-memoire-des-marins',
    title: ['L’Atlantique'],
    subtitle: 'Mémoire des marins',
    category: 'Conférence',
    tone: 'olive',
    motif: 'waves',
  },
  {
    slug: 'ahwach-corps-et-cercle',
    title: ['Ahwach'],
    subtitle: 'Corps et cercle',
    category: 'Danse',
    tone: 'rust',
    motif: 'circle',
  },
  {
    slug: 'rire-a-l-ouest',
    title: ['Rire', 'à l’ouest'],
    subtitle: 'Plateau humour',
    category: 'Humour',
    tone: 'sepia',
    motif: 'grin',
  },
  {
    slug: 'jazz-sur-la-baie',
    title: ['Jazz', 'sur la baie'],
    subtitle: 'Trio Lune Noire',
    category: 'Concert',
    tone: 'night',
    motif: 'bay',
  },
]

function poster({ title, subtitle, category, tone, motif }) {
  const [dark, light, accent] = TONES[tone]
  const size = title.some((line) => line.length > 9) ? 72 : 96
  const titleLines = title
    .map(
      (line, i) =>
        `<text x="48" y="${600 + i * (size * 0.95)}" font-size="${size}">${esc(line)}</text>`,
    )
    .join('')
  const art = MOTIFS[motif](light, accent).replaceAll('var(--bg)', dark)

  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" width="${W}" height="${H}" role="img" aria-label="${esc(title.join(' '))} — affiche">
  <defs>
    <filter id="grain" x="0" y="0" width="100%" height="100%">
      <feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="2" seed="7" result="n"/>
      <feColorMatrix in="n" type="matrix" values="0 0 0 0 0.95  0 0 0 0 0.9  0 0 0 0 0.83  0 0 0 0.09 0"/>
      <feComposite in2="SourceGraphic" operator="in"/>
    </filter>
  </defs>
  <rect width="${W}" height="${H}" fill="${dark}"/>
  <g>${art}</g>
  <g fill="${light}" font-family="'Cormorant Garamond', Georgia, 'Times New Roman', serif" font-weight="600" letter-spacing="-1">${titleLines}</g>
  <text x="50" y="${H - 52}" fill="${accent}" font-family="'Instrument Sans', system-ui, sans-serif" font-size="18" letter-spacing="3">${esc(subtitle.toUpperCase())}</text>
  <text x="${W - 48}" y="64" fill="${light}" text-anchor="end" font-family="'Instrument Sans', system-ui, sans-serif" font-size="14" letter-spacing="4" opacity=".8">${esc(category.toUpperCase())} · SAISON 26/27</text>
  <text x="48" y="64" fill="${light}" font-family="'Cormorant Garamond', Georgia, serif" font-size="22" font-style="italic" opacity=".9">Théâtre Argane</text>
  <rect width="${W}" height="${H}" filter="url(#grain)"/>
</svg>
`
}

mkdirSync(OUT, { recursive: true })
for (const p of POSTERS) {
  writeFileSync(join(OUT, `${p.slug}.svg`), poster(p), 'utf8')
  console.log(`posters/${p.slug}.svg`)
}
