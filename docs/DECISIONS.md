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

**Conséquences.** Réponse de quelques centaines d'octets au pire, 304 la plupart du temps. Mercure/SSE (intégré à FrankenPHP) reste une option si la latence de 5 s devient gênante ; non livré.

## ADR-005 — Contraste : un rouge « texte » distinct du rouge « aplat »

**Contexte.** La direction « mural » utilise un rouge vif (#e8432e). En texte sur le fond crème il ne fait que 3,56:1, et un texte blanc sur ce rouge 3,75:1 : sous le seuil WCAG AA (4,5:1) pour le texte courant.

**Décision.** Deux jetons : `--pop-red` pour les aplats (blocs, barres, sièges), `--red-text` (#c22f1c, 5,06:1 sur crème) pour les rôles texte (`accent`, `highlight`, `danger`). Sur fond sombre (thème nuit, en-têtes), le rouge vif reste le rouge texte (4,8:1). Les libellés posés sur un aplat rouge passent en encre (#15121e). Ratios vérifiés à la main (outil de calcul WCAG) ; axe-core couvre le reste dans les tests (le contraste n'est pas calculable en jsdom).

**Conséquences.** Le rendu reste vif ; seuls les petits textes rouges sont un peu plus sombres.

## ADR-006 — Contrôleurs simples plutôt qu'API Platform

**Choix.** Des contrôleurs Symfony fins (`Api/`) qui délèguent à des services applicatifs et renvoient des DTO en lecture ; les types TypeScript sont générés depuis un schéma OpenAPI.

**Raison.** Presque chaque écriture est une commande métier (réserver, payer, annuler, scanner) et non un CRUD de ressource. Les règles vivent dans les services, testables sans HTTP ; les contrôleurs restent lisibles en une page.

**Alternatives.** API Platform : très productif pour le CRUD (Studio), mais les opérations métier deviennent des *processors* et des *state providers* qui dispersent la logique, pour peu de gain ici.

## ADR-007 — Une place vendue une seule fois : UPDATE conditionnel

**Choix.** La réservation d'un fauteuil est un seul `UPDATE` sur l'inventaire de la représentation, limité aux places `AVAILABLE` ou dont la réservation a expiré. Si le nombre de lignes modifiées ne correspond pas, la transaction est annulée et le client apprend quelles places il a perdues. Les places debout verrouillent la ligne de stock (`FOR UPDATE`) et somment les réservations actives. Une contrainte unique sur `ticket.active_seat_key` (renseignée seulement pour les billets valides) est le filet de sécurité final.

**Raison.** L'atomicité vient de MySQL, sans verrou applicatif ; une place ne coûte qu'une ligne. Testé par `ParallelHoldTest` : plusieurs processus PHP réels se disputent les mêmes places, un seul gagne.

**Alternatives.** Verrou pessimiste `SELECT … FOR UPDATE` sur chaque fauteuil (plus de verrous tenus, risque d'interblocage selon l'ordre), verrou optimiste par version (plus de nouvelles tentatives sous forte demande), verrou Redis (un service de plus à héberger gratuitement, alors que la base reste la vérité).

## ADR-008 — Expiration paresseuse des réservations

**Choix.** Une réservation expirée est traitée comme libre dans *toutes* les requêtes (`hold_expires_at < now`). Le planificateur (toutes les minutes) ne fait que du ménage.

**Raison.** Render gratuit endort le conteneur : un traitement planifié ne peut pas garantir la correction. Avec l'expiration paresseuse, une place redevient vendable à la seconde près, que le worker tourne ou non.

**Alternatives.** Expiration uniquement par tâche planifiée (places bloquées tant que le service dort), TTL Redis (service supplémentaire).

## ADR-009 — Stripe PaymentIntents, webhook idempotent et réconciliation à la lecture

**Choix.** Paiement intégré (Payment Element + PaymentIntent) créé par l'API pour le montant calculé côté serveur. La commande passe à « payée » sur le webhook `payment_intent.succeeded` (signature vérifiée, événement enregistré une seule fois dans `stripe_webhook_event`), ou à la lecture de la commande en interrogeant Stripe si le webhook n'est pas encore arrivé. La finalisation vend tout ou rien ; un paiement arrivé après l'expiration reprend les places si elles sont libres, sinon il est remboursé automatiquement (`SaleFinalizer`).

**Raison.** Une session Stripe Checkout ne peut pas expirer en moins de 30 minutes, plus que notre réservation de 10 minutes. Le webhook seul ne suffit pas sur un hébergement qui dort : la réconciliation à la lecture rend la confirmation immédiate.

**Alternatives.** Stripe Checkout hébergé (expiration trop longue, moins de contrôle sur l'expérience), confirmation côté client seulement (falsifiable).

## ADR-010 — Google Sheets : réécriture complète, différée de 15 s

**Choix.** Après chaque vente, remboursement ou annulation, un message `SalesChanged` (différé de 15 s, dédoublonné) réécrit entièrement les onglets `Ventes` et `Synthèse`. Une synchronisation horaire sert de filet. Authentification par compte de service (JWT signé), via l'API REST.

**Raison.** Quelques milliers de lignes au plus : la réécriture complète est idempotente, corrige toute dérive et ne demande aucun état de synchronisation. Le délai regroupe les rafales de ventes en un seul appel et ménage les quotas.

**Alternatives.** Ajout ligne par ligne (dérive dès qu'un appel échoue ou qu'un remboursement modifie une ligne passée), Apps Script côté Google (logique hors du dépôt, non testée).

## ADR-011 — Identifiants UUID v7, montants en centimes

**Choix.** Toutes les entités ont un UUID v7 (stocké en `BINARY(16)`) ; tous les montants sont des entiers en centimes, avec la devise à côté (`MAD`). Les commandes ont en plus une référence lisible (`ARG-26-001268`) tirée d'une séquence.

**Raison.** Le v7 est ordonné dans le temps (index compacts) et se génère côté application avant l'insertion ; il ne révèle pas le volume de ventes. Les entiers évitent toute erreur d'arrondi.

**Alternatives.** Auto-incrément (énumérable, révèle le volume), UUID v4 (index fragmentés), `DECIMAL` (correct mais facile à mal manipuler en PHP et en JS).

## ADR-012 — Livraison sur offres gratuites : un seul conteneur

**Choix.** Render (Docker) exécute FrankenPHP et le worker Messenger dans le même conteneur sous supervisord. Au démarrage, un script décode le certificat CA d'Aiven (variable base64), écoute sur `$PORT` et applique les migrations, avec nouvelles tentatives pendant le réveil de la base. Le frontend est un site statique sur Vercel (réécriture SPA, en-têtes de sécurité, cache immuable des assets).

**Raison.** L'offre gratuite de Render n'a pas de worker séparé. Les migrations au démarrage gardent le schéma aligné sur le code sans étape manuelle ; `--all-or-nothing` évite un schéma à moitié migré. La vérification du certificat n'est activée que si un CA est fourni, ce qui laisse l'image tourner telle quelle contre le MySQL local.

**Alternatives.** Worker séparé (payant), migrations lancées à la main (oubli possible). Pas de `Content-Security-Policy` côté Vercel pour l'instant : Clerk et Stripe chargent scripts et iframes depuis plusieurs domaines, et une CSP non testée en production risquerait de casser la connexion ou le paiement. L'API, elle, envoie une CSP stricte (`default-src 'none'`).

## ADR-013 — Heure de Casablanca : une base de fuseaux à jour partout

**Constat.** Les heures sont stockées en UTC et affichées à l'heure du théâtre (`Africa/Casablanca`). Le PDF et l'email (formatés par PHP) affichaient une heure de plus que le site (formaté par le navigateur). Cause : le Maroc est repassé de UTC+1 à UTC+0 le 20 septembre 2026 (IANA tzdata 2026e). Les navigateurs et Node récents le savent ; la copie de la base intégrée à PHP (2025.2 / 2026.1) ne le savait pas.

**Décision.** L'image Docker installe l'extension `timezonedb`, qui apporte la base IANA la plus récente à PHP (vérifié : `2026.6`, 18:00 UTC le 20 novembre 2026 → 18:00 à Casablanca). Une seule règle : la base la plus récente fait foi, côté serveur comme côté navigateur. Les tests unitaires du frontend utilisent des dates antérieures au changement, sur lesquelles toutes les versions s'accordent ; ceux du backend calculent l'heure attendue via `VenueTime`.

**Conséquences.** Hors Docker, un PHP local ancien affiche encore UTC+1 : installer `timezonedb` (`pecl install timezonedb`) ou utiliser `make up`. Les données de démonstration chargées avec un PHP ancien ont des horaires décalés d'une heure ; les recharger depuis le conteneur.

## ADR-014 — Affiches téléversées : stockées dans MySQL

**Choix.** Le Studio téléverse une affiche (`POST /api/studio/posters`, organisateur) ; l'image est gardée dans la table `poster` (LONGBLOB) et servie publiquement par `GET /api/posters/{id}`, avec un cache d'un an (`immutable` : une nouvelle image reçoit un nouvel identifiant). L'événement garde seulement le chemin `/api/posters/{id}`, comme pour les affiches SVG livrées avec le site (`/posters/…`). Le frontend charge les premières depuis l'API (`mediaUrl`).

**Raison.** Le disque de Render gratuit est effacé à chaque déploiement et à chaque réveil : un fichier écrit sur le serveur disparaîtrait en moins d'une heure. Un stockage objet (R2, S3, Vercel Blob) serait la bonne réponse en production, mais demande un compte et des clés de plus pour une démo. À cette échelle (quelques affiches de 2 Mo au plus), la base suffit.

**Garde-fous.** JPEG, PNG ou WebP seulement, type lu dans les octets (jamais celui annoncé par le navigateur), 2 Mo et 6000 px au plus ; le SVG est refusé car il peut contenir du script. La réponse porte `nosniff`, une CSP `default-src 'none'` et `Cross-Origin-Resource-Policy: cross-origin` (le site est sur un autre domaine que l'API ; les autres réponses de l'API restent `same-origin`).

**Pour aller plus loin.** Passer à un stockage objet ne change que `PosterLibrary` et l'URL stockée ; les images remplacées ne sont pas encore supprimées.

## ADR-015 — Une salle, deux dispositions : on active ou désactive des places

**Choix.** Le Studio n'a plus de générateur de configurations ni de copies : la page **Salle** montre les deux dispositions fixes de la salle (Théâtre, Concert avec fosse debout) et l'organisateur y désactive ou réactive des places (une à une ou par rangée). `PUT /api/studio/layouts/{id}/seats` met à jour le plan et, dans la même transaction, toutes les dates **à venir** non annulées : une place libre (ou tenue par une réservation expirée) passe `BLOCKED`, une place bloquée redevient `AVAILABLE`. Une place vendue ou en cours de paiement n'est jamais retirée ; la réponse dit combien ont été conservées.

**Raison.** Théâtre Argane est une seule salle de 400 places : générer des plans arbitraires et verrouiller puis dupliquer une configuration pour retirer un fauteuil cassé était une mécanique de logiciel multi-salles, pas un besoin du lieu. Le verrou protège la géométrie (zones, rangées), pas l'état d'une place : bloquer reste permis sur un plan déjà utilisé.

**Conséquences.** Les dates passées gardent leur état (historique et statistiques intacts). Les points d'API du générateur restent en place mais ne sont plus utilisés par l'interface.

---

## Questions du challenge

### 1. Le prix d'un billet déjà vendu change-t-il si le tarif est modifié ?

**Non.** Au moment de la vente, le billet recopie son prix unitaire (`unit_price_cents`), le libellé du tarif, la zone et la place. Modifier ou désactiver un tarif, ou changer la grille de prix d'une représentation, n'affecte que les ventes suivantes. Le montant d'une commande est figé dès sa création.

*Pourquoi.* Un billet est une preuve d'achat : son prix ne peut pas dépendre de la configuration actuelle, ni pour le client ni pour la comptabilité et les remboursements (on rembourse ce qui a été payé).
*Alternative écartée.* Lire le prix à travers la relation au tarif : l'historique deviendrait faux au premier changement.

### 2. Une représentation peut-elle utiliser une configuration de salle différente ?

**Oui.** Chaque représentation référence une configuration (`HallLayout`) : « théâtre » (400 fauteuils) ou « concert » (fosse debout de 300 + 160 fauteuils). À la création de la représentation, l'inventaire (une ligne par fauteuil, une ligne de stock par zone debout) est généré depuis la configuration, et les prix sont fixés par zone et par tarif.

Une configuration déjà utilisée par des ventes est **verrouillée** : on la **clone** pour la modifier (Studio → Configurations).

*Pourquoi.* Les billets vendus pointent vers des places précises ; modifier la géométrie sous eux rendrait des billets invalides.
*Alternative écartée.* Une seule salle figée : ne couvre pas les concerts debout.

### 3. Que devient une place dont le paiement n'aboutit pas ?

Elle reste **réservée pour le client pendant 10 minutes** (compte à rebours visible), puis redevient disponible automatiquement : la place est considérée libre dès l'expiration, sans attendre de tâche planifiée (ADR-008). Un paiement refusé laisse le client réessayer tant que la réservation court.

Si un paiement réussit **après** l'expiration (banque lente, 3-D Secure), la vente est reprise si toutes les places sont encore libres ; sinon le paiement est **remboursé automatiquement** (ADR-009). Il n'y a jamais de place vendue deux fois, ni de paiement conservé sans billet.

### 4. Que se passe-t-il pour les billets si une représentation est annulée ?

L'organisateur annule depuis le Studio, avec un motif. Alors :
1. les ventes s'arrêtent immédiatement, en ligne et au guichet ;
2. tous les billets sont **invalidés** : le contrôle à l'entrée les refuse ;
3. les commandes payées en ligne sont **remboursées automatiquement** via Stripe ;
4. les ventes au guichet passent en **remboursement à effectuer au guichet**, que l'agent solde quand il rend l'argent ;
5. chaque client reçoit un **email d'annulation**, et la commande apparaît « Remboursée » dans « Mes billets ».

Le traitement passe par Messenger. Il est idempotent et peut être relancé sans double remboursement (clé d'idempotence par remboursement).

En dehors d'une annulation de la représentation, un client peut annuler une commande en ligne jusqu'à **48 h avant**, si aucun billet n'a été scanné. Les achats au guichet s'annulent au guichet.

### 5. Qui a le droit de vendre à quel tarif ?

| Tarif | En ligne (client) | Guichet | Organisateur |
|---|---|---|---|
| Plein | oui | oui | oui |
| Réduit | oui (justificatif à l'entrée) | oui | oui |
| Groupe | oui, 10 à 30 places | oui, 10 à 30 places | oui |
| Invitation (0 MAD) | non | oui, motif obligatoire | oui |

Les règles sont des **données du tarif** (canaux, rôles autorisés, quantités min/max, gratuité), modifiables dans le Studio. Un seul service, `TariffEligibilityPolicy`, les interprète **côté serveur** : à l'affichage (tarifs proposés) comme au moment de la vente (refus avec un message explicite). Le frontend ne fait que refléter ce que l'API autorise.

*Pourquoi.* Une règle de prix appliquée seulement dans l'interface se contourne avec une simple requête HTTP.
*Rôles.* Client, guichetier et administrateur correspondent à `ROLE_CUSTOMER`, `ROLE_BOX_OFFICE` et `ROLE_ORGANIZER`, hiérarchisés : l'organisateur peut tout ce que peut le guichet.
