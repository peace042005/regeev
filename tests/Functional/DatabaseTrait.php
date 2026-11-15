<?php

namespace App\Tests\Functional;

use App\Entity\Association;
use App\Entity\Categorie;
use App\Entity\Commerce;
use App\Entity\Lot;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Repart d'une base vide avant chaque test et fournit quelques fabriques d'objets.
 */
trait DatabaseTrait
{
    private static int $compteur = 0;

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    private function reinitialiserBase(): void
    {
        $em = $this->em();
        $metadata = $em->getMetadataFactory()->getAllMetadata();

        $schemaTool = new SchemaTool($em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function creerUser(string $role, ?string $email = null): User
    {
        ++self::$compteur;

        return (new User())
            ->setEmail($email ?? sprintf('user%d@test.fr', self::$compteur))
            ->setNom('Utilisateur '.self::$compteur)
            ->setPassword('non-utilise')
            ->setRoles([$role]);
    }

    private function creerCommerce(string $ville = 'Belfort'): Commerce
    {
        $commerce = (new Commerce())
            ->setUser($this->creerUser(User::ROLE_COMMERCE))
            ->setNom('Commerce '.self::$compteur)
            ->setSiret(sprintf('%014d', self::$compteur))
            ->setAdresse('1 rue du Test')
            ->setCodePostal('90000')
            ->setVille($ville);

        $this->em()->persist($commerce);

        return $commerce;
    }

    private function creerAssociation(bool $validee = true): Association
    {
        $association = (new Association())
            ->setUser($this->creerUser(User::ROLE_ASSOCIATION))
            ->setNom('Association '.self::$compteur)
            ->setRna(sprintf('W%09d', self::$compteur))
            ->setAdresse('2 rue du Test')
            ->setCodePostal('90000')
            ->setVille('Belfort')
            ->setIsValidated($validee);

        $this->em()->persist($association);

        return $association;
    }

    private function creerLot(Commerce $commerce, float $poidsKg = 5.0, string $dateLimite = '+2 days', string $categorie = 'Boulangerie'): Lot
    {
        $em = $this->em();
        $cat = $em->getRepository(Categorie::class)->findOneBy(['nom' => $categorie]);
        if (null === $cat) {
            $cat = new Categorie($categorie);
            $em->persist($cat);
            $em->flush();
        }

        $limite = new \DateTimeImmutable($dateLimite);
        $lot = (new Lot())
            ->setCommerce($commerce)
            ->setCategorie($cat)
            ->setTitre('Lot de test')
            ->setQuantite($poidsKg)
            ->setPoidsKg($poidsKg)
            ->setDateLimite($limite)
            ->setRetraitDebut($limite->setTime(17, 0))
            ->setRetraitFin($limite->setTime(19, 0));

        $em->persist($lot);

        return $lot;
    }
}
