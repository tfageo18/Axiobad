<?php

namespace App\Command;

use App\Entity\CommunicationEnvoi;
use App\Repository\CommunicationEnvoiRepository;
use App\Repository\LicencieRepository;
use App\Service\NotificationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Envoie les communications ciblées programmées (envoi différé) dont la date/heure planifiée est
 * arrivée. Destinée à être appelée périodiquement (cron, toutes les 5-10 min suffit).
 */
#[AsCommand(name: 'app:communication:envoyer-planifiees', description: 'Envoie les communications ciblées programmées dont la date est arrivée.')]
class EnvoyerCommunicationsPlanifieesCommand extends Command
{
    public function __construct(
        private readonly CommunicationEnvoiRepository $envoiRepository,
        private readonly LicencieRepository $licencieRepository,
        private readonly NotificationMailer $notificationMailer,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $aEnvoyer = $this->envoiRepository->findAEnvoyer(new \DateTimeImmutable());

        foreach ($aEnvoyer as $envoi) {
            $echecs = [];
            foreach ($envoi->getDestinatairesIds() as $id) {
                $destinataire = $this->licencieRepository->find($id);
                if (!$destinataire) {
                    continue;
                }
                if (!$this->notificationMailer->communicationCiblee($destinataire, $envoi->getSujet(), $envoi->getCorps(), $envoi->getPieceJointeChemin(), $envoi->getPieceJointeNom())) {
                    $echecs[] = $destinataire->getEmail() ?? sprintf('#%d', $destinataire->getId());
                }
            }

            $envoi->setStatut(CommunicationEnvoi::STATUT_ENVOYE)
                ->setEnvoyeLe(new \DateTimeImmutable())
                ->setNombreEchecs(count($echecs))
                ->setEmailsEnEchec($echecs ? implode(', ', $echecs) : null);
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d communication(s) planifiée(s) envoyée(s).', count($aEnvoyer)));

        return Command::SUCCESS;
    }
}
