<?php

namespace App\Tests\Functional;

use App\Entity\Lot;
use App\Entity\Reservation;
use App\Service\LotManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'intégration du cycle de vie d'un lot (Workflow + base de données).
 */
class LotManagerTest extends KernelTestCase
{
    use DatabaseTrait;

    private LotManager $lotManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->reinitialiserBase();

        $lotManager = static::getContainer()->get(LotManager::class);
        self::assertInstanceOf(LotManager::class, $lotManager);
        $this->lotManager = $lotManager;
    }

    public function testParcoursComplet(): void
    {
        $lot = $this->creerLot($this->creerCommerce());
        $association = $this->creerAssociation();
        $this->em()->flush();

        self::assertSame(Lot::STATUT_BROUILLON, $lot->getStatut());

        $this->lotManager->publier($lot);
        self::assertSame(Lot::STATUT_PUBLIE, $lot->getStatut());

        $reservation = $this->lotManager->reserver($lot, $association);
        self::assertSame(Lot::STATUT_RESERVE, $lot->getStatut());
        self::assertTrue($reservation->estActive());
        self::assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $reservation->getCodeRetrait());

        // Le code est accepté même saisi en minuscules avec des espaces
        $this->lotManager->confirmerRetrait($reservation, ' '.strtolower($reservation->getCodeRetrait()).' ');
        self::assertSame(Lot::STATUT_RECUPERE, $lot->getStatut());
        self::assertSame(Reservation::STATUT_RECUPEREE, $reservation->getStatut());
        self::assertNotNull($reservation->getDateRetrait());
    }

    public function testUnLotDejaReserveNePeutPasEtreReserveDeuxFois(): void
    {
        $lot = $this->creerLot($this->creerCommerce());
        $premiere = $this->creerAssociation();
        $seconde = $this->creerAssociation();
        $this->em()->flush();

        $this->lotManager->publier($lot);
        $this->lotManager->reserver($lot, $premiere);

        $this->expectException(\DomainException::class);
        $this->lotManager->reserver($lot, $seconde);
    }

    public function testUneAssociationNonValideeNePeutPasReserver(): void
    {
        $lot = $this->creerLot($this->creerCommerce());
        $association = $this->creerAssociation(validee: false);
        $this->em()->flush();
        $this->lotManager->publier($lot);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('validée');
        $this->lotManager->reserver($lot, $association);
    }

    public function testUnMauvaisCodeNeValidePasLeRetrait(): void
    {
        $lot = $this->creerLot($this->creerCommerce());
        $association = $this->creerAssociation();
        $this->em()->flush();
        $this->lotManager->publier($lot);
        $reservation = $this->lotManager->reserver($lot, $association);

        try {
            $this->lotManager->confirmerRetrait($reservation, '000000');
            self::fail('Un code incorrect aurait dû être refusé.');
        } catch (\DomainException) {
            self::assertSame(Lot::STATUT_RESERVE, $lot->getStatut());
            self::assertTrue($reservation->estActive());
        }
    }

    public function testAnnulerUneReservationRemetLeLotEnLigne(): void
    {
        $lot = $this->creerLot($this->creerCommerce());
        $association = $this->creerAssociation();
        $this->em()->flush();
        $this->lotManager->publier($lot);
        $reservation = $this->lotManager->reserver($lot, $association);

        $this->lotManager->annulerReservation($reservation);

        self::assertSame(Lot::STATUT_PUBLIE, $lot->getStatut());
        self::assertSame(Reservation::STATUT_ANNULEE, $reservation->getStatut());
    }

    public function testExpirationDesLotsPerimes(): void
    {
        $commerce = $this->creerCommerce();
        $association = $this->creerAssociation();

        $perimePublie = $this->creerLot($commerce, dateLimite: '-1 day')->setStatut(Lot::STATUT_PUBLIE);
        $perimeReserve = $this->creerLot($commerce, dateLimite: '-3 days')->setStatut(Lot::STATUT_RESERVE);
        $duJour = $this->creerLot($commerce, dateLimite: 'today')->setStatut(Lot::STATUT_PUBLIE);
        $brouillonPerime = $this->creerLot($commerce, dateLimite: '-1 day');

        $reservation = new Reservation($perimeReserve, $association, 'ABCDEF');
        $this->em()->persist($reservation);
        $this->em()->flush();

        $nombre = $this->lotManager->expirerLotsPerimes();

        self::assertSame(2, $nombre);
        self::assertSame(Lot::STATUT_EXPIRE, $perimePublie->getStatut());
        self::assertSame(Lot::STATUT_EXPIRE, $perimeReserve->getStatut());
        self::assertSame(Reservation::STATUT_ANNULEE, $reservation->getStatut());
        self::assertSame(Lot::STATUT_PUBLIE, $duJour->getStatut(), 'Un lot reste disponible jusqu\'à la fin de sa date limite.');
        self::assertSame(Lot::STATUT_BROUILLON, $brouillonPerime->getStatut());
    }
}
