# Décisions

Choix techniques et réponses aux questions du challenge. Chaque entrée indique le choix, la raison et les alternatives écartées.

---

## ADR-001 — TypeScript 6.0 et ESLint 9 côté frontend

**Choix.** Le frontend est épinglé sur `typescript@~6.0` et `eslint@^9`.

**Raison.** Le gabarit Vite installe TypeScript 7 (compilateur natif), mais `typescript-eslint` exige `typescript <6.1` : le lint typé (`strictTypeChecked`) en dépend, tout comme `openapi-typescript` plus tard. `eslint-plugin-jsx-a11y`, indispensable pour les objectifs d'accessibilité du plan de salle, ne supporte pas encore ESLint 10.

**Alternatives.** Garder TypeScript 7 avec `oxlint` (perte des règles typées et de jsx-a11y) ; utiliser `--legacy-peer-deps` (fragile). À réévaluer quand l'écosystème supportera TS 7 et ESLint 10.
