<?php

namespace App\Repository;

use App\Entity\Creneau;
use App\Entity\Licencie;
use App\Entity\Presence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Presence>
 */
class PresenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Presence::class);
    }

    public function findOneByCreneauLicencieEtDate(Creneau $creneau, Licencie $licencie, \DateTimeImmutable $date): ?Presence
    {
        return $this->findOneBy(['creneau' => $creneau, 'licencie' => $licencie, 'date' => $date]);
    }

    /**
     * @return Presence[]
     */
    public function findPourCreneauEtDate(Creneau $creneau, \DateTimeImmutable $date): array
    {
        return $this->findBy(['creneau' => $creneau, 'date' => $date]);
    }

    /**
     * @return Presence[]
     */
    public function findPourLicencieEntre(Licencie $licencie, \DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.licencie = :licencie')
            ->andWhere('p.date BETWEEN :debut AND :fin')
            ->setParameter('licencie', $licencie)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getResult();
    }

    public function compterConfirmees(Creneau $creneau, \DateTimeImmutable $date, ?Licencie $exclure = null): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.creneau = :creneau')
            ->andWhere('p.date = :date')
            ->andWhere('p.present = true')
            ->andWhere('p.statutInscription = :statut')
            ->setParameter('creneau', $creneau)
            ->setParameter('date', $date)
            ->setParameter('statut', Presence::STATUT_CONFIRMEE);

        if ($exclure) {
            $qb->andWhere('p.licencie != :exclure')->setParameter('exclure', $exclure);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findPremierEnListeAttente(Creneau $creneau, \DateTimeImmutable $date): ?Presence
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.creneau = :creneau')
            ->andWhere('p.date = :date')
            ->andWhere('p.present = true')
            ->andWhere('p.statutInscription = :statut')
            ->setParameter('creneau', $creneau)
            ->setParameter('date', $date)
            ->setParameter('statut', Presence::STATUT_LISTE_ATTENTE)
            ->orderBy('p.repondule', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Presence[]
     */
    public function findPromotionsExpirees(\DateTimeImmutable $maintenant): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.statutInscription = :statut')
            ->andWhere('p.promotionExpiresAt < :maintenant')
            ->setParameter('statut', Presence::STATUT_EN_ATTENTE_CONFIRMATION)
            ->setParameter('maintenant', $maintenant)
            ->getQuery()
            ->getResult();
    }
}
