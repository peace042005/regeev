# Conception — ReGeev

Les diagrammes sont écrits en Mermaid : GitHub les affiche directement.

## Diagramme de classes

```mermaid
classDiagram
    class User {
        +int id
        +string email
        +string password
        +string[] roles
        +string nom
        +string telephone
    }
    class Commerce {
        +int id
        +string nom
        +string siret
        +string adresse
        +string codePostal
        +string ville
        +float latitude
        +float longitude
    }
    class Association {
        +int id
        +string nom
        +string rna
        +string adresse
        +string codePostal
        +string ville
        +float latitude
        +float longitude
        +bool isValidated
    }
    class Categorie {
        +int id
        +string nom
    }
    class Lot {
        +int id
        +string titre
        +string description
        +float quantite
        +string unite
        +float poidsKg
        +date dateLimite
        +datetime retraitDebut
        +datetime retraitFin
        +string statut
        +datetime createdAt
        +estPerime() bool
    }
    class Reservation {
        +int id
        +datetime dateReservation
        +string codeRetrait
        +string statut
        +datetime dateRetrait
    }

    Commerce "1" --> "1" User : compte
    Association "1" --> "1" User : compte
    Commerce "1" --> "0..*" Lot : publie
    Categorie "1" --> "0..*" Lot : classe
    Lot "1" --> "0..*" Reservation : concerne
    Association "1" --> "0..*" Reservation : effectue
```

## Cycle de vie d'un lot

Ce cycle est implémenté avec le composant Workflow de Symfony
(`config/packages/framework.yaml`) et piloté par `App\Service\LotManager`.

```mermaid
stateDiagram-v2
    [*] --> brouillon
    brouillon --> publie : publier
    publie --> reserve : reserver
    reserve --> publie : liberer
    reserve --> recupere : confirmer_retrait
    publie --> expire : expirer
    reserve --> expire : expirer
    brouillon --> annule : annuler
    publie --> annule : annuler
    reserve --> annule : annuler
    recupere --> [*]
    expire --> [*]
    annule --> [*]
```

## Architecture

```mermaid
flowchart LR
    N[Navigateur] --> C[Contrôleurs Symfony]
    C --> T[Templates Twig + Bootstrap]
    C --> S[Services métier<br>LotManager, StatsProvider, ImpactCalculator]
    S --> W[Workflow]
    S --> D[Doctrine ORM]
    D --> P[(PostgreSQL)]
```

## Circuit des données d'impact

1. `StatsProvider` lit en base les réservations au statut « récupérée ».
2. `ImpactCalculator` agrège ces lignes : total, repas équivalents, répartition par mois, catégorie et ville.
3. Le résultat est exposé de trois façons : page `/impact` (graphiques), `/api/stats` (JSON), `/impact/export.csv`.

`ImpactCalculator` ne dépend ni de la base ni du framework : je peux le tester unitairement.
