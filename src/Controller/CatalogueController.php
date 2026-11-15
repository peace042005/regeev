<?php

namespace App\Controller;

use App\Entity\Association;
use App\Entity\Categorie;
use App\Entity\Lot;
use App\Entity\Reservation;
use App\Service\LotCatalogue;
use App\Service\LotManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Côté association : parcourir les lots disponibles, réserver, se désister.
 */
#[IsGranted('ROLE_ASSOCIATION')]
class CatalogueController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LotManager $lotManager,
        private readonly LotCatalogue $catalogue,
    ) {
    }

    #[Route('/lots', name: 'app_catalogue', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Le filtre « Toutes » envoie une valeur vide : on ne garde que les identifiants numériques
        $categorieBrute = $request->query->getString('categorie');
        $categorieId = ctype_digit($categorieBrute) ? (int) $categorieBrute : null;
        $ville = trim($request->query->getString('ville'));

        return $this->render('catalogue/index.html.twig', [
            'lots' => $this->catalogue->rechercher($categorieId, $ville),
            'categories' => $this->em->getRepository(Categorie::class)->findBy([], ['nom' => 'ASC']),
            'villes' => $this->catalogue->villes(),
            'filtres' => ['categorie' => $categorieId, 'ville' => $ville],
            'association' => $this->associationConnectee(),
        ]);
    }

    #[Route('/lots/{id}', name: 'app_catalogue_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $lot = $this->em->find(Lot::class, $id);
        if (null === $lot || Lot::STATUT_PUBLIE !== $lot->getStatut()) {
            throw $this->createNotFoundException('Ce lot n\'est pas disponible.');
        }

        return $this->render('catalogue/show.html.twig', [
            'lot' => $lot,
            'association' => $this->associationConnectee(),
        ]);
    }

    #[Route('/lots/{id}/reserver', name: 'app_catalogue_reserve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reserve(int $id, Request $request): Response
    {
        $lot = $this->em->find(Lot::class, $id);
        if (null === $lot) {
            throw $this->createNotFoundException('Ce lot n\'existe pas.');
        }

        if (!$this->isCsrfTokenValid('reserver'.$id, $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');

            return $this->redirectToRoute('app_catalogue');
        }

        if (Lot::STATUT_PUBLIE !== $lot->getStatut()) {
            $this->addFlash('error', 'Ce lot vient d\'être réservé par une autre association ou n\'est plus disponible.');

            return $this->redirectToRoute('app_catalogue');
        }

        try {
            $reservation = $this->lotManager->reserver($lot, $this->associationConnectee());
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_catalogue');
        }

        $this->addFlash('success', sprintf(
            'Lot réservé ! Votre code de retrait : %s. Présentez-le au commerce le %s entre %s et %s.',
            $reservation->getCodeRetrait(),
            $lot->getRetraitDebut()?->format('d/m/Y'),
            $lot->getRetraitDebut()?->format('H:i'),
            $lot->getRetraitFin()?->format('H:i'),
        ));

        return $this->redirectToRoute('app_espace');
    }

    #[Route('/reservations/{id}/annuler', name: 'app_reservation_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelReservation(int $id, Request $request): Response
    {
        $reservation = $this->em->find(Reservation::class, $id);
        if (null === $reservation) {
            throw $this->createNotFoundException('Cette réservation n\'existe pas.');
        }

        // Une association ne peut annuler que ses propres réservations
        if ($reservation->getAssociation()->getId() !== $this->associationConnectee()->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('reservation'.$id, $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');
        } else {
            try {
                $this->lotManager->annulerReservation($reservation);
                $this->addFlash('success', 'Réservation annulée : le lot est de nouveau disponible pour les autres associations.');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_espace');
    }

    private function associationConnectee(): Association
    {
        $association = $this->em->getRepository(Association::class)->findOneBy(['user' => $this->getUser()]);
        if (null === $association) {
            throw $this->createAccessDeniedException('Aucune association n\'est liée à ce compte.');
        }

        return $association;
    }
}
