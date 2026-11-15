<?php

namespace App\Tests\Functional;

use App\Entity\Association;
use App\Entity\Lot;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Parcours de l'administrateur : valider, suspendre, modérer.
 */
class AdminTest extends WebTestCase
{
    use DatabaseTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->reinitialiserBase();
    }

    private function connecterUnAdmin(): void
    {
        $admin = $this->creerUser(User::ROLE_ADMIN);
        $this->em()->persist($admin);
        $this->em()->flush();
        $this->client->loginUser($admin);
    }

    private function trouverAssociation(int $id): Association
    {
        $association = $this->em()->find(Association::class, $id);
        self::assertNotNull($association);

        return $association;
    }

    public function testLAdminValideUneAssociationEnAttente(): void
    {
        $association = $this->creerAssociation(validee: false);
        $this->em()->flush();
        $id = (int) $association->getId();
        $nom = $association->getNom();

        $this->connecterUnAdmin();

        $crawler = $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test="en-attente"]', $nom);

        $this->client->submit($crawler->selectButton('Valider')->form());
        self::assertResponseRedirects('/admin');
        self::assertTrue($this->trouverAssociation($id)->isValidated());

        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-test="validees"]', $nom);
    }

    public function testLAdminSuspendUneAssociation(): void
    {
        $association = $this->creerAssociation();
        $this->em()->flush();
        $id = (int) $association->getId();

        $this->connecterUnAdmin();

        $crawler = $this->client->request('GET', '/admin');
        $this->client->submit($crawler->selectButton('Suspendre')->form());

        self::assertResponseRedirects('/admin');
        self::assertFalse($this->trouverAssociation($id)->isValidated());
    }

    public function testLAdminAnnuleUnLotParModeration(): void
    {
        $lot = $this->creerLot($this->creerCommerce())->setStatut(Lot::STATUT_PUBLIE);
        $this->em()->flush();
        $id = (int) $lot->getId();

        $this->connecterUnAdmin();

        $crawler = $this->client->request('GET', '/admin');
        $this->client->submit($crawler->selectButton('Annuler ce lot')->form());

        self::assertResponseRedirects('/admin');
        $lot = $this->em()->find(Lot::class, $id);
        self::assertNotNull($lot);
        self::assertSame(Lot::STATUT_ANNULE, $lot->getStatut());
    }

    public function testUneValidationSansJetonCsrfEstIgnoree(): void
    {
        $association = $this->creerAssociation(validee: false);
        $this->em()->flush();
        $id = (int) $association->getId();

        $this->connecterUnAdmin();

        $this->client->request('POST', '/admin/associations/'.$id.'/valider');
        self::assertResponseRedirects('/admin');
        self::assertFalse($this->trouverAssociation($id)->isValidated());
    }

    public function testUnCommerceNAPasAccesALAdministration(): void
    {
        $commerce = $this->creerCommerce();
        $association = $this->creerAssociation(validee: false);
        $this->em()->flush();

        $user = $commerce->getUser();
        self::assertNotNull($user);
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/admin/associations/'.$association->getId().'/valider');
        self::assertResponseStatusCodeSame(403);
    }

    public function testUnVisiteurEstRenvoyeVersLaConnexion(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects();
        self::assertStringContainsString('/connexion', (string) $this->client->getResponse()->headers->get('Location'));
    }
}
