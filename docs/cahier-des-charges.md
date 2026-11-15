# Cahier des charges — ReGeev

## 1. Contexte et objectif

Chaque jour, des commerces alimentaires jettent des produits encore consommables,
alors que des associations d'aide alimentaire manquent de denrées.
J'ai conçu ReGeev pour mettre les deux en relation : un commerce publie ses invendus,
une association les réserve et vient les chercher.

Objectif mesurable : suivre les kilos de nourriture sauvés, et les rendre visibles
sur un tableau de bord public.

## 2. Acteurs

| Acteur | Rôle |
|---|---|
| Commerce | Publie ses lots d'invendus et confirme les retraits |
| Association | Réserve des lots et vient les récupérer (compte validé par l'administrateur) |
| Administrateur | Valide les associations, modère, consulte les statistiques |
| Visiteur | Consulte la page d'accueil et le tableau de bord d'impact |

## 3. User stories du MVP

### Commerce
- En tant que commerce, je m'inscris avec mon SIRET et mon adresse.
- En tant que commerce, je publie un lot (produits, quantité, date limite, créneau de retrait).
- En tant que commerce, je suis mes réservations et je confirme le retrait grâce au code présenté par l'association.

### Association
- En tant qu'association, je m'inscris avec mon numéro RNA ; mon compte est validé par un administrateur.
- En tant qu'association, je consulte les lots disponibles sur une liste et une carte, avec des filtres (distance, catégorie, date).
- En tant qu'association, je réserve un lot et je reçois un code de retrait.
- En tant qu'association, je consulte l'historique de mes réservations.

### Administrateur
- En tant qu'administrateur, je valide ou refuse les associations inscrites.
- En tant qu'administrateur, je consulte les statistiques (kg sauvés, nombre de dons, commerces actifs).

### Bonus prévus
Notifications en temps réel, avis, attestation de don en PDF, export CSV.

## 4. Règles de gestion

1. Un lot suit un cycle de vie strict : brouillon → publié → réservé → récupéré, ou expiré / annulé.
2. Un lot ne peut être réservé que par une seule association à la fois.
3. Seule une association validée peut réserver.
4. Le retrait n'est confirmé que si le code de retrait saisi par le commerce est correct.
5. Un lot dont la date limite est dépassée passe automatiquement en « expiré ».
6. Seuls les lots effectivement récupérés comptent dans les statistiques d'impact.

## 5. Exigences non fonctionnelles

- **Sécurité** : mots de passe hachés, protection CSRF, accès par rôle.
- **Qualité** : tests automatisés, analyse statique, intégration continue à chaque push.
- **Portabilité** : application conteneurisée avec Docker.
- **Accessibilité** : interface responsive, utilisable sur mobile.

## 6. Étapes de réalisation

1. Mise en place : Docker, Git, entités, données de démonstration.
2. Authentification par rôle.
3. Gestion des lots côté commerce et géocodage des adresses.
4. Liste, carte et réservation côté association (MVP).
5. Workflow complet, e-mails, expiration automatique.
6. Administration et tableau de bord.
7. Tests, CI/CD, déploiement, documentation.
