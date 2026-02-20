# README — Workflow complet des disponibilités mentor et création de sessions (Front)

Ce document explique **le parcours normal** côté Front pour :

1. Un utilisateur qui agit comme **mentor** et configure ses disponibilités.
2. Un utilisateur qui agit comme **apprenant** et crée une session à partir des disponibilités du mentor.

L’objectif est d’avoir un guide **pratique, séquencé et intégrable** dans le Front.

---

## 1) Vue d’ensemble métier

### Rôles
- **Mentor** : définit ses règles de disponibilités.
- **Apprenant** : consulte les slots disponibles d’un mentor et crée une session.

### Types de disponibilité supportés
Les règles de disponibilité mentor (`MentorAvailabilityRule`) supportent :
- `weekly` : récurrence hebdo (ex: tous les mardis 09:00–12:00),
- `one_shot` : fenêtre ponctuelle (ex: vendredi prochain 15:00–18:00),
- `exclusion` : exception (hebdo ou fenêtre date/heure) qui bloque des créneaux.

### Règle métier clé
À la création de session (`POST /sessions`), le backend refuse si la date demandée ne match pas les disponibilités du mentor.

---

## 2) Pré-requis techniques côté Front

## 2.1 Auth cookie + CSRF
Le backend fonctionne en cookie auth avec double-submit CSRF :
- après login (`POST /auth`) :
    - cookie `access_token`,
    - cookie `XSRF-TOKEN`,
    - header `X-CSRF-TOKEN`.

Pour chaque requête **non-safe** (`POST`, `PATCH`, `DELETE`), il faut envoyer :
- le cookie `XSRF-TOKEN` (géré par le navigateur/postman),
- le header `X-CSRF-TOKEN` avec la même valeur.

## 2.2 Format de contenu
- API Platform attend généralement `Content-Type: application/ld+json` pour les écritures.

---

## 3) Workflow normal — Mentor (création des disponibilités)

## Étape M1 — Connexion mentor

### Requête
`POST /auth`

```json
{
  "email": "mentor@example.com",
  "password": "password123"
}
```

### Front
- stocker `X-CSRF-TOKEN` côté client (state/store mémoire),
- laisser le navigateur gérer les cookies.

---

## Étape M2 — Créer une règle `weekly`

### Endpoint
`POST /mentor_availability_rules`

### Headers
- `Content-Type: application/ld+json`
- `X-CSRF-TOKEN: <token>`

### Body exemple
```json
{
  "type": "weekly",
  "dayOfWeek": 1,
  "startTime": "1970-01-01T17:00:00+00:00",
  "endTime": "1970-01-01T20:00:00+00:00",
  "timezone": "Europe/Paris"
}
```

> `dayOfWeek`: 1 = lundi, ... 7 = dimanche.

---

## Étape M3 — Créer une règle `one_shot`

### Endpoint
`POST /mentor_availability_rules`

### Body exemple
```json
{
  "type": "one_shot",
  "startsAt": "2026-03-20T15:00:00+01:00",
  "endsAt": "2026-03-20T18:00:00+01:00",
  "timezone": "Europe/Paris"
}
```

---

## Étape M4 — Créer une règle `exclusion`

### Endpoint
`POST /mentor_availability_rules`

### Option A — Exclusion hebdo
```json
{
  "type": "exclusion",
  "dayOfWeek": 1,
  "startTime": "1970-01-01T18:00:00+00:00",
  "endTime": "1970-01-01T19:00:00+00:00",
  "timezone": "Europe/Paris"
}
```

### Option B — Exclusion ponctuelle
```json
{
  "type": "exclusion",
  "startsAt": "2026-04-01T00:00:00+02:00",
  "endsAt": "2026-04-03T23:59:59+02:00",
  "timezone": "Europe/Paris"
}
```

---

## Étape M5 — Lire/modifier/supprimer ses règles

- Lister : `GET /mentor_availability_rules?mentor=/users/{id}`
- Détail : `GET /mentor_availability_rules/{id}`
- Modifier : `PATCH /mentor_availability_rules/{id}`
- Supprimer : `DELETE /mentor_availability_rules/{id}`

