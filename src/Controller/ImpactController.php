<?php

namespace App\Controller;

use App\Service\StatsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tableau de bord public : les données d'impact de la plateforme,
 * consultables en page web, en JSON (API) et en CSV (export).
 */
class ImpactController extends AbstractController
{
    #[Route('/impact', name: 'app_impact')]
    public function index(StatsProvider $statsProvider): Response
    {
        return $this->render('impact/index.html.twig', [
            'stats' => $statsProvider->synthese(),
        ]);
    }

    #[Route('/api/stats', name: 'api_stats', methods: ['GET'])]
    public function api(StatsProvider $statsProvider): JsonResponse
    {
        return $this->json($statsProvider->synthese());
    }

    #[Route('/impact/export.csv', name: 'app_impact_export', methods: ['GET'])]
    public function export(StatsProvider $statsProvider): Response
    {
        $stats = $statsProvider->synthese();

        $lignes = ['mois;kg_sauves'];
        foreach ($stats['parMois'] as $mois => $kg) {
            $lignes[] = sprintf('%s;%s', $mois, number_format($kg, 1, ',', ''));
        }

        return new Response(implode("\n", $lignes)."\n", Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="regeev-impact.csv"',
        ]);
    }
}
