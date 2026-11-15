<?php

namespace App\Service;

/**
 * Transforme la liste brute des dons récupérés en indicateurs d'impact.
 * Classe volontairement sans dépendance (ni base de données, ni framework)
 * pour pouvoir la tester unitairement.
 */
class ImpactCalculator
{
    public function __construct(private readonly float $kgParRepas = 0.5)
    {
        if ($kgParRepas <= 0) {
            throw new \InvalidArgumentException('Le poids d\'un repas doit être strictement positif.');
        }
    }

    /**
     * @param iterable<array{poids: float|int|string, dateRetrait: \DateTimeInterface, categorie: string, ville: string, commerceId: int|string}> $dons
     *
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
    public function synthese(iterable $dons, \DateTimeImmutable $reference, int $nbMois = 6, int $topVilles = 5): array
    {
        $parMois = $this->moisVides($reference, $nbMois);
        $parCategorie = [];
        $parVille = [];
        $commerces = [];
        $totalKg = 0.0;
        $nbDons = 0;

        foreach ($dons as $don) {
            $poids = (float) $don['poids'];
            $totalKg += $poids;
            ++$nbDons;
            $commerces[(string) $don['commerceId']] = true;

            $mois = $don['dateRetrait']->format('Y-m');
            if (array_key_exists($mois, $parMois)) {
                $parMois[$mois] += $poids;
            }

            $parCategorie[$don['categorie']] = ($parCategorie[$don['categorie']] ?? 0.0) + $poids;
            $parVille[$don['ville']] = ($parVille[$don['ville']] ?? 0.0) + $poids;
        }

        arsort($parCategorie);
        arsort($parVille);

        return [
            'totalKg' => round($totalKg, 1),
            'nbDons' => $nbDons,
            'repas' => $this->repasEquivalents($totalKg),
            'commercesActifs' => count($commerces),
            'parMois' => array_map(static fn (float $kg): float => round($kg, 1), $parMois),
            'parCategorie' => array_map(static fn (float $kg): float => round($kg, 1), $parCategorie),
            'parVille' => array_map(static fn (float $kg): float => round($kg, 1), array_slice($parVille, 0, $topVilles, true)),
        ];
    }

    public function repasEquivalents(float $kg): int
    {
        return (int) floor($kg / $this->kgParRepas);
    }

    /**
     * Prépare les $nbMois derniers mois (du plus ancien au plus récent) à zéro,
     * pour que le graphique affiche aussi les mois sans don.
     *
     * @return array<string, float>
     */
    private function moisVides(\DateTimeImmutable $reference, int $nbMois): array
    {
        $mois = [];
        $premierDuMois = $reference->modify('first day of this month')->setTime(0, 0);

        for ($i = $nbMois - 1; $i >= 0; --$i) {
            $mois[$premierDuMois->modify(sprintf('-%d months', $i))->format('Y-m')] = 0.0;
        }

        return $mois;
    }
}
