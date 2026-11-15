# ReGeev

Plateforme de dons d'invendus alimentaires entre commerces et associations.

Je m'appelle Marcella Chanhoun, je suis étudiante en BUT 3 Informatique.
J'ai créé ReGeev pour répondre à un problème concret : des commerces jettent chaque jour
des produits encore consommables alors que des associations en manquent.
Avec ce projet, je parcours tout le cycle de développement d'un logiciel :
analyse du besoin, conception, développement, tests, intégration continue et déploiement.

**Démo en ligne : https://regeev.onrender.com**

Le site est hébergé sur une offre gratuite : après une période sans visite, le premier affichage peut prendre
jusqu'à une minute. Les comptes de démonstration sont indiqués plus bas, et les données fictives sont
rechargées automatiquement pour rester récentes.

## Fonctionnalités

- Inscription d'un commerce (format du SIRET contrôlé) ou d'une association (numéro RNA).
- Connexion sécurisée et espace personnel selon le rôle.
- Cycle de vie d'un lot géré par une machine à états : brouillon, publié, réservé, récupéré, expiré, annulé.
- Espace commerce : création d'un lot en brouillon, modification, publication, annulation, confirmation du retrait avec le code.
- Droits d'accès par Voter : un commerce ne gère que ses propres lots.
- Espace association : lots disponibles filtrables par catégorie et par ville, réservation avec code de retrait, désistement.
- Administration : validation et suspension des associations, compteurs de la plateforme, modération des lots.
- Expiration automatique des lots périmés (`php bin/console app:lots:expirer`).
- Tableau de bord d'impact public : kilos sauvés, repas équivalents, évolution par mois, répartition par catégorie et par ville.
- Données d'impact disponibles en JSON (`/api/stats`) et en CSV (`/impact/export.csv`).

## Stack technique

| Domaine | Outils |
|---|---|
| Back-end | PHP 8.3, Symfony 7.4, Doctrine ORM, composant Workflow |
| Base de données | PostgreSQL |
| Front-end | Twig, Bootstrap 5, Chart.js |
| Tests | PHPUnit (unitaires, intégration, fonctionnels) |
| Qualité | PHPStan, linters Symfony |
| CI/CD | GitHub Actions |
| Conteneurisation | Docker, Docker Compose |

## Installation

Prérequis : PHP 8.2 ou plus, Composer, Docker.

```bash
git clone https://github.com/peace042005/regeev.git
cd regeev
composer install

# Base de données PostgreSQL
docker compose up -d

# Création des tables à partir des migrations
php bin/console doctrine:migrations:migrate

# Données de démonstration (6 mois d'activité fictive)
php bin/console doctrine:fixtures:load

# Lancement
php -S 127.0.0.1:8000 -t public
```

L'application est disponible sur http://127.0.0.1:8000.

Sans Docker, je peux utiliser SQLite en créant un fichier `.env.local` :

```
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

Les migrations sont écrites pour PostgreSQL : avec SQLite, je crée les tables avec
`php bin/console doctrine:schema:create` à la place de `doctrine:migrations:migrate`.

### Comptes de démonstration

Mot de passe commun : `regeev123`

| Rôle | E-mail |
|---|---|
| Commerce | commerce@regeev.test |
| Association | association@regeev.test |
| Administrateur | admin@regeev.test |

## Tests et qualité

```bash
composer test    # PHPUnit
composer stan    # PHPStan
composer lint    # YAML, Twig, conteneur de services
```

J'ai écrit trois niveaux de tests :

- **unitaires** : calcul des indicateurs d'impact, règles des entités, génération du code de retrait ;
- **intégration** : cycle de vie d'un lot avec le Workflow et la base de données ;
- **fonctionnels** : parcours dans les pages (accueil, tableau de bord, API, inscription, accès protégé).

## Intégration et déploiement continus

À chaque push et à chaque pull request, GitHub Actions :

1. valide `composer.json` et installe les dépendances ;
2. lance les linters et PHPStan ;
3. vérifie le mapping Doctrine ;
4. exécute les tests sur une vraie base PostgreSQL ;
5. construit l'image Docker ;
6. sur la branche `main`, déclenche le déploiement chez l'hébergeur.

## Mise en production

L'application est conteneurisée (`Dockerfile`) et décrite pour l'hébergeur dans `render.yaml`.

- **Application** : Render (service web Docker, région Francfort).
- **Base de données** : PostgreSQL chez Neon.
- **Au démarrage du conteneur** (`docker/entrypoint.sh`) : application des migrations, préparation du cache,
  chargement des données de démonstration si elles sont absentes ou trop anciennes, expiration des lots périmés.
- **Secrets** : `APP_SECRET` et `DATABASE_URL` sont des variables d'environnement de l'hébergeur, jamais versionnées.
- **Déploiement continu** : le pipeline appelle le « deploy hook » de Render (secret GitHub `DEPLOY_HOOK_URL`)
  uniquement quand les tests et la construction de l'image ont réussi sur `main`.

L'offre gratuite met le service en veille après une période sans visite : le premier affichage peut prendre
jusqu'à une minute.

## Documentation

- [Cahier des charges](docs/cahier-des-charges.md)
- [Conception : diagramme de classes, cycle de vie d'un lot, architecture](docs/conception.md)

## Avancement

- [x] Analyse du besoin et conception
- [x] Socle technique : entités, sécurité, données de démonstration
- [x] Inscription et connexion par rôle
- [x] Règles métier du cycle de vie d'un lot
- [x] Tableau de bord d'impact (page, API JSON, export CSV)
- [x] Tests automatisés et pipeline CI/CD
- [x] Gestion des lots côté commerce : création, modification, publication, annulation, confirmation du retrait
- [ ] Géocodage des adresses
- [x] Côté association : liste filtrable des lots disponibles, réservation avec code de retrait, désistement
- [ ] Carte des lots
- [ ] E-mails de notification
- [x] Administration : validation et suspension des associations, vue d'ensemble, modération des lots
- [x] Mise en ligne sur Render avec une base PostgreSQL Neon : https://regeev.onrender.com

## Ce que j'ai appris

- Modéliser un cycle de vie avec une machine à états plutôt qu'avec des `if` dispersés dans le code.
- Séparer le calcul (`ImpactCalculator`) de l'accès aux données (`StatsProvider`) pour pouvoir tester le calcul seul.
- Faire tourner les tests sur PostgreSQL dans la CI pour être au plus près de la production.

## Auteure

Marcella Chanhoun — [GitHub](https://github.com/peace042005) · [Portfolio](https://portfolio-marcella-chanhoun.vercel.app/)
