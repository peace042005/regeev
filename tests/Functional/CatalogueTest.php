<?php

namespace App\Tests\Functional;

use App\Entity\Association;
use App\Entity\Lot;
use App\Entity\Reservation;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Parcours de l'association : consulter les lots, réserver, se désister.
 */
class CatalogueTest extends WebTestCase
{
    use DatabaseTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->reinitialiserBase();
    }

    private function connecter(Association $association): void
    {
        $user = $association->getUser();
        self::assertNotNull($user);
        $this->client->loginUser($user);
    }

    private function trouverLot(int $id): Lot
    {
        $lot = $this->em()->find(Lot::class, $id);
        self::assertNotNull($lot);

        return $lot;
    }

    public function testSeulsLesLotsPubliesEtNonPerimesSontListes(): void
    {
        $belfort = $this->creerCommerce('Belfort');
        $lyon = $this->creerCommerce('Lyon');

        $this->creerLot($belfort)->setStatut(Lot::STATUT_PUBLIE);
        $this->creerLot($lyon)->setStatut(Lot::STATUT_PUBLIE);
        $epicerie = $this->creerLot($lyon, categorie: 'Épicerie')->setStatut(Lot::STATUT_PUBLIE);
        // Ceux-ci ne doivent pas apparaître
        $this->creerLot($belfort);
        $this->creerLot($belfort)->setStatut(Lot::STATUT_RESERVE);
        $this->creerLot($belfort, dateLimite: '-1 day')->setStatut(Lot::STATUT_PUBLIE);

        $association = $this->creerAssociation();
        $this->em()->flush();
        $categorieId = $epicerie->getCategorie()?->getId();

        $this->connecter($association);

        $crawler = $this->client->request('GET', '/lots');
        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('.lot-card'));

        $crawler = $this->client->request('GET', '/lots?ville=Lyon');
        self::assertCount(2, $crawler->filter('.lot-card'));

        $crawler = $this->client->request('GET', '/lots?categorie='.$categorieId);
        self::assertCount(1, $crawler->filter('.lot-card'));

        $crawler = $this->client->request('GET', '/lots?ville=Belfort&categorie='.$categorieId);
        self::assertCount(0, $crawler->filter('.lot-card'));
    }

    public function testUneAssociationValideeReserveUnLot(): void
    {
        $lot = $this->creerLot($this->creerCommerce())->setStatut(Lot::STATUT_PUBLIE);
        $association = $this->creerAssociation();
        $this->em()->flush();
        $lotId = (int) $lot->getId();

        $this->connecter($association);

        $crawler = $this->client->request('GET', '/lots/'.$lotId);
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Réserver ce lot')->form());
        self::assertResponseRedirects('/espace');

        self::assertSame(Lot::STATUT_RESERVE, $this->trouverLot($lotId)->getStatut());
        $reservation = $this->em()->getRepository(Reservation::class)->findOneBy(['lot' => $lotId]);
        self::assertNotNull($reservation);
        self::assertTrue($reservation->estActive());

        // Le code de retrait est affiché à l'association
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', $reservation->getCodeRetrait());

        // Le lot n'est plus proposé aux autres
        $this->client->request('GET', '/lots/'.$lotId);
        self::assertResponseStatusCodeSame(404);
    }

    public function testUneAssociationNonValideeNePeutPasReserver(): void
    {
        $lot = $this->creerLot($this->creerCommerce())->setStatut(Lot::STATUT_PUBLIE);
        $association = $this->creerAssociation(validee: false);
        $this->em()->flush();
        $lotId = (int) $lot->getId();

        $this->connecter($association);

        $crawler = $this->client->request('GET', '/lots/'.$lotId);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->selectButton('Réserver ce lot'));

        // Même en envoyant la requête à la main, la réservation est refusée
        $this->client->request('POST', '/lots/'.$lotId.'/reserver');
        self::assertSame(Lot::STATUT_PUBLIE, $this->trouverLot($lotId)->getStatut());
        self::assertNull($this->em()->getRepository(Reservation::class)->findOneBy(['lot' => $lotId]));
    }

    public function testSeDesisterRemetLeLotEnLigne(): void
    {
        $lot = $this->creerLot($this->creerCommerce())->setStatut(Lot::STATUT_RESERVE);
        $association = $this->creerAssociation();
        $this->em()->persist(new Reservation($lot, $association, 'ABCDEF'));
        $this->em()->flush();
        $lotId = (int) $lot->getId();

        $this->connecter($association);

        $crawler = $this->client->request('GET', '/espace');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Annuler la réservation')->form());
        self::assertResponseRedirects('/espace');

        self::assertSame(Lot::STATUT_PUBLIE, $this->trouverLot($lotId)->getStatut());
    }

    public function testUneAssociationNePeutPasAnnulerLaReservationDUneAutre(): void
    {
        $lot = $this->creerLot($this->creerCommerce())->setStatut(Lot::STATUT_RESERVE);
        $titulaire = $this->creerAssociation();
        $autre = $this->creerAssociation();
        $reservation = new Reservation($lot, $titulaire, 'ABCDEF');
        $this->em()->persist($reservation);
        $this->em()->flush();
        $lotId = (int) $lot->getId();

        $this->connecter($autre);

        $this->client->request('POST', '/reservations/'.$reservation->getId().'/annuler');
        self::assertResponseStatusCodeSame(403);
        self::assertSame(Lot::STATUT_RESERVE, $this->trouverLot($lotId)->getStatut());
    }

    public function testUnCommerceNAPasAccesAuCatalogue(): void
    {
        $commerce = $this->creerCommerce();
        $this->em()->flush();

        $user = $commerce->getUser();
        self::assertNotNull($user);
        $this->client->loginUser($user);

        $this->client->request('GET', '/lots');
        self::assertResponseStatusCodeSame(403);
    }

    public function testUnVisiteurEstRenvoyeVersLaConnexion(): void
    {
        $this->client->request('GET', '/lots');

        self::assertResponseRedirects();
        self::assertStringContainsString('/connexion', (string) $this->client->getResponse()->headers->get('Location'));
    }
}
