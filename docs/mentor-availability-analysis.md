# Analyse — Ajout des disponibilités mentor + sélection par l’apprenant

## 1) Contexte actuel (code existant)

Le backend gère déjà les sessions avec un statut `pending/confirmed/cancelled/completed`, une date `scheduledAt`, un mentor, un apprenant et une compétence. La création de session force l’apprenant à être l’utilisateur connecté (`student = currentUser`) et vérifie que le mentor enseigne bien la compétence sélectionnée.

👉 Aujourd’hui, il n’y a pas de modèle de disponibilités mentor. L’apprenant peut envoyer n’importe quelle date dans `scheduledAt` au moment de créer une session.

## 2) Objectifs fonctionnels demandés

Tu veux couvrir 3 types de disponibilités pour un mentor :

1. **Récurrence** (ex: tous les lundis à 17h)
2. **Disponibilités ponctuelles** (jours “par-ci par-là”)
3. **Exceptions** (ex: tous les jours sauf le lundi, ou dates indisponibles spécifiques)

Et côté apprenant:

4. Lors de la création d’une session `PENDING`, il doit **choisir une date parmi les disponibilités du mentor**.

## 3) Proposition de modèle de données

## 3.1 Nouvelle entité `MentorAvailabilityRule`

But: stocker les règles de base d’un mentor (récurrentes, one-shot, exclusions).

Champs proposés:
- `id`
- `mentor` (FK `User`, obligatoire)
- `type` (enum):
  - `WEEKLY` (récurrence hebdomadaire)
  - `ONE_SHOT` (créneau ponctuel)
  - `EXCLUSION` (règle d’exclusion)
- `dayOfWeek` (1..7, nullable; utile pour `WEEKLY`/`EXCLUSION`)
- `startTime` / `endTime` (time; optionnel selon type)
- `startsAt` / `endsAt` (datetime; pour one-shot et borne de validité)
- `timezone` (string, par défaut timezone mentor)
- `isActive` (bool)
- `createdAt`, `updatedAt`

Notes:
- Pour `WEEKLY`: `dayOfWeek + startTime + endTime`.
- Pour `ONE_SHOT`: `startsAt + endsAt`.
- Pour `EXCLUSION`: soit hebdo (`dayOfWeek`), soit fenêtre date/heure (`startsAt/endsAt`).

## 3.2 Option recommandée: table matérialisée de slots (`MentorAvailabilitySlot`)

But: accélérer la lecture côté front et simplifier la validation en création de session.

Champs:
- `id`
- `mentor` (FK `User`)
- `startAt` (datetime immutable)
- `endAt` (datetime immutable)
- `sourceRuleId` (nullable)
- `status` enum: `AVAILABLE | BOOKED | BLOCKED`
- `createdAt`, `updatedAt`

Principe:
- Un job génère les slots (ex: toutes les 4 semaines glissantes) à partir des règles.
- Les exceptions passent les slots en `BLOCKED`.
- Une session `PENDING/CONFIRMED` peut réserver le slot (`BOOKED`) selon stratégie.

Alternative (sans table de slots): calcul à la volée (plus flexible mais plus coûteux et plus complexe pour éviter les collisions).

## 4) API proposée

## 4.1 Côté mentor (gestion)

- `POST /mentors/{id}/availability-rules`
- `GET /mentors/{id}/availability-rules`
- `PATCH /availability-rules/{id}`
- `DELETE /availability-rules/{id}`

Sécurité:
- Seulement le mentor propriétaire (ou admin).
- Refuser création si `user.isMentor = false`.

## 4.2 Côté apprenant (consultation)

- `GET /mentors/{id}/available-slots?from=...&to=...&skill=...`

Retour:
- Liste de créneaux réservable (`startAt`, `endAt`, éventuellement `durationOptions`).

## 4.3 Côté sessions

Sur `POST /sessions`:
- conserver le flux actuel, **mais** ajouter une validation business:
  - `scheduledAt` doit correspondre à un slot disponible du mentor,
  - durée compatible (`duration` <= longueur slot),
  - slot non déjà réservé.

Idéalement: payload avec `slotId` plutôt que date brute.
- Si `slotId` transmis, backend fixe `scheduledAt = slot.startAt` et verrouille le slot.

## 5) Règles métier détaillées

1. **Un mentor seulement** peut définir des dispos.
2. **Priorité des règles**: exclusions > inclusions.
3. Si recurrences + ponctuel se chevauchent, fusionner ou dédupliquer.
4. Pas de chevauchement de sessions confirmées pour un même mentor.
5. Timezone obligatoire pour interpréter les règles correctement.
6. Fenêtre max de projection (ex: 90 jours) pour éviter les requêtes trop lourdes.
7. À confirmation de session, recheck disponibilité (anti-course condition).

## 6) Intégration dans le code existant

## 6.1 `SessionCreateProcessor`

Ajouter après validations actuelles:
- vérif que `scheduledAt` appartient aux dispos du mentor (ou `slotId` valide),
- vérif collision avec autre session active du mentor,
- verrouillage transactionnel du slot.

## 6.2 Nouveaux services

- `AvailabilityExpansionService`: transforme règles en créneaux.
- `AvailabilityQueryService`: retourne slots disponibles sur une période.
- `AvailabilityGuard`: valide qu’une demande de session est bien dans les dispos.

## 6.3 Repository/DB

- Index recommandés:
  - `(mentor_id, start_at)`
  - `(mentor_id, start_at, end_at, status)`
  - contrainte d’unicité possible sur slot exact selon stratégie.

## 7) Plan d’implémentation par étapes (MVP → robuste)

### Étape 1 (MVP rapide)
- Créer `MentorAvailabilityRule`.
- Exposer CRUD mentor.
- Endpoint `GET available-slots` calculé à la volée (sans matérialisation).
- Ajouter validation dans `SessionCreateProcessor`.

### Étape 2 (fiabilité/perf)
- Ajouter `MentorAvailabilitySlot` + génération asynchrone.
- Verrouillage transactionnel à la réservation.
- Tests de concurrence.

### Étape 3 (UX avancée)
- Gestion exceptions riches (jours fériés, congés).
- Durées variables et granularité configurable (15/30/60 min).
- ICS/Google sync (optionnel).

## 8) Cas limites à couvrir

- Changement d’heure (DST Europe/Paris).
- Mentor change timezone.
- Session réservée puis règle supprimée.
- Deux apprenants tentent le même créneau en simultané.
- Exclusion “tous les jours sauf lundi” + slots ponctuels le lundi.

## 9) Stratégie de tests

- Unit:
  - expansion de règles,
  - priorité inclusion/exclusion,
  - validation durée/slot.
- Intégration API:
  - mentor crée règles,
  - apprenant lit slots,
  - apprenant crée session valide/invalides.
- Concurrence:
  - double réservation même slot → une seule réussite.

## 10) Conclusion

La meilleure trajectoire est:
1) démarrer avec des **règles + endpoint de slots calculés**,
2) brancher la validation de création de session sur ces slots,
3) puis passer à des slots matérialisés pour robustesse/performance.

Cette approche couvre bien tes besoins (récurrence, ponctuel, exceptions, sélection obligatoire depuis les dispos mentor) sans bloquer une mise en prod incrémentale.
