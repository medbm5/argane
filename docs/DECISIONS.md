# Décisions

Choix techniques et réponses aux questions du challenge. Chaque entrée indique le choix, la raison et les alternatives écartées.

---

## ADR-001 — TypeScript 6.0 et ESLint 9 côté frontend

**Choix.** Le frontend est épinglé sur `typescript@~6.0` et `eslint@^9`.

**Raison.** Le gabarit Vite installe TypeScript 7 (compilateur natif), mais `typescript-eslint` exige `typescript <6.1` : le lint typé (`strictTypeChecked`) en dépend, tout comme `openapi-typescript` plus tard. `eslint-plugin-jsx-a11y`, indispensable pour les objectifs d'accessibilité du plan de salle, ne supporte pas encore ESLint 10.

**Alternatives.** Garder TypeScript 7 avec `oxlint` (perte des règles typées et de jsx-a11y) ; utiliser `--legacy-peer-deps` (fragile). À réévaluer quand l'écosystème supportera TS 7 et ESLint 10.

**Note (Slice 9).** `openapi-typescript@7` déclare encore `typescript ^5` en pair. Il n'utilise le compilateur que pour imprimer des types : un `overrides` npm ciblé (`"openapi-typescript": { "typescript": "$typescript" }`) le fait tourner sur TS 6 sans `--legacy-peer-deps` global. Les types générés (`src/lib/api/schema.d.ts`) sont versionnés pour que le frontend compile sans backend démarré.

---

## ADR-002 — Polices auto-hébergées (@fontsource)

**Choix.** Cormorant Garamond et Instrument Sans sont servies depuis le bundle via `@fontsource`, et non depuis Google Fonts.

**Raison.** Aucune requête vers un tiers (RGPD, pas de dépendance réseau supplémentaire pendant un démarrage à froid), mise en cache avec les assets Vite versionnés.

**Alternatives.** Lien Google Fonts : plus simple, mais requête tierce et texte invisible plus long au premier chargement.

---

## ADR-003 — Authentification déléguée à Clerk, rôles chez nous

**Choix.** Clerk gère l'inscription, la connexion et les sessions. L'API vérifie localement le JWT de session (RS256) contre le JWKS de Clerk mis en cache une heure, avec une tolérance de 5 s sur `exp`/`nbf`, l'émetteur attendu et la liste blanche `azp`. Un `kid` inconnu déclenche un seul rafraîchissement du JWKS (rotation de clés). Les rôles (`CUSTOMER`, `BOX_OFFICE`, `ORGANIZER`) sont stockés dans notre table `app_user` ; l'utilisateur local est créé à la première requête authentifiée (JIT) via la Backend API de Clerk.

**Raison.** Pas de mots de passe à stocker ni de flux de récupération à écrire ; le palier gratuit suffit. La vérification locale évite un appel réseau par requête. Garder les rôles chez nous découple l'autorisation métier du fournisseur et permet `app:user:grant` sans console Clerk.

**Alternatives.** Auth maison (Symfony + mots de passe) : plus de surface de sécurité à maintenir. Rôles dans les métadonnées Clerk : dépendance au fournisseur pour une règle métier, et JWT à rafraîchir après chaque changement de rôle.

## ADR-004 — Plan de salle : géométrie et disponibilité séparées, polling avec ETag

**Contexte.** Le plan (400 à 460 places) change rarement ; l'état des places change en continu pendant les ventes.

**Décision.** Deux endpoints : `/seatmap` (géométrie, zones, prix autorisés pour l'appelant, cache privé 60 s) et `/availability` (uniquement les places *non* disponibles + stock debout, `Cache-Control: no-cache` + `ETag`). Le front interroge `/availability` toutes les 5 s ; le navigateur revalide seul avec `If-None-Match` et reçoit un 304 sans corps quand rien n'a bougé. Aucune gestion manuelle de l'ETag côté client.

**Conséquences.** Réponse de quelques centaines d'octets au pire, 304 la plupart du temps. Mercure/SSE reste une option (Slice 20) si la latence de 5 s devient gênante.

## ADR-005 — Contraste : un rouge « texte » distinct du rouge « aplat »

**Contexte.** La direction « mural » utilise un rouge vif (#e8432e). En texte sur le fond crème il ne fait que 3,56:1, et un texte blanc sur ce rouge 3,75:1 : sous le seuil WCAG AA (4,5:1) pour le texte courant.

**Décision.** Deux jetons : `--pop-red` pour les aplats (blocs, barres, sièges), `--red-text` (#c22f1c, 5,06:1 sur crème) pour les rôles texte (`accent`, `highlight`, `danger`). Sur fond sombre (thème nuit, en-têtes), le rouge vif reste le rouge texte (4,8:1). Les libellés posés sur un aplat rouge passent en encre (#15121e). Ratios vérifiés à la main (outil de calcul WCAG) ; axe-core couvre le reste dans les tests (le contraste n'est pas calculable en jsdom).

**Conséquences.** Le rendu reste vif ; seuls les petits textes rouges sont un peu plus sombres.

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
