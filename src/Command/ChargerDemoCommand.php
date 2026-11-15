<?php

namespace App\Command;

use App\Entity\Lot;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Sur le site de démonstration, je veux toujours des données récentes :
 * cette commande charge le jeu de démo si la base est vide,
 * et le recharge s'il date de plus de quelques jours.
 */
#[AsCommand(
    name: 'app:demo:charger',
    description: 'Charge les données de démonstration si la base est vide ou si elles sont trop anciennes.',
)]
class ChargerDemoCommand extends Command
{
    public const JOURS_AVANT_RECHARGEMENT = 3;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->baseVide() && !$this->donneesTropAnciennes()) {
            $io->info('Les données de démonstration sont déjà en place et récentes : rien à faire.');

            return Command::SUCCESS;
        }

        $application = $this->getApplication();
        if (null === $application) {
            $io->error('Cette commande doit être lancée depuis la console Symfony.');

            return Command::FAILURE;
        }

        $io->note('Chargement des données de démonstration…');

        $chargement = new ArrayInput(['command' => 'doctrine:fixtures:load']);
        $chargement->setInteractive(false);

        return $application->find('doctrine:fixtures:load')->run($chargement, $output);
    }

    private function baseVide(): bool
    {
        $nombre = $this->em->createQuery('SELECT COUNT(u.id) FROM '.User::class.' u')->getSingleScalarResult();

        return 0 === (int) $nombre;
    }

    private function donneesTropAnciennes(): bool
    {
        $dernierLot = $this->em->createQuery('SELECT MAX(l.createdAt) FROM '.Lot::class.' l')->getSingleScalarResult();
        if (null === $dernierLot) {
            return true;
        }

        $limite = new \DateTimeImmutable(sprintf('-%d days', self::JOURS_AVANT_RECHARGEMENT));

        return new \DateTimeImmutable((string) $dernierLot) < $limite;
    }
}
