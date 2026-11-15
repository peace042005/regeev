<?php

namespace App\DataFixtures;

use App\Entity\Association;
use App\Entity\Categorie;
use App\Entity\Commerce;
use App\Entity\Lot;
use App\Entity\Reservation;
use App\Entity\User;
use App\Service\CodeRetraitGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de données de démonstration : 6 mois d'activité fictive
 * pour alimenter le tableau de bord d'impact.
 *
 * Comptes de démo (mot de passe : regeev123)
 *  - commerce@regeev.test
 *  - association@regeev.test
 *  - admin@regeev.test
 */
class AppFixtures extends Fixture
{
    public const MOT_DE_PASSE = 'regeev123';

    /** [ville, code postal, latitude, longitude] */
    private const VILLES = [
        ['Belfort', '90000', 47.6397, 6.8638],
        ['Montbéliard', '25200', 47.5100, 6.7980],
        ['Besançon', '25000', 47.2380, 6.0243],
        ['Strasbourg', '67000', 48.5734, 7.7521],
        ['Lyon', '69003', 45.7640, 4.8357],
        ['Lille', '59000', 50.6292, 3.0573],
        ['Orléans', '45000', 47.9029, 1.9093],
        ['Paris', '75011', 48.8566, 2.3522],
    ];

    private const CATEGORIES = [
        'Boulangerie' => ['Pains et viennoiseries de la veille', 'Baguettes invendues', 'Assortiment de pâtisseries'],
        'Fruits et légumes' => ['Cagette de légumes de saison', 'Fruits mûrs à consommer vite', 'Salades et herbes fraîches'],
        'Produits laitiers' => ['Yaourts à date courte', 'Fromages à la coupe', 'Lait et crème fraîche'],
        'Épicerie' => ['Conserves et pâtes', 'Céréales et biscuits', 'Produits secs en fin de série'],
        'Plats préparés' => ['Plats du jour non vendus', 'Sandwichs et salades', 'Quiches et tartes salées'],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly CodeRetraitGenerator $codeGenerator,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $faker->seed(2026);

        $categories = [];
        foreach (array_keys(self::CATEGORIES) as $nom) {
            $categories[$nom] = new Categorie($nom);
            $manager->persist($categories[$nom]);
        }

        $admin = $this->creerUser('admin@regeev.test', 'Administration ReGeev', User::ROLE_ADMIN);
        $manager->persist($admin);

        $commerces = [];
        for ($i = 0; $i < 16; ++$i) {
            [$ville, $codePostal, $lat, $lng] = self::VILLES[$i % count(self::VILLES)];
            $nom = 0 === $i ? 'Boulangerie des Rosiers' : $faker->company();
            $email = 0 === $i ? 'commerce@regeev.test' : sprintf('commerce%d@regeev.test', $i);

            $commerce = (new Commerce())
                ->setUser($this->creerUser($email, $nom, User::ROLE_COMMERCE))
                ->setNom($nom)
                ->setSiret($this->genererSiret($i))
                ->setAdresse($faker->streetAddress())
                ->setCodePostal($codePostal)
                ->setVille($ville)
                ->setCoordonnees($lat + $faker->randomFloat(4, -0.02, 0.02), $lng + $faker->randomFloat(4, -0.02, 0.02));

            $manager->persist($commerce);
            $commerces[] = $commerce;
        }

        $associations = [];
        for ($i = 0; $i < 8; ++$i) {
            [$ville, $codePostal, $lat, $lng] = self::VILLES[$i % count(self::VILLES)];
            $nom = 0 === $i ? 'Entraide Belfortaine' : 'Solidarité '.$ville;
            $email = 0 === $i ? 'association@regeev.test' : sprintf('association%d@regeev.test', $i);

            $association = (new Association())
                ->setUser($this->creerUser($email, $nom, User::ROLE_ASSOCIATION))
                ->setNom($nom)
                ->setRna('W'.$faker->unique()->numerify('#########'))
                ->setAdresse($faker->streetAddress())
                ->setCodePostal($codePostal)
                ->setVille($ville)
                ->setCoordonnees($lat + $faker->randomFloat(4, -0.02, 0.02), $lng + $faker->randomFloat(4, -0.02, 0.02))
                // La dernière association reste en attente pour montrer la validation par l'admin
                ->setIsValidated($i < 7);

            $manager->persist($association);
            $associations[] = $association;
        }

        $associationsValidees = array_slice($associations, 0, 7);
        $aujourdhui = new \DateTimeImmutable('today');

        for ($i = 0; $i < 180; ++$i) {
            $nomCategorie = $faker->randomElement(array_keys(self::CATEGORIES));
            $creation = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-6 months', 'now'));
            $dateLimite = $creation->modify(sprintf('+%d days', $faker->numberBetween(1, 4)))->setTime(0, 0);
            $poids = $faker->randomFloat(1, 1, 25);

            $lot = (new Lot())
                ->setCommerce($faker->randomElement($commerces))
                ->setCategorie($categories[$nomCategorie])
                ->setTitre($faker->randomElement(self::CATEGORIES[$nomCategorie]))
                ->setDescription($faker->sentence(12))
                ->setQuantite($poids)
                ->setUnite('kg')
                ->setPoidsKg($poids)
                ->setDateLimite($dateLimite)
                ->setRetraitDebut($dateLimite->setTime(17, 0))
                ->setRetraitFin($dateLimite->setTime(19, 0))
                ->setCreatedAt($creation);

            $this->simulerCycleDeVie($lot, $dateLimite < $aujourdhui, $associationsValidees, $faker, $manager);
            $manager->persist($lot);
        }

        $manager->flush();
    }

