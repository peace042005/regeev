<?php

namespace App\Controller;

use App\Service\StatsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(StatsProvider $statsProvider): Response
    {
        return $this->render('home/index.html.twig', [
            'stats' => $statsProvider->synthese(),
        ]);
    }
}
