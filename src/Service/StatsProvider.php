<?php

namespace App\Service;

use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Va chercher en base les dons effectivement récupérés
 * et délègue le calcul des indicateurs à ImpactCalculator.
 */
class StatsProvider
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImpactCalculator $calculator,
    ) {
    }

    /**
     * @return array{
     *     totalKg: float,
     *     nbDons: int,
     *     repas: int,
     *     commercesActifs: int,
     *     parMois: array<string, float>,
     *     parCategorie: array<string, float>,
     *     parVille: array<string, float>
     * }
     */
    public function synthese(?\DateTimeImmutable $reference = null): array
    {
        $dons = $this->em->createQuery(
            'SELECT l.poidsKg AS poids, r.dateRetrait AS dateRetrait, c.nom AS categorie, co.ville AS ville, co.id AS commerceId
             FROM '.Reservation::class.' r
             JOIN r.lot l
             JOIN l.categorie c
             JOIN l.commerce co
             WHERE r.statut = :statut AND r.dateRetrait IS NOT NULL'
        )
            ->setParameter('statut', Reservation::STATUT_RECUPEREE)
            ->getArrayResult();

        return $this->calculator->synthese($dons, $reference ?? new \DateTimeImmutable());
    }
}