### Exemple PATCH
```json
{
  "isActive": false
}
```

---

## 4) Workflow normal — Apprenant (création d’une session)

## Étape S1 — Connexion apprenant

`POST /auth` (même logique cookie + CSRF).

---

## Étape S2 — Consulter les slots disponibles d’un mentor

### Endpoint
`GET /mentors/{mentorId}/available-slots?from=...&to=...&duration=60`

### Exemple
`GET /mentors/12/available-slots?from=2026-03-01T00:00:00%2B01:00&to=2026-03-15T00:00:00%2B01:00&duration=60`

### Réponse attendue (exemple)
```json
{
  "@context": "/contexts/MentorAvailableSlot",
  "@id": "/mentors/12/available-slots",
  "@type": "Collection",
  "member": [
    {
      "id": "12-2026-03-02T17:00:00+01:00-60",
      "startAt": "2026-03-02T17:00:00+01:00",
      "endAt": "2026-03-02T18:00:00+01:00",
      "duration": 60
    }
  ]
}
```

### Front
- afficher une grille agenda ou liste de créneaux,
- forcer le choix utilisateur via ces slots (pas de saisie libre brute).

---

## Étape S3 — Créer une session `pending`

### Endpoint
`POST /sessions`

### Headers
- `Content-Type: application/ld+json`
- `X-CSRF-TOKEN: <token>`

### Body exemple
```json
{
  "mentor": "/users/12",
  "skill": "/skills/5",
  "scheduledAt": "2026-03-02T17:00:00+01:00",
  "duration": 60,
  "location": "Zoom",
  "notes": "Besoin d'aide sur les hooks React"
}
```

### Comportement attendu
- `201` si slot valide,
- `422` si hors disponibilité mentor (message métier),
- `422` si mentor ne possède pas la skill en `teach`,
- `422` si règles token/session empêchent la création.

---

## 5) Workflow UX recommandé (Front)

## Côté mentor
1. Écran “Mes disponibilités”.
2. Créer/éditer des blocs récurrents (`weekly`).
3. Ajouter des ouvertures ponctuelles (`one_shot`).
4. Ajouter des exceptions (`exclusion`).
5. Afficher preview agenda résultant.

## Côté apprenant
1. Profil mentor -> CTA “Voir les créneaux disponibles”.
2. Charger `available-slots` selon période + durée.
3. Sélection d’un créneau.
4. Formulaire session pré-rempli avec `scheduledAt` + `duration`.
5. Confirmation -> `POST /sessions`.

---

## 6) Gestion d’erreurs à prévoir côté Front

- `401/403` : session expirée ou droits insuffisants.
- `404` : mentor ou ressource inexistante.
- `422` : validation métier (disponibilité, skill, token...).
- `500` : erreur serveur -> message générique + retry.

Bon pattern:
- afficher le message `violations[].message` si présent,
- fallback sur message standard sinon.

---

## 7) Checklist d’intégration Front

- [ ] login mentor/apprenant opérationnel (`/auth`)
- [ ] gestion CSRF sur toutes requêtes non-safe
- [ ] CRUD règles dispo mentor (`/mentor_availability_rules`)
- [ ] liste des slots (`/mentors/{id}/available-slots`)
- [ ] création session depuis slot (`/sessions`)
- [ ] affichage erreurs validation backend
- [ ] tests manuels timezone (Europe/Paris, DST)

---

## 8) Exemples de scénarios manuels E2E

1. Mentor crée `weekly` lundi 17-20 + exclusion 18-19.
2. Apprenant charge les slots lundi:
    - 17h doit apparaître,
    - 18h ne doit pas apparaître,
    - 19h doit apparaître.
3. Apprenant crée session à 17h -> OK.
4. Apprenant tente 18h en forçant `scheduledAt` -> rejet `422`.

---

## 9) Notes d’implémentation

- Le backend calcule les slots via provider dédié.
- Les règles sont évaluées avec priorité aux exclusions.
- La création de session repasse une validation disponibilité côté serveur.
- Les fixtures seedent déjà des mentors + règles + sessions cohérentes pour QA locale.
