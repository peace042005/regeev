<?php

namespace App\Tests\Unit;

use App\Entity\Commerce;
use App\Entity\Lot;
use App\Entity\User;
use App\Service\CodeRetraitGenerator;
use PHPUnit\Framework\TestCase;

class EntitesTest extends TestCase
{
    public function testUnLotEstPerimeLeLendemainDeSaDateLimite(): void
    {
        $lot = (new Lot())->setDateLimite(new \DateTimeImmutable('2026-11-14'));

        self::assertFalse($lot->estPerime(new \DateTimeImmutable('2026-11-13 10:00')));
        self::assertFalse($lot->estPerime(new \DateTimeImmutable('2026-11-14 23:59')));
        self::assertTrue($lot->estPerime(new \DateTimeImmutable('2026-11-15 00:01')));
    }

    public function testUnNouveauLotEstUnBrouillon(): void
    {
        self::assertSame(Lot::STATUT_BROUILLON, (new Lot())->getStatut());
    }

    public function testLEmailEstNormalise(): void
    {
        $user = (new User())->setEmail('  Marcella@Exemple.FR ');

        self::assertSame('marcella@exemple.fr', $user->getEmail());
        self::assertSame('marcella@exemple.fr', $user->getUserIdentifier());
    }

    public function testToutUtilisateurALeRoleUser(): void
    {
        $user = (new User())->setRoles([User::ROLE_COMMERCE]);

        self::assertSame([User::ROLE_COMMERCE, 'ROLE_USER'], $user->getRoles());
    }

    public function testLeSiretEstNettoyeDeSesEspaces(): void
    {
        self::assertSame('73282932000074', (new Commerce())->setSiret('732 829 320 00074')->getSiret());
    }

    public function testLeCodeDeRetraitEstLisible(): void
    {
        $generator = new CodeRetraitGenerator();

        for ($i = 0; $i < 50; ++$i) {
            $code = $generator->generer();
            self::assertSame(6, strlen($code));
            self::assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]+$/', $code, 'Pas de caractères ambigus (0, O, 1, I, L).');
        }
    }
}
