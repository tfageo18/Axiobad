<?php

namespace App\Command;

use App\Entity\Adhesion;
use App\Entity\Inscription;
use App\Entity\Licencie;
use App\Entity\Presence;
use App\Repository\AdhesionRepository;
use App\Repository\CreneauRepository;
use App\Repository\EvenementRepository;
use App\Repository\LicencieRepository;
use App\Repository\PresenceRepository;
use App\Repository\RencontreInterclubRepository;
use App\Repository\SaisonRepository;
use App\Service\NotificationMailer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Envoie les notifications récapitulatives quotidiennes : rappel de réponse à un créneau,
 * promotion de liste d'attente bientôt expirée, invitation bientôt expirée, évènement à venir,
 * convocation interclubs à venir sans réponse, et (le lundi) adhésions impayées.
 *
 * Destinée à être appelée une fois par jour (cron).
 */
#[AsCommand(name: 'app:notifications:quotidiennes', description: 'Envoie les notifications récapitulatives quotidiennes par email.')]
class EnvoyerNotificationsQuotidiennesCommand extends Command
{
    private const JOURS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

    public function __construct(
        private readonly LicencieRepository $licencieRepository,
        private readonly CreneauRepository $creneauRepository,
        private readonly PresenceRepository $presenceRepository,
        private readonly AdhesionRepository $adhesionRepository,
        private readonly SaisonRepository $saisonRepository,
        private readonly EvenementRepository $evenementRepository,
        private readonly RencontreInterclubRepository $rencontreInterclubRepository,
        private readonly NotificationMailer $notificationMailer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $envoyes = 0;

        $envoyes += $this->rappelsReponseCreneau($io);
        $envoyes += $this->promotionsBientotExpirees($io);
        $envoyes += $this->invitationsBientotExpirees($io);
        $envoyes += $this->evenementsAVenir($io);
        $envoyes += $this->convocationsAVenir($io);

        if ('Lundi' === self::nomJourFrancais(new \DateTimeImmutable())) {
            $envoyes += $this->adhesionsImpayees($io);
        }

        $io->success(sprintf('%d notification(s) envoyée(s).', $envoyes));

        return Command::SUCCESS;
    }

    private function rappelsReponseCreneau(SymfonyStyle $io): int
    {
        $dansDeuxJours = (new \DateTimeImmutable('today'))->modify('+2 days');
        $jour = self::nomJourFrancais($dansDeuxJours);
        $envoyes = 0;

        $licencies = array_values(array_filter(
            $this->licencieRepository->findAll(),
            static fn (Licencie $l) => $l->aUnCompte() && $l->isActif()
        ));

        foreach ($this->creneauRepository->findAll() as $creneau) {
            if (!$creneau->isActif() || $creneau->getJourSemaine() !== $jour) {
                continue;
            }
            if ($creneau->getRecurrenceDebut() && $dansDeuxJours < $creneau->getRecurrenceDebut()) {
                continue;
            }
            if ($creneau->getRecurrenceFin() && $dansDeuxJours > $creneau->getRecurrenceFin()) {
                continue;
            }

            foreach ($licencies as $licencie) {
                if (!$creneau->correspondA($licencie)) {
                    continue;
                }
                if ($this->presenceRepository->findOneByCreneauLicencieEtDate($creneau, $licencie, $dansDeuxJours)) {
                    continue;
                }
                if ($this->notificationMailer->rappelReponseCreneau($licencie, $creneau, $dansDeuxJours)) {
                    ++$envoyes;
                }
            }
        }

        $io->writeln(sprintf('%d rappel(s) de réponse à un créneau envoyé(s).', $envoyes));

        return $envoyes;
    }

