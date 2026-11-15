<?php

namespace App\Service;

use App\Entity\Association;
use App\Entity\Lot;
use App\Entity\Reservation;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Règles métier autour du cycle de vie d'un lot :
 * publication, réservation, retrait, expiration.
 */
class LotManager
{
    public function __construct(
        private readonly WorkflowInterface $lotStateMachine,
        private readonly EntityManagerInterface $em,
        private readonly CodeRetraitGenerator $codeGenerator,
    ) {
    }

    public function publier(Lot $lot): void
    {
        if ($lot->estPerime()) {
            throw new \DomainException('Impossible de publier un lot dont la date limite est dépassée.');
        }

        $this->appliquer($lot, 'publier');
        $this->em->persist($lot);
        $this->em->flush();
    }

    public function reserver(Lot $lot, Association $association): Reservation
    {
        if (!$association->isValidated()) {
            throw new \DomainException('Votre association doit être validée par un administrateur avant de réserver.');
        }

        if ($lot->estPerime()) {
            throw new \DomainException('Ce lot a dépassé sa date limite.');
        }

        $this->appliquer($lot, 'reserver');

        $reservation = new Reservation($lot, $association, $this->codeGenerator->generer());
        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
    }

    public function confirmerRetrait(Reservation $reservation, string $codeSaisi): void
    {
        if (!$reservation->estActive()) {
            throw new \DomainException('Cette réservation n\'est plus active.');
        }

        if (!hash_equals($reservation->getCodeRetrait(), mb_strtoupper(trim($codeSaisi)))) {
            throw new \DomainException('Le code de retrait est incorrect.');
        }

        $this->appliquer($reservation->getLot(), 'confirmer_retrait');
        $reservation->marquerRecuperee();
        $this->em->flush();
    }

    /** L'association se désiste : le lot redevient disponible. */
    public function annulerReservation(Reservation $reservation): void
    {
        if (!$reservation->estActive()) {
            throw new \DomainException('Cette réservation n\'est plus active.');
        }

        $this->appliquer($reservation->getLot(), 'liberer');
        $reservation->annuler();
        $this->em->flush();
    }

    /**
     * Passe en "expiré" tous les lots publiés ou réservés dont la date limite est dépassée.
     *
     * @return int nombre de lots expirés
     */
    public function expirerLotsPerimes(?\DateTimeImmutable $maintenant = null): int
    {
        $aujourdhui = ($maintenant ?? new \DateTimeImmutable())->setTime(0, 0);

        /** @var Lot[] $lots */
        $lots = $this->em->createQuery(
            'SELECT l FROM '.Lot::class.' l WHERE l.statut IN (:statuts) AND l.dateLimite < :aujourdhui'
        )
            ->setParameter('statuts', [Lot::STATUT_PUBLIE, Lot::STATUT_RESERVE])
            ->setParameter('aujourdhui', $aujourdhui, Types::DATE_IMMUTABLE)
            ->getResult();

        foreach ($lots as $lot) {
            $reservations = $this->em->getRepository(Reservation::class)->findBy([
                'lot' => $lot,
                'statut' => Reservation::STATUT_ACTIVE,
            ]);
            foreach ($reservations as $reservation) {
                $reservation->annuler();
            }

            $this->appliquer($lot, 'expirer');
        }

        $this->em->flush();

        return count($lots);
    }

    private function appliquer(Lot $lot, string $transition): void
    {
        if (!$this->lotStateMachine->can($lot, $transition)) {
            throw new \DomainException(sprintf('Action « %s » impossible pour un lot au statut « %s ».', $transition, $lot->getStatut()));
        }

        $this->lotStateMachine->apply($lot, $transition);
    }
}
