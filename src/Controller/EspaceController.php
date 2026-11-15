<?php

namespace App\Controller;

use App\Entity\Association;
use App\Entity\Commerce;
use App\Entity\Lot;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace personnel : son contenu dépend du rôle de la personne connectée.
 */
#[IsGranted('ROLE_USER')]
class EspaceController extends AbstractController
{
    #[Route('/espace', name: 'app_espace')]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        $commerce = $em->getRepository(Commerce::class)->findOneBy(['user' => $user]);
        $association = $em->getRepository(Association::class)->findOneBy(['user' => $user]);

        $lots = [];
        if (null !== $commerce) {
            $lots = $em->getRepository(Lot::class)->findBy(['commerce' => $commerce], ['createdAt' => 'DESC'], 20);
        }

        $reservations = [];
        if (null !== $association) {
            $reservations = $em->getRepository(Reservation::class)->findBy(['association' => $association], ['dateReservation' => 'DESC'], 20);
        }

        return $this->render('espace/index.html.twig', [
            'commerce' => $commerce,
            'association' => $association,
            'lots' => $lots,
            'reservations' => $reservations,
        ]);
    }
}