    private function promotionsBientotExpirees(SymfonyStyle $io): int
    {
        $maintenant = new \DateTimeImmutable();
        $envoyes = 0;

        $presences = array_filter(
            $this->presenceRepository->findAll(),
            static fn (Presence $p) => $p->estEnAttenteConfirmation()
                && $p->getPromotionExpiresAt()
                && $p->getPromotionExpiresAt() > $maintenant
                && $p->getPromotionExpiresAt() <= $maintenant->modify('+24 hours')
        );

        foreach ($presences as $presence) {
            if ($this->notificationMailer->promotionBientotExpiree($presence->getLicencie(), $presence->getCreneau(), $presence->getDate())) {
                ++$envoyes;
            }
        }

        $io->writeln(sprintf('%d rappel(s) de promotion bientôt expirée envoyé(s).', $envoyes));

        return $envoyes;
    }

    private function invitationsBientotExpirees(SymfonyStyle $io): int
    {
        $maintenant = new \DateTimeImmutable();
        $dansDeuxJours = $maintenant->modify('+2 days');
        $envoyes = 0;

        $licencies = array_filter(
            $this->licencieRepository->findAll(),
            static fn (Licencie $l) => null !== $l->getActivationToken()
                && $l->getActivationTokenExpiresAt()
                && $l->getActivationTokenExpiresAt() > $maintenant
                && $l->getActivationTokenExpiresAt() <= $dansDeuxJours
        );

        foreach ($licencies as $licencie) {
            if ($this->notificationMailer->invitationBientotExpiree($licencie)) {
                ++$envoyes;
            }
        }

        $io->writeln(sprintf('%d rappel(s) d\'invitation bientôt expirée envoyé(s).', $envoyes));

        return $envoyes;
    }

    private function evenementsAVenir(SymfonyStyle $io): int
    {
        $dansDeuxJours = (new \DateTimeImmutable('today'))->modify('+2 days');
        $envoyes = 0;

        foreach ($this->evenementRepository->findAll() as $evenement) {
            if (!$evenement->getDateDebut() || $evenement->getDateDebut()->format('Y-m-d') !== $dansDeuxJours->format('Y-m-d')) {
                continue;
            }

            foreach ($evenement->getInscriptions() as $inscription) {
                if (Inscription::STATUT_CONFIRMEE !== $inscription->getStatut()) {
                    continue;
                }
                if ($this->notificationMailer->evenementAVenir($inscription->getLicencie(), $evenement)) {
                    ++$envoyes;
                }
            }
        }

        $io->writeln(sprintf('%d rappel(s) d\'évènement à venir envoyé(s).', $envoyes));

        return $envoyes;
    }

    private function convocationsAVenir(SymfonyStyle $io): int
    {
        $dansTroisJours = (new \DateTimeImmutable('today'))->modify('+3 days');
        $envoyes = 0;

        foreach ($this->rencontreInterclubRepository->findAll() as $rencontre) {
            if (!$rencontre->getDateRencontre() || $rencontre->getDateRencontre()->format('Y-m-d') !== $dansTroisJours->format('Y-m-d')) {
                continue;
            }

            foreach ($rencontre->getConvocations() as $convocation) {
                if (null !== $convocation->isPresent()) {
                    continue;
                }
                if ($this->notificationMailer->convocationInterclub($convocation->getLicencie(), $rencontre)) {
                    ++$envoyes;
                }
            }
        }

        $io->writeln(sprintf('%d rappel(s) de convocation interclubs envoyé(s).', $envoyes));

        return $envoyes;
    }

    private function adhesionsImpayees(SymfonyStyle $io): int
    {
        $saisonEnCours = $this->saisonRepository->findEnCours();
        $envoyes = 0;

        if ($saisonEnCours) {
            foreach ($this->adhesionRepository->findBy(['saison' => $saisonEnCours]) as $adhesion) {
                if ($adhesion->isPayee() || Adhesion::STATUT_EXONEREE === $adhesion->getStatut()) {
                    continue;
                }
                if ($this->notificationMailer->adhesionImpayee($adhesion->getLicencie(), $adhesion)) {
                    ++$envoyes;
                }
            }
        }

        $io->writeln(sprintf('%d rappel(s) d\'adhésion impayée envoyé(s).', $envoyes));

        return $envoyes;
    }

    private static function nomJourFrancais(\DateTimeImmutable $date): string
    {
        return self::JOURS[((int) $date->format('N')) - 1];
    }
}
