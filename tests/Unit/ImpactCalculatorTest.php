<?php

namespace App\Tests\Unit;

use App\Service\ImpactCalculator;
use PHPUnit\Framework\TestCase;

class ImpactCalculatorTest extends TestCase
{
    private \DateTimeImmutable $reference;

    protected function setUp(): void
    {
        $this->reference = new \DateTimeImmutable('2026-11-14');
    }

    /** @return array{poids: float, dateRetrait: \DateTimeImmutable, categorie: string, ville: string, commerceId: int} */
    private function don(float $poids, string $date, string $categorie = 'Boulangerie', string $ville = 'Belfort', int $commerceId = 1): array
    {
        return [
            'poids' => $poids,
            'dateRetrait' => new \DateTimeImmutable($date),
            'categorie' => $categorie,
            'ville' => $ville,
            'commerceId' => $commerceId,
        ];
    }

    public function testSansAucunDonToutEstAZero(): void
    {
        $stats = (new ImpactCalculator())->synthese([], $this->reference);

        self::assertSame(0.0, $stats['totalKg']);
        self::assertSame(0, $stats['nbDons']);
        self::assertSame(0, $stats['repas']);
        self::assertSame(0, $stats['commercesActifs']);
        self::assertSame([], $stats['parCategorie']);
        self::assertSame([], $stats['parVille']);
    }

    public function testLesSixDerniersMoisSontToujoursPresentsDansLOrdre(): void
    {
        $stats = (new ImpactCalculator())->synthese([], $this->reference);

        self::assertSame(
            ['2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11'],
            array_keys($stats['parMois'])
        );
    }

    public function testTotauxEtRepartitions(): void
    {
        $dons = [
            $this->don(10.0, '2026-11-02', 'Boulangerie', 'Belfort', 1),
            $this->don(4.5, '2026-11-10', 'Épicerie', 'Belfort', 2),
            $this->don(20.0, '2026-10-20', 'Boulangerie', 'Lyon', 3),
            $this->don(3.0, '2026-10-21', 'Épicerie', 'Lyon', 3),
        ];

        $stats = (new ImpactCalculator())->synthese($dons, $this->reference);

        self::assertSame(37.5, $stats['totalKg']);
        self::assertSame(4, $stats['nbDons']);
        self::assertSame(3, $stats['commercesActifs']);
        self::assertSame(14.5, $stats['parMois']['2026-11']);
        self::assertSame(23.0, $stats['parMois']['2026-10']);
        self::assertSame(0.0, $stats['parMois']['2026-09']);
        self::assertSame(['Boulangerie' => 30.0, 'Épicerie' => 7.5], $stats['parCategorie']);
        self::assertSame(['Lyon' => 23.0, 'Belfort' => 14.5], $stats['parVille']);
    }

    public function testUnDonTropAncienCompteDansLeTotalMaisPasDansLeGraphiqueMensuel(): void
    {
        $stats = (new ImpactCalculator())->synthese([$this->don(8.0, '2025-01-15')], $this->reference);

        self::assertSame(8.0, $stats['totalKg']);
        self::assertSame(0.0, array_sum($stats['parMois']));
    }

    public function testLeClassementDesVillesEstLimite(): void
    {
        $dons = [];
        foreach (['A' => 1, 'B' => 5, 'C' => 3, 'D' => 9] as $ville => $kg) {
            $dons[] = $this->don((float) $kg, '2026-11-01', 'Épicerie', $ville);
        }

        $stats = (new ImpactCalculator())->synthese($dons, $this->reference, topVilles: 2);

        self::assertSame(['D' => 9.0, 'B' => 5.0], $stats['parVille']);
    }

    public function testRepasEquivalents(): void
    {
        self::assertSame(25, (new ImpactCalculator())->repasEquivalents(12.5));
        self::assertSame(2, (new ImpactCalculator())->repasEquivalents(1.2), 'On arrondit toujours à l\'inférieur.');
        self::assertSame(10, (new ImpactCalculator(1.0))->repasEquivalents(10.9));
    }

    public function testUnPoidsDeRepasNulEstRefuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImpactCalculator(0.0);
    }

    public function testLaFinDeMoisNeDecalePasLesMois(): void
    {
        // Piège classique : "31 mars - 1 mois" donne début mars en PHP
        $stats = (new ImpactCalculator())->synthese([], new \DateTimeImmutable('2026-03-31'), nbMois: 3);

        self::assertSame(['2026-01', '2026-02', '2026-03'], array_keys($stats['parMois']));
    }
}
