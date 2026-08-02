<?php

namespace App\Command;

use App\Service\GestionInscriptionCreneau;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:creneau:expirer-promotions', description: "Fait passer en liste d'attente les promotions de créneau non confirmées à temps, et promeut la personne suivante.")]
class ExpirerPromotionsCreneauCommand extends Command
{
    public function __construct(private readonly GestionInscriptionCreneau $gestionInscriptionCreneau)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $nombre = $this->gestionInscriptionCreneau->expirerPromotions();
        $io->writeln(sprintf('%d promotion(s) expirée(s) traitée(s).', $nombre));

        return Command::SUCCESS;
    }
}
