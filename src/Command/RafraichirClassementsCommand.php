<?php

namespace App\Command;

use App\Repository\LicencieRepository;
use App\Service\FfbadClassementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:classements:rafraichir',
    description: 'Rafraîchit le classement FFBaD de tous les licenciés ayant un numéro de licence renseigné',
)]
class RafraichirClassementsCommand extends Command
{
    public function __construct(
        private readonly LicencieRepository $licencieRepository,
        private readonly FfbadClassementService $classementService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $licencies = $this->licencieRepository->findAvecNumeroLicence();

        $reussis = 0;
        $echecs = 0;

        foreach ($licencies as $licencie) {
            if ($this->classementService->mettreAJourClassement($licencie)) {
                ++$reussis;
            } else {
                ++$echecs;
                $io->warning(sprintf('Échec pour %s (licence %s).', $licencie->getNomComplet(), $licencie->getNumeroLicence()));
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d classement(s) mis à jour, %d échec(s) sur %d licencié(s) traité(s).', $reussis, $echecs, count($licencies)));

        return Command::SUCCESS;
    }
}
