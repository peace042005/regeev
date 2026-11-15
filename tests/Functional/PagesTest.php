<?php

namespace App\Tests\Functional;

use App\Entity\Lot;
use App\Entity\Reservation;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels : je simule un navigateur et je vérifie les pages.
 */
class PagesTest extends WebTestCase
{
    use DatabaseTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->reinitialiserBase();
    }

    public function testAccueilAvecBaseVide(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'invendus');
        self::assertSelectorTextSame('[data-test="total-kg"]', '0');
    }

    public function testTableauDeBordEtApiRefletentLesDonsRecuperes(): void
    {
        $commerce = $this->creerCommerce('Belfort');
        $association = $this->creerAssociation();

        // 12,5 kg récupérés ce mois-ci
        $recupere = $this->creerLot($commerce, 12.5)->setStatut(Lot::STATUT_RECUPERE);
        $reservation = new Reservation($recupere, $association, 'ABCDEF');
        $reservation->marquerRecuperee(new \DateTimeImmutable());
        $this->em()->persist($reservation);

        // Un lot seulement réservé ne doit pas compter
        $reserve = $this->creerLot($commerce, 99)->setStatut(Lot::STATUT_RESERVE);
        $this->em()->persist(new Reservation($reserve, $association, 'GHJKMN'));
        $this->em()->flush();

        $this->client->request('GET', '/impact');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[data-test="total-kg"]', '13');

        $this->client->request('GET', '/api/stats');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $stats = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertEqualsWithDelta(12.5, $stats['totalKg'], 0.001);
        self::assertSame(1, $stats['nbDons']);
        self::assertSame(25, $stats['repas']);
        self::assertSame(1, $stats['commercesActifs']);
        self::assertEqualsWithDelta(12.5, $stats['parVille']['Belfort'], 0.001);
        self::assertEqualsWithDelta(12.5, $stats['parMois'][date('Y-m')], 0.001);
    }

    public function testExportCsv(): void
    {
        $this->client->request('GET', '/impact/export.csv');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/csv', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringStartsWith('mois;kg_sauves', (string) $this->client->getResponse()->getContent());
    }

    public function testInscriptionDUnCommerce(): void
    {
        $crawler = $this->client->request('GET', '/inscription/commerce');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer mon compte')->form([
            'commerce_registration[nom]' => 'Boulangerie du Lion',
            'commerce_registration[siret]' => '732 829 320 00074',
            'commerce_registration[adresse]' => '3 faubourg de France',
            'commerce_registration[codePostal]' => '90000',
            'commerce_registration[ville]' => 'Belfort',
            'commerce_registration[email]' => 'Lion@Exemple.fr',
            'commerce_registration[plainPassword]' => 'motdepasse123',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/connexion');

        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => 'lion@exemple.fr']);
        self::assertNotNull($user);
        self::assertContains(User::ROLE_COMMERCE, $user->getRoles());
        self::assertNotSame('motdepasse123', $user->getPassword(), 'Le mot de passe doit être haché.');
    }

    public function testInscriptionRefuseeAvecUnSiretInvalide(): void
    {
        $crawler = $this->client->request('GET', '/inscription/commerce');

        $form = $crawler->selectButton('Créer mon compte')->form([
            'commerce_registration[nom]' => 'Faux commerce',
            'commerce_registration[siret]' => '12345678901234',
            'commerce_registration[adresse]' => '1 rue Inconnue',
            'commerce_registration[codePostal]' => '90000',
            'commerce_registration[ville]' => 'Belfort',
            'commerce_registration[email]' => 'faux@exemple.fr',
            'commerce_registration[plainPassword]' => 'motdepasse123',
        ]);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->em()->getRepository(User::class)->findOneBy(['email' => 'faux@exemple.fr']));
    }

    public function testEspaceReserveAuxPersonnesConnectees(): void
    {
        $this->client->request('GET', '/espace');
        self::assertResponseRedirects();
        self::assertStringContainsString('/connexion', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testEspaceDUnCommerceConnecte(): void
    {
        $commerce = $this->creerCommerce();
        $this->creerLot($commerce);
        $this->em()->flush();

        $user = $commerce->getUser();
        self::assertNotNull($user);
        $this->client->loginUser($user);

        $this->client->request('GET', '/espace');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Lot de test');
    }
}
