<?php

namespace App\Service;

use App\Entity\Lot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Recherche des lots que les associations peuvent réserver :
 * publiés et dont la date limite n'est pas dépassée.
 */
class LotCatalogue
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return Lot[] du plus urgent au moins urgent
     */
    public function rechercher(?int $categorieId = null, ?string $ville = null, ?\DateTimeImmutable $maintenant = null): array
    {
        $qb = $this->disponibles($maintenant)
            ->select('l', 'c', 'co')
            ->orderBy('l.dateLimite', 'ASC')
            ->addOrderBy('l.id', 'ASC');

        if (null !== $categorieId) {
            $qb->andWhere('c.id = :categorie')->setParameter('categorie', $categorieId);
        }

        if (null !== $ville && '' !== $ville) {
            $qb->andWhere('co.ville = :ville')->setParameter('ville', $ville);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Villes où au moins un lot est disponible, pour alimenter le filtre.
     *
     * @return string[]
     */
    public function villes(?\DateTimeImmutable $maintenant = null): array
    {
        return $this->disponibles($maintenant)
            ->select('DISTINCT co.ville')
            ->orderBy('co.ville', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    private function disponibles(?\DateTimeImmutable $maintenant): QueryBuilder
    {
        $aujourdhui = ($maintenant ?? new \DateTimeImmutable())->setTime(0, 0);

        return $this->em->createQueryBuilder()
            ->from(Lot::class, 'l')
            ->join('l.categorie', 'c')
            ->join('l.commerce', 'co')
            ->where('l.statut = :statut')
            ->andWhere('l.dateLimite >= :aujourdhui')
            ->setParameter('statut', Lot::STATUT_PUBLIE)
            ->setParameter('aujourdhui', $aujourdhui, Types::DATE_IMMUTABLE);
    }
}
