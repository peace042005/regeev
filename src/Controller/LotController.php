<?php

namespace App\Controller;

use App\Entity\Commerce;
use App\Entity\Lot;
use App\Entity\Reservation;
use App\Form\LotType;
use App\Security\LotVoter;
use App\Service\LotManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des lots par le commerce : création, modification, publication,
 * annulation et confirmation du retrait.
 */
#[Route('/espace/lots')]
#[IsGranted('ROLE_COMMERCE')]
class LotController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LotManager $lotManager,
    ) {
    }

    #[Route('/nouveau', name: 'app_lot_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $lot = (new Lot())->setCommerce($this->commerceConnecte());
        $form = $this->createForm(LotType::class, $lot);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($lot);
            $this->em->flush();
            $this->addFlash('success', 'Lot enregistré en brouillon. Publiez-le pour le rendre visible des associations.');

            return $this->redirectToRoute('app_lot_show', ['id' => $lot->getId()]);
        }

        return $this->render('lot/form.html.twig', ['form' => $form, 'lot' => $lot]);
    }

    #[Route('/{id}', name: 'app_lot_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $lot = $this->lot($id, LotVoter::GERER);

        return $this->render('lot/show.html.twig', [
            'lot' => $lot,
            'reservation' => $this->reservationActive($lot),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_lot_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $lot = $this->lot($id, LotVoter::MODIFIER);
        $form = $this->createForm(LotType::class, $lot);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Lot modifié.');

            return $this->redirectToRoute('app_lot_show', ['id' => $lot->getId()]);
        }

        return $this->render('lot/form.html.twig', ['form' => $form, 'lot' => $lot]);
    }

    #[Route('/{id}/publier', name: 'app_lot_publish', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function publish(int $id, Request $request): Response
    {
        $lot = $this->lot($id, LotVoter::GERER);

        return $this->executer($lot, $request, 'Lot publié : les associations peuvent maintenant le réserver.', function () use ($lot): void {
            $this->lotManager->publier($lot);
        });
    }

    #[Route('/{id}/annuler', name: 'app_lot_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(int $id, Request $request): Response
    {
        $lot = $this->lot($id, LotVoter::GERER);

        return $this->executer($lot, $request, 'Lot annulé.', function () use ($lot): void {
            $this->lotManager->annuler($lot);
        });
    }

    #[Route('/{id}/retrait', name: 'app_lot_pickup', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function pickup(int $id, Request $request): Response
    {
        $lot = $this->lot($id, LotVoter::GERER);

        return $this->executer($lot, $request, 'Retrait confirmé. Merci pour ce don !', function () use ($lot, $request): void {
            $reservation = $this->reservationActive($lot);
            if (null === $reservation) {
                throw new \DomainException('Ce lot n\'a pas de réservation en cours.');
            }

            $this->lotManager->confirmerRetrait($reservation, $request->getPayload()->getString('code'));
        });
    }

    /**
     * Point commun des actions POST : vérification du jeton CSRF,
     * exécution de la règle métier, message de retour, redirection.
     */
    private function executer(Lot $lot, Request $request, string $messageSucces, callable $action): Response
    {
        if (!$this->isCsrfTokenValid('lot'.$lot->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');
        } else {
            try {
                $action();
                $this->addFlash('success', $messageSucces);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_lot_show', ['id' => $lot->getId()]);
    }

    private function lot(int $id, string $droit): Lot
    {
        $lot = $this->em->find(Lot::class, $id);
        if (null === $lot) {
            throw $this->createNotFoundException('Ce lot n\'existe pas.');
        }

        $this->denyAccessUnlessGranted($droit, $lot);

        return $lot;
    }

    private function commerceConnecte(): Commerce
    {
        $commerce = $this->em->getRepository(Commerce::class)->findOneBy(['user' => $this->getUser()]);
        if (null === $commerce) {
            throw $this->createAccessDeniedException('Aucun commerce n\'est associé à ce compte.');
        }

        return $commerce;
    }

    private function reservationActive(Lot $lot): ?Reservation
    {
        return $this->em->getRepository(Reservation::class)->findOneBy([
            'lot' => $lot,
            'statut' => Reservation::STATUT_ACTIVE,
        ]);
    }
}
