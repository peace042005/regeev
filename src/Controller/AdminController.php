<?php

namespace App\Controller;

use App\Entity\Association;
use App\Entity\Commerce;
use App\Entity\Lot;
use App\Service\LotManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration : validation des associations, vue d'ensemble et modération des lots.
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LotManager $lotManager,
    ) {
    }

    #[Route('', name: 'app_admin', methods: ['GET'])]
    public function index(): Response
    {
        $associations = $this->em->getRepository(Association::class);

        return $this->render('admin/index.html.twig', [
            'enAttente' => $associations->findBy(['isValidated' => false], ['id' => 'DESC']),
            'validees' => $associations->findBy(['isValidated' => true], ['nom' => 'ASC']),
            'nbCommerces' => $this->em->getRepository(Commerce::class)->count([]),
            'lotsParStatut' => $this->lotsParStatut(),
            'derniersLots' => $this->em->getRepository(Lot::class)->findBy([], ['createdAt' => 'DESC'], 15),
        ]);
    }

    #[Route('/associations/{id}/valider', name: 'app_admin_association_validate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function validate(int $id, Request $request): Response
    {
        return $this->changerValidation($id, $request, true, 'Association validée : elle peut maintenant réserver des lots.');
    }

    #[Route('/associations/{id}/suspendre', name: 'app_admin_association_suspend', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function suspend(int $id, Request $request): Response
    {
        return $this->changerValidation($id, $request, false, 'Association suspendue : elle ne peut plus réserver.');
    }

    #[Route('/lots/{id}/annuler', name: 'app_admin_lot_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelLot(int $id, Request $request): Response
    {
        $lot = $this->em->find(Lot::class, $id);
        if (null === $lot) {
            throw $this->createNotFoundException('Ce lot n\'existe pas.');
        }

        if (!$this->isCsrfTokenValid('admin-lot'.$id, $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');
        } else {
            try {
                $this->lotManager->annuler($lot);
                $this->addFlash('success', 'Lot annulé par la modération.');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_admin');
    }

    private function changerValidation(int $id, Request $request, bool $validee, string $message): Response
    {
        $association = $this->em->find(Association::class, $id);
        if (null === $association) {
            throw $this->createNotFoundException('Cette association n\'existe pas.');
        }

        if (!$this->isCsrfTokenValid('association'.$id, $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Votre session a expiré, merci de réessayer.');
        } else {
            $association->setIsValidated($validee);
            $this->em->flush();
            $this->addFlash('success', $message);
        }

        return $this->redirectToRoute('app_admin');
    }

    /**
     * @return array<string, int> nombre de lots pour chaque statut
     */
    private function lotsParStatut(): array
    {
        $lignes = $this->em->createQuery(
            'SELECT l.statut AS statut, COUNT(l.id) AS nombre FROM '.Lot::class.' l GROUP BY l.statut'
        )->getArrayResult();

        $compteurs = [];
        foreach ($lignes as $ligne) {
            $compteurs[(string) $ligne['statut']] = (int) $ligne['nombre'];
        }

        return $compteurs;
    }
}
