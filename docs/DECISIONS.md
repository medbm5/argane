# Décisions

Choix techniques et réponses aux questions du challenge. Chaque entrée indique le choix, la raison et les alternatives écartées.

---

## ADR-001 — TypeScript 6.0 et ESLint 9 côté frontend

**Choix.** Le frontend est épinglé sur `typescript@~6.0` et `eslint@^9`.

**Raison.** Le gabarit Vite installe TypeScript 7 (compilateur natif), mais `typescript-eslint` exige `typescript <6.1` : le lint typé (`strictTypeChecked`) en dépend, tout comme `openapi-typescript` plus tard. `eslint-plugin-jsx-a11y`, indispensable pour les objectifs d'accessibilité du plan de salle, ne supporte pas encore ESLint 10.

**Alternatives.** Garder TypeScript 7 avec `oxlint` (perte des règles typées et de jsx-a11y) ; utiliser `--legacy-peer-deps` (fragile). À réévaluer quand l'écosystème supportera TS 7 et ESLint 10.

---

## Questions du challenge

> Réponses complètes rédigées au fil des slices et finalisées en Slice 22.

### 1. Le prix d'un billet déjà vendu change-t-il si le tarif est modifié ?
Non. Le prix, le libellé du tarif et la place sont figés sur le billet au moment de la vente. *(Slice 7)*

### 2. Une représentation peut-elle utiliser une configuration de salle différente ?
Oui. Chaque représentation référence une configuration (théâtre ou concert). Une configuration utilisée par des ventes est verrouillée ; on la clone pour la modifier. *(Slice 4)*

### 3. Que devient une place dont le paiement n'aboutit pas ?
Elle reste bloquée jusqu'à l'expiration de la réservation (10 min), puis redevient disponible. Un paiement tardif reprend la place si elle est libre, sinon il est remboursé automatiquement. *(Slices 11–12)*

### 4. Que se passe-t-il pour les billets si une représentation est annulée ?
Ventes stoppées, billets invalidés, remboursement automatique des paiements en ligne, remboursement au guichet pour les ventes guichet, email aux clients. *(Slice 14)*

### 5. Qui a le droit de vendre à quel tarif ?
Matrice canal × rôle × quantité appliquée côté serveur par `TariffEligibilityPolicy`. *(Slice 6)*
