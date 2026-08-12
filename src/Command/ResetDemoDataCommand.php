<?php

namespace App\Command;

use App\Demo\DemoAccounts;
use App\Entity\Adhesion;
use App\Entity\CleGymnase;
use App\Entity\Creneau;
use App\Entity\Equipe;
use App\Entity\Evenement;
use App\Entity\Gymnase;
use App\Entity\Inscription;
use App\Entity\Licencie;
use App\Entity\MatchInterclub;
use App\Entity\PaiementAdhesion;
use App\Entity\Presence;
use App\Entity\RencontreInterclub;
use App\Entity\Saison;
use App\Entity\StockCordage;
use App\Entity\StockVetement;
use App\Entity\StockVolant;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Réinitialise entièrement les données de l'instance de démonstration (demo.axiobad.click) :
 * efface tout ce qui a pu être modifié par des visiteurs, puis recrée un jeu de données
 * fictives (licenciés, créneaux, présences, adhésions, équipe/interclubs, stock) ainsi que les
 * comptes de démo listés dans DemoAccounts (un par rôle).
 *
 * Destructif par nature — refuse de s'exécuter tant que DEMO_MODE n'est pas activé, pour
 * qu'une exécution accidentelle ne puisse jamais vider la vraie base de production.
 */
#[AsCommand(name: 'app:demo:reset', description: 'Réinitialise les données de démonstration (instance demo.axiobad.click uniquement).')]
class ResetDemoDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly bool $demoMode,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->demoMode) {
            $io->error('DEMO_MODE n\'est pas activé sur cette instance : commande refusée par sécurité.');

