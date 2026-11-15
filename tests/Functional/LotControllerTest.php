<?php

namespace App\Tests\Functional;

use App\Entity\Categorie;
use App\Entity\Lot;
use App\Entity\Reservation;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Parcours du commerce : créer, publier, annuler un lot et confirmer un retrait.
 */
class LotControllerTest extends WebTestCase
{
    use DatabaseTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->reinitialiserBase();
    }

    /** @return array<string, string> */
    private function donneesDuFormulaire(int $categorieId, string $dateLimite = '+1 day'): array
    {
        $limite = new \DateTimeImmutable($dateLimite);

        return [
            'lot[titre]' => 'Baguettes du soir',
            'lot[categorie]' => (string) $categorieId,
            'lot[description]' => 'Une vingtaine de baguettes de la journée.',
            'lot[quantite]' => '20',
            'lot[unite]' => 'unités',
            'lot[poidsKg]' => '5',
            'lot[dateLimite]' => $limite->format('Y-m-d'),
            'lot[retraitDebut]' => $limite->format('Y-m-d').'T17:00',
            'lot[retraitFin]' => $limite->format('Y-m-d').'T19:00',
        ];
    }

    private function trouverLot(int $id): Lot
    {
        $lot = $this->em()->find(Lot::class, $id);
        self::assertNotNull($lot);

        return $lot;
    }

    public function testUnCommerceCreePuisPublieUnLot(): void
    {
        $commerce = $this->creerCommerce();
        $categorie = new Categorie('Boulangerie');
        $this->em()->persist($categorie);
        $this->em()->flush();

        $user = $commerce->getUser();
        self::assertNotNull($user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/espace/lots/nouveau');
        self::assertResponseIsSuccessful();

        $this->client->submit(
            $crawler->selectButton('Enregistrer le brouillon')->form($this->donneesDuFormulaire((int) $categorie->getId()))
        );
        self::assertResponseRedirects();

        $lot = $this->em()->getRepository(Lot::class)->findOneBy(['titre' => 'Baguettes du soir']);
        self::assertNotNull($lot);
        self::assertSame(Lot::STATUT_BROUILLON, $lot->getStatut());
        self::assertSame(5.0, $lot->getPoidsKg());
        $lotId = (int) $lot->getId();

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Baguettes du soir');
        self::assertSelectorTextContains('.badge', 'Brouillon');

        $this->client->submit($crawler->selectButton('Publier le lot')->form());
        self::assertResponseRedirects('/espace/lots/'.$lotId);
        self::assertSame(Lot::STATUT_PUBLIE, $this->trouverLot($lotId)->getStatut());

        // Une fois publié, le lot n'est plus modifiable
        $this->client->request('GET', '/espace/lots/'.$lotId.'/modifier');
        self::assertResponseStatusCodeSame(403);
    }

    public function testUneDateLimitePasseeEstRefusee(): void
    {
        $commerce = $this->creerCommerce();
        $categorie = new Categorie('Boulangerie');
        $this->em()->persist($categorie);
        $this->em()->flush();

        $user = $commerce->getUser();
        self::assertNotNull($user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/espace/lots/nouveau');
        $this->client->submit(
            $crawler->selectButton('Enregistrer le brouillon')->form($this->donneesDuFormulaire((int) $categorie->getId(), '-2 days'))
        );

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->em()->getRepository(Lot::class)->findOneBy(['titre' => 'Baguettes du soir']));
    }

    public function testUnCommerceNeVoitPasLesLotsDesAutres(): void
    {
        $proprietaire = $this->creerCommerce();
        $curieux = $this->creerCommerce();
        $lot = $this->creerLot($proprietaire);
        $this->em()->flush();

        $user = $curieux->getUser();
        self::assertNotNull($user);
        $this->client->loginUser($user);

        $this->client->request('GET', '/espace/lots/'.$lot->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testUneAssociationNePeutPasPublierDeLot(): void
    {
        $association = $this->creerAssociation();
        $this->em()->flush();

        $user = $association->getUser();
        self::assertNotNull($user);
        $this->client->loginUser($user);

        $this->client->request('GET', '/espace/lots/nouveau');
        self::assertResponseStatusCodeSame(403);
    }

    public function testLeCommerceConfirmeLeRetraitAvecLeCode(): void
    {
        $commerce = $this->creerCommerce();
        $association = $this->creerAssociation();
        $lot = $this->creerLot($commerce)->setStatut(Lot::STATUT_RESERVE);
        $this->em()->persist(new Reservation($lot, $association, 'ABCDEF'));
        $this->em()->flush();
        $lotId = (int) $lot->getId();

        $user = $commerce->getUser();
        self::assertNotNull($user);
        $this->client->loginUser($user);

        // Mauvais code : le lot reste réservé
        $crawler = $this->client->request('GET', '/espace/lots/'.$lotId);
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Confirmer le retrait')->form(['code' => 'ZZZZZZ']));
        self::assertSame(Lot::STATUT_RESERVE, $this->trouverLot($lotId)->getStatut());

        // Bon code, saisi en minuscules
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'incorrect');
        $this->client->submit($crawler->selectButton('Confirmer le retrait')->form(['code' => 'abcdef']));
        self::assertSame(Lot::STATUT_RECUPERE, $this->trouverLot($lotId)->getStatut());
    }

    public function testAnnulerUnLotReserveAnnuleAussiLaReservation(): void
    {
        $commerce = $this->creerCommerce();
        $association = $this->creerAssociation();
        $lot = $this->creerLot($commerce)->setStatut(Lot::STATUT_RESERVE);
        $this->em()->persist(new Reservation($lot, $association, 'ABCDEF'));
        $this->em()->flush();
        $lotId = (int) $lot->getId();

        $user = $commerce->getUser();
        self::assertNotNull($user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/espace/lots/'.$lotId);
        $this->client->submit($crawler->selectButton('Annuler le lot')->form());

        self::assertSame(Lot::STATUT_ANNULE, $this->trouverLot($lotId)->getStatut());
        $reservation = $this->em()->getRepository(Reservation::class)->findOneBy(['lot' => $lotId]);
        self::assertNotNull($reservation);
        self::assertSame(Reservation::STATUT_ANNULEE, $reservation->getStatut());
    }
}
