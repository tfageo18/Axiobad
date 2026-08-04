<?php

namespace App\Command;

use App\Entity\Licencie;
use App\Repository\LicencieRepository;
use App\Service\AnonymisationLicencieService;
use App\Service\AuditLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Anonymise automatiquement (RGPD art. 17, durée de conservation) les comptes désactivés depuis
 * plus de 3 ans. À la différence d'une suppression, les données comptables liées (adhésions,
 * paiements) sont conservées — seule l'identité et les données personnelles sont effacées.
 *
 * Destinée à être appelée périodiquement (cron, une fois par mois suffit).
 */
#[AsCommand(name: 'app:rgpd:purger-comptes-inactifs', description: 'Anonymise les comptes désactivés depuis plus de 3 ans (durée de conservation RGPD).')]
class PurgerComptesInactifsCommand extends Command
{
    private const DUREE_CONSERVATION_ANS = 3;

    public function __construct(
        private readonly LicencieRepository $licencieRepository,
        private readonly AnonymisationLicencieService $anonymisationService,
        private readonly AuditLogger $auditLogger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $seuil = new \DateTimeImmutable(sprintf('-%d years', self::DUREE_CONSERVATION_ANS));

        $candidats = array_filter(
            $this->licencieRepository->findAll(),
            static fn (Licencie $l) => !$l->isActif()
                && !$l->isAnonymise()
                && Licencie::EMAIL_ADMIN_DEFAUT !== $l->getEmail()
                && $l->getDesactiveLe()
                && $l->getDesactiveLe() < $seuil
        );

        foreach ($candidats as $licencie) {
            $nom = $licencie->getNomComplet();
            $this->anonymisationService->anonymiser($licencie);
            $this->auditLogger->log(AuditLogger::COMPTE_ANONYMISE, 'Licencie', $nom, null, 'Anonymisation automatique (durée de conservation dépassée)');
        }

        $io->success(sprintf('%d compte(s) anonymisé(s).', count($candidats)));

        return Command::SUCCESS;
    }
}