            return Command::FAILURE;
        }

        $io->section('Suppression des données existantes');
        $this->viderLesTables($io);

        $io->section('Recréation du jeu de données de démonstration');
        $this->creerJeuDeDonnees($io);

        $io->success('Instance de démonstration réinitialisée.');

        return Command::SUCCESS;
    }

    private function viderLesTables(SymfonyStyle $io): void
    {
        // Ordre pensé pour respecter les contraintes de clé étrangère (enfants avant parents).
        $tables = [
            'match_interclub', 'rencontre_interclub', 'equipe_membre', 'equipe',
            'paiement_adhesion', 'adhesion',
            'presence', 'creneau_exception', 'creneau_ouverture', 'creneau',
            'stock_mouvement_vetement', 'stock_vetement',
            'stock_mouvement_volant', 'stock_volant',
            'stock_mouvement_cordage', 'stock_cordage',
            'inventaire_ligne', 'inventaire_campagne',
            'inscription', 'evenement',
            'cle_gymnase', 'gymnase',
            'licencie', 'saison',
        ];
        foreach ($tables as $table) {
            $this->connection->executeStatement('DELETE FROM '.$table);
            $io->writeln('  · '.$table.' vidée');
        }
    }

    private function creerJeuDeDonnees(SymfonyStyle $io): void
    {
        $em = $this->entityManager;

        $saison = (new Saison())
            ->setLibelle('2025-2026')
            ->setDateDebut(new \DateTimeImmutable('2025-09-01'))
            ->setDateFin(new \DateTimeImmutable('2026-08-31'));
        $em->persist($saison);

        $gymnase = (new Gymnase())
            ->setNom('Gymnase Central')
            ->setAdresse('12 avenue des Sports, 76600 Le Havre')
            ->setTelephone('02 35 00 00 00')
            ->setNombreTerrains(6)
            ->setActif(true);
        $em->persist($gymnase);

        $gymnaseAcacias = (new Gymnase())
            ->setNom('Gymnase des Acacias')
            ->setAdresse('4 rue des Acacias, 76600 Le Havre')
            ->setTelephone('02 35 00 00 01')
            ->setNombreTerrains(4)
            ->setActif(true);
        $em->persist($gymnaseAcacias);

        // --- Comptes de démo (un par rôle) ---
        $comptesDemo = [];
        foreach (DemoAccounts::all() as $compte) {
            $l = (new Licencie())
                ->setPrenom($compte['prenom'])
                ->setNom($compte['nom'])
                ->setEmail($compte['email'])
                ->setGenre(Licencie::GENRE_HOMME)
                ->setRoles($compte['roles'])
                ->setMustChangePassword(false);
            $l->setPassword($this->passwordHasher->hashPassword($l, DemoAccounts::PASSWORD));
            $em->persist($l);
            $comptesDemo[] = $l;
        }

        // --- Licenciés fictifs supplémentaires (données réalistes mais anonymes) ---
        $prenomsH = ['Lucas', 'Hugo', 'Mathis', 'Nathan', 'Théo', 'Enzo', 'Louis', 'Adam'];
        $prenomsF = ['Emma', 'Léa', 'Chloé', 'Manon', 'Camille', 'Julie', 'Sarah', 'Inès'];
        $noms = ['Marchand', 'Lefebvre', 'Girard', 'Rousseau', 'Vidal', 'Fontaine', 'Chevalier', 'Gauthier', 'Perrin', 'Morel', 'Dubois', 'Lambert'];
        $classements = ['NC', 'P12', 'P11', 'P10', 'D9', 'D8', 'D7', 'R6'];

        $licencies = [];
        $idx = 0;
        foreach (array_merge($prenomsH, $prenomsF) as $prenom) {
            $nom = $noms[$idx % count($noms)];
            $genre = in_array($prenom, $prenomsH, true) ? Licencie::GENRE_HOMME : Licencie::GENRE_FEMME;
            $email = strtolower($prenom).'.'.strtolower($nom).$idx.'@exemple-demo.fr';

            $l = (new Licencie())
                ->setPrenom($prenom)
                ->setNom($nom)
                ->setEmail($email)
                ->setTelephone('06 00 00 '.str_pad((string) (10 + $idx), 2, '0', STR_PAD_LEFT))
                ->setGenre($genre)
                ->setDateNaissance(new \DateTimeImmutable(sprintf('%d-0%d-1%d', 1985 + $idx, ($idx % 9) + 1, $idx % 9)))
                ->setNumeroLicence('DEMO'.str_pad((string) (1000 + $idx), 6, '0', STR_PAD_LEFT))
                ->setClassementSimple($classements[$idx % count($classements)])
                ->setClassementDouble($classements[($idx + 1) % count($classements)])
                ->setClassementMixte($classements[($idx + 2) % count($classements)])
                ->setMustChangePassword(false);
            $l->setPassword($this->passwordHasher->hashPassword($l, DemoAccounts::PASSWORD));
            $em->persist($l);
            $licencies[] = $l;
            ++$idx;
        }
        $em->flush();

        // --- Créneaux ---
        $creneauLoisir = (new Creneau())
            ->setNom('Loisir adultes')->setActivite('Badminton')->setGymnase($gymnase)
            ->setJourSemaine('Mardi')
            ->setHeureDebut(new \DateTimeImmutable('19:00'))->setHeureFin(new \DateTimeImmutable('21:00'))
            ->setCategorie('ADULTE')->setEncadre(false)->setLoisir(true)->setCompetiteur(false)->setActif(true);
        $em->persist($creneauLoisir);

        $creneauCompet = (new Creneau())
            ->setNom('Entraînement compétiteurs')->setActivite('Badminton')->setGymnase($gymnase)
            ->setJourSemaine('Jeudi')
            ->setHeureDebut(new \DateTimeImmutable('20:00'))->setHeureFin(new \DateTimeImmutable('22:00'))
            ->setCategorie('ADULTE')->setEncadre(true)->setEntraineur($comptesDemo[1])
            ->setLoisir(false)->setCompetiteur(true)->setClassementMinimum('D9')->setCapaciteMax(12)->setActif(true);
        $em->persist($creneauCompet);

        $creneauJeunes = (new Creneau())
            ->setNom('École de badminton')->setActivite('Badminton')->setGymnase($gymnase)
            ->setJourSemaine('Mercredi')
            ->setHeureDebut(new \DateTimeImmutable('14:00'))->setHeureFin(new \DateTimeImmutable('15:30'))
            ->setCategorie('ENFANT')->setEncadre(true)->setEntraineur($comptesDemo[1])
            ->setLoisir(true)->setCompetiteur(false)->setCapaciteMax(10)->setActif(true);
        $em->persist($creneauJeunes);
        $em->flush();

        // --- Présences (créneau loisir, 10 dernières semaines) ---
        $aujourdhui = new \DateTimeImmutable('today');
        foreach ([$licencies[0], $licencies[1], $licencies[2], $licencies[3]] as $li) {
            for ($semaine = 1; $semaine <= 10; ++$semaine) {
                $date = $aujourdhui->modify('-'.($semaine * 7).' days');
                $present = (mt_rand(1, 10) <= 8);
                $p = (new Presence())->setCreneau($creneauLoisir)->setLicencie($li)->setDate($date)->setPresent($present);
                $em->persist($p);
            }
        }
        $em->flush();

        // --- Adhésions (statuts variés) ---
        $statuts = [Adhesion::STATUT_PAYEE, Adhesion::STATUT_PAYEE, Adhesion::STATUT_PAYEE, Adhesion::STATUT_EN_ATTENTE, Adhesion::STATUT_EN_ATTENTE, Adhesion::STATUT_EXONEREE];
        foreach ($licencies as $i => $li) {
            $statut = $statuts[$i % count($statuts)];
            $montant = 120.0;
            $adhesion = (new Adhesion())->setLicencie($li)->setSaison($saison)->setStatut($statut)->setMontantTotal($montant);
            $em->persist($adhesion);
            $em->flush();

            if (Adhesion::STATUT_PAYEE === $statut) {
                $paiement = (new PaiementAdhesion())->setAdhesion($adhesion)
                    ->setDate(new \DateTimeImmutable('-'.(20 + $i).' days'))
                    ->setMontant($montant)->setMoyen(PaiementAdhesion::MOYEN_VIREMENT);
                $em->persist($paiement);
            } elseif (Adhesion::STATUT_EN_ATTENTE === $statut) {
                $paiement = (new PaiementAdhesion())->setAdhesion($adhesion)
                    ->setDate(new \DateTimeImmutable('-'.(10 + $i).' days'))
                    ->setMontant(50.0)->setMoyen(PaiementAdhesion::MOYEN_CHEQUE)->setNumeroCheque('000'.(100 + $i));
                $em->persist($paiement);
            }
        }
        $em->flush();

        // --- Équipe + rencontre interclub ---
        $equipe = (new Equipe())->setNom('Régional 2')->setDivision('R2')->setCapitaine($licencies[0])->setActif(true);
        foreach (array_slice($licencies, 0, 6) as $membre) {
            $equipe->addMembre($membre);
        }
        $em->persist($equipe);
        $em->flush();

        $rencontre = (new RencontreInterclub())
            ->setEquipe($equipe)->setJournee(4)
            ->setDateRencontre(new \DateTimeImmutable('-14 days 19:00'))
            ->setGymnase($gymnase)->setAdversaire('Club Voisin Badminton')->setDomicile(true)
            ->setHeureRdv(new \DateTimeImmutable('18:30'))->setCapitaineRencontre($licencies[0])
            ->setScoreEquipe(5)->setScoreAdversaire(3);
        $em->persist($rencontre);
        $em->flush();

        $matchs = [
            ['type' => MatchInterclub::TYPE_SH, 'j1' => 0, 'j2' => null, 'adv' => 'M. Petit', 'score' => '21-15 / 21-18', 'gagne' => true],
            ['type' => MatchInterclub::TYPE_SH, 'j1' => 1, 'j2' => null, 'adv' => 'M. Roy', 'score' => '18-21 / 19-21', 'gagne' => false],
            ['type' => MatchInterclub::TYPE_SD, 'j1' => 8, 'j2' => null, 'adv' => 'Mme Blanc', 'score' => '21-12 / 21-10', 'gagne' => true],
            ['type' => MatchInterclub::TYPE_DH, 'j1' => 2, 'j2' => 3, 'adv' => 'MM. Robert / Simon', 'score' => '21-19 / 15-21 / 21-17', 'gagne' => true],
            ['type' => MatchInterclub::TYPE_DD, 'j1' => 9, 'j2' => 10, 'adv' => 'Mmes Garcia / Martin', 'score' => '21-14 / 21-16', 'gagne' => true],
            ['type' => MatchInterclub::TYPE_MX, 'j1' => 4, 'j2' => 9, 'adv' => 'M. Bernard / Mme Petit', 'score' => '19-21 / 21-18 / 21-19', 'gagne' => true],
        ];
        foreach ($matchs as $m) {
            $match = (new MatchInterclub())
                ->setRencontre($rencontre)->setType($m['type'])->setJoueur1($licencies[$m['j1']])
                ->setJoueur2(null !== $m['j2'] ? $licencies[$m['j2']] : null)
                ->setAdversaires($m['adv'])->setScore($m['score'])->setGagne($m['gagne']);
            $em->persist($match);
        }
        $em->flush();

        // --- Porteurs de clés ---
        $cle1 = (new CleGymnase())->setGymnase($gymnase)->setLicencie($comptesDemo[0])->setReference('Trousseau bureau — badge A12');
        $em->persist($cle1);
        $cle2 = (new CleGymnase())->setGymnase($gymnase)->setLicencie($licencies[0])->setReference('Double n°2');
        $em->persist($cle2);
        $cle3 = (new CleGymnase())->setGymnase($gymnaseAcacias)->setLicencie($comptesDemo[1])->setReference('Badge entraîneur');
        $em->persist($cle3);
        $em->flush();

        // --- Vie du club / évènements (avec inscriptions et liste d'attente) ---
        $tournoi = (new Evenement())
            ->setType(Evenement::TYPE_TOURNOI_INTERNE)
            ->setTitre('Tournoi interne de printemps')
            ->setDescription("Tournoi amical ouvert à tous les niveaux, simples et doubles. Inscription gratuite, lots pour les finalistes.")
            ->setLieu('Gymnase Central')
            ->setDateDebut(new \DateTimeImmutable('+21 days 09:00'))
            ->setDateFin(new \DateTimeImmutable('+21 days 18:00'))
            ->setNombrePlaces(16);
        $em->persist($tournoi);
        $em->flush();

        // 16 places : 14 confirmées + 3 en liste d'attente pour illustrer le mécanisme.
        foreach (array_slice($licencies, 0, 14) as $li) {
            $em->persist((new Inscription())->setEvenement($tournoi)->setLicencie($li)->setStatut(Inscription::STATUT_CONFIRMEE));
        }
        foreach (array_slice($licencies, 14, 3) as $li) {
            $em->persist((new Inscription())->setEvenement($tournoi)->setLicencie($li)->setStatut(Inscription::STATUT_LISTE_ATTENTE));
        }

        $barbecue = (new Evenement())
            ->setType(Evenement::TYPE_BARBECUE)
            ->setTitre('Barbecue de fin de saison')
            ->setDescription('Repas convivial pour clôturer la saison, ouvert aux licenciés et à leurs familles.')
            ->setLieu('Gymnase Central, parvis extérieur')
            ->setDateDebut(new \DateTimeImmutable('+45 days 12:00'))
            ->setNombrePlaces(40);
        $em->persist($barbecue);
        $em->flush();
        foreach (array_slice($licencies, 0, 9) as $li) {
            $em->persist((new Inscription())->setEvenement($barbecue)->setLicencie($li)->setStatut(Inscription::STATUT_CONFIRMEE));
        }

        $ag = (new Evenement())
            ->setType(Evenement::TYPE_ASSEMBLEE_GENERALE)
            ->setTitre('Assemblée générale annuelle')
            ->setDescription("Bilan de la saison, votes du bureau, présentation du budget.")
            ->setLieu('Gymnase Central, salle de réunion')
            ->setDateDebut(new \DateTimeImmutable('+60 days 19:00'));
        $em->persist($ag);
        $em->flush();

        // --- Stock ---
        $vetement = (new StockVetement())
            ->setType(StockVetement::TYPE_TSHIRT)->setTaille('M')->setMarque('Decathlon / Kipsta')
            ->setQuantite(24)->setPrixUnitaire(9.5)->setLieuStockage('Local matériel, armoire 1');
        $em->persist($vetement);

        $volant = (new StockVolant())
            ->setType(StockVolant::TYPE_PLUME)->setVitesse('77')->setDestination(StockVolant::DESTINATION_LOISIR)
            ->setMarque('Yonex')->setModele('Aerosensa 50')->setQuantiteTubes(15)->setPrixUnitaire(18.0)
            ->setLieuStockage('Local matériel, étagère volants');
        $em->persist($volant);

        $cordages = [
            ['type' => StockCordage::TYPE_BOBINE, 'marque' => 'Yonex', 'modele' => 'BG65', 'qte' => 3, 'prix' => 32.0, 'seuil' => 1],
            ['type' => StockCordage::TYPE_BOBINE, 'marque' => 'Yonex', 'modele' => 'Nanogy 95', 'qte' => 1, 'prix' => 38.0, 'seuil' => 1],
            ['type' => StockCordage::TYPE_SACHET, 'marque' => 'Victor', 'modele' => 'VS-850', 'qte' => 12, 'prix' => 6.5, 'seuil' => 5],
        ];
        foreach ($cordages as $c) {
            $stockCordage = (new StockCordage())
                ->setType($c['type'])->setMarque($c['marque'])->setModele($c['modele'])
                ->setQuantite($c['qte'])->setPrixUnitaire($c['prix'])->setSeuilAlerte($c['seuil'])
                ->setLieuStockage('Local matériel, armoire cordage');
            $em->persist($stockCordage);
        }
        $em->flush();

        $io->writeln(sprintf('  · %d licenciés (dont %d comptes de démo)', count($licencies) + count($comptesDemo), count($comptesDemo)));
    }
}
