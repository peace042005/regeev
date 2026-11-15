<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Reservation
{
    public const STATUT_ACTIVE = 'active';
    public const STATUT_RECUPEREE = 'recuperee';
    public const STATUT_ANNULEE = 'annulee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Lot $lot;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Association $association;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateReservation;

    /** Code à présenter au commerce au moment du retrait. */
    #[ORM\Column(length: 6)]
    private string $codeRetrait;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_ACTIVE;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateRetrait = null;

    public function __construct(Lot $lot, Association $association, string $codeRetrait, ?\DateTimeImmutable $dateReservation = null)
    {
        $this->lot = $lot;
        $this->association = $association;
        $this->codeRetrait = $codeRetrait;
        $this->dateReservation = $dateReservation ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLot(): Lot
    {
        return $this->lot;
    }

    public function getAssociation(): Association
    {
        return $this->association;
    }

    public function getDateReservation(): \DateTimeImmutable
    {
        return $this->dateReservation;
    }

    public function getCodeRetrait(): string
    {
        return $this->codeRetrait;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function getDateRetrait(): ?\DateTimeImmutable
    {
        return $this->dateRetrait;
    }

    public function estActive(): bool
    {
        return self::STATUT_ACTIVE === $this->statut;
    }

    public function marquerRecuperee(?\DateTimeImmutable $date = null): void
    {
        $this->statut = self::STATUT_RECUPEREE;
        $this->dateRetrait = $date ?? new \DateTimeImmutable();
    }

    public function annuler(): void
    {
        $this->statut = self::STATUT_ANNULEE;
    }
}