    /**
     * Donne un statut réaliste au lot : dans le passé la plupart ont été récupérés,
     * les lots encore valables sont publiés ou réservés.
     *
     * @param Association[] $associations
     */
    private function simulerCycleDeVie(Lot $lot, bool $passe, array $associations, Generator $faker, ObjectManager $manager): void
    {
        $tirage = $faker->numberBetween(1, 100);
        $dateReservation = $lot->getCreatedAt()->modify('+2 hours');

        if ($passe) {
            if ($tirage <= 75) {
                $lot->setStatut(Lot::STATUT_RECUPERE);
                $reservation = new Reservation($lot, $faker->randomElement($associations), $this->codeGenerator->generer(), $dateReservation);
                $reservation->marquerRecuperee($lot->getRetraitDebut());
                $manager->persist($reservation);
            } elseif ($tirage <= 90) {
                $lot->setStatut(Lot::STATUT_EXPIRE);
            } else {
                $lot->setStatut(Lot::STATUT_ANNULE);
            }

            return;
        }

        if ($tirage <= 40) {
            $lot->setStatut(Lot::STATUT_RESERVE);
            $manager->persist(new Reservation($lot, $faker->randomElement($associations), $this->codeGenerator->generer(), $dateReservation));
        } else {
            $lot->setStatut(Lot::STATUT_PUBLIE);
        }
    }

    /** Fabrique un SIRET fictif mais valide : 13 chiffres suivis de la clé de contrôle de Luhn. */
    private function genererSiret(int $index): string
    {
        $base = sprintf('%09d%04d', 800000000 + $index * 7919, 10 + $index);
        $somme = 0;

        foreach (str_split(strrev($base)) as $position => $chiffre) {
            $valeur = (int) $chiffre;
            if (0 === $position % 2) {
                $valeur *= 2;
                if ($valeur > 9) {
                    $valeur -= 9;
                }
            }
            $somme += $valeur;
        }

        return $base.((10 - $somme % 10) % 10);
    }

    private function creerUser(string $email, string $nom, string $role): User
    {
        $user = (new User())->setEmail($email)->setNom($nom)->setRoles([$role]);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::MOT_DE_PASSE));

        return $user;
    }
}
