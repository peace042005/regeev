<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Garde-fou : les migrations doivent produire exactement le schéma décrit par mes entités.
 * Les migrations étant écrites pour PostgreSQL, ce test ne tourne que sur PostgreSQL
 * (donc dans le pipeline d'intégration continue) et il est ignoré en local sur SQLite.
 */
class MigrationsTest extends KernelTestCase
{
    use DatabaseTrait;

    public function testLesMigrationsCorrespondentAuxEntites(): void
    {
        $kernel = self::bootKernel();
        $em = $this->em();
        $connexion = $em->getConnection();

        if (!$connexion->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::markTestSkipped('Les migrations sont écrites pour PostgreSQL.');
        }

        // Je repars d'une base totalement vide
        $connexion->executeStatement('DROP SCHEMA public CASCADE');
        $connexion->executeStatement('CREATE SCHEMA public');

        $application = new Application($kernel);
        $migration = new CommandTester($application->find('doctrine:migrations:migrate'));
        $migration->execute([], ['interactive' => false]);
        $migration->assertCommandIsSuccessful();

        $ecarts = (new SchemaTool($em))->getUpdateSchemaSql($em->getMetadataFactory()->getAllMetadata());
        $ecarts = array_values(array_filter(
            $ecarts,
            static fn (string $sql): bool => !str_contains($sql, 'doctrine_migration_versions') && 'CREATE SCHEMA public' !== $sql,
        ));

        self::assertSame([], $ecarts, "Le schéma issu des migrations diffère des entités :\n".implode("\n", $ecarts));
    }
}
