<?php

namespace App\Command;

use App\Service\LotManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:lots:expirer',
    description: 'Passe en « expiré » les lots dont la date limite est dépassée (à lancer chaque nuit).',
)]
class ExpirerLotsCommand extends Command
{
    public function __construct(private readonly LotManager $lotManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $nombre = $this->lotManager->expirerLotsPerimes();

        $io->success(sprintf('%d lot(s) expiré(s).', $nombre));

        return Command::SUCCESS;
    }
}
