<?php

namespace App\Tests\Functional;

use App\Entity\Lot;
use App\Entity\Reservation;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Vérifie le chargement des données de démonstration utilisé sur le site en ligne.
 */
class ChargerDemoCommandTest extends KernelTestCase
{
    use DatabaseTrait;

    private CommandTester $commande;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->reinitialiserBase();

        $application = new Application($kernel);
        $this->commande = new CommandTester($application->find('app:demo:charger'));
    }

    private function compter(string $entite): int
    {
        return $this->em()->getRepository($entite)->count([]);
    }

    public function testUneBaseVideRecoitLesDonneesDeDemo(): void
    {
        self::assertSame(0, $this->compter(User::class));

        $this->commande->execute([]);
        $this->commande->assertCommandIsSuccessful();

        // 1 admin + 16 commerces + 8 associations
        self::assertSame(25, $this->compter(User::class));
        self::assertSame(180, $this->compter(Lot::class));
        self::assertGreaterThan(50, $this->compter(Reservation::class));
        self::assertNotNull($this->em()->getRepository(User::class)->findOneBy(['email' => 'commerce@regeev.test']));
    }

    public function testDesDonneesRecentesNeSontPasRechargees(): void
    {
        $this->commande->execute([]);
        $admin = $this->em()->getRepository(User::class)->findOneBy(['email' => 'admin@regeev.test']);
        self::assertNotNull($admin);
        $idAvant = $admin->getId();

        $this->commande->execute([]);
        $this->commande->assertCommandIsSuccessful();
        self::assertStringContainsString('rien à faire', $this->commande->getDisplay());

        $this->em()->clear();
        $admin = $this->em()->getRepository(User::class)->findOneBy(['email' => 'admin@regeev.test']);
        self::assertNotNull($admin);
        self::assertSame($idAvant, $admin->getId(), 'Les comptes ne doivent pas avoir été recréés.');
    }

    public function testDesDonneesTropAnciennesSontRechargees(): void
    {
        $this->commande->execute([]);

        // Je vieillis artificiellement tous les lots
        $this->em()->createQuery('UPDATE '.Lot::class.' l SET l.createdAt = :ancien')
            ->setParameter('ancien', new \DateTimeImmutable('-30 days'))
            ->execute();

        $this->commande->execute([]);
        $this->commande->assertCommandIsSuccessful();
        self::assertStringNotContainsString('rien à faire', $this->commande->getDisplay());

        $recent = $this->em()->createQuery('SELECT COUNT(l.id) FROM '.Lot::class.' l WHERE l.createdAt > :limite')
            ->setParameter('limite', new \DateTimeImmutable('-2 days'))
            ->getSingleScalarResult();
        self::assertGreaterThan(0, (int) $recent);
    }
}
