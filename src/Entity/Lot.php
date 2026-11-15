<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un lot d'invendus publié par un commerce.
 * Son statut est piloté par le composant Workflow (voir config/packages/framework.yaml).
 */
#[ORM\Entity]
#[ORM\Index(columns: ['statut'], name: 'idx_lot_statut')]
class Lot
{
    public const STATUT_BROUILLON = 'brouillon';
    public const STATUT_PUBLIE = 'publie';
    public const STATUT_RESERVE = 'reserve';
    public const STATUT_RECUPERE = 'recupere';
    public const STATUT_EXPIRE = 'expire';
    public const STATUT_ANNULE = 'annule';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commerce $commerce = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Choisissez une catégorie.')]
    private ?Categorie $categorie = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    private string $titre = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\Positive(message: 'La quantité doit être supérieure à zéro.')]
    private float $quantite = 1;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    private string $unite = 'kg';

    /** Poids estimé du lot en kg : sert aux statistiques d'impact. */
    #[ORM\Column]
    #[Assert\Positive(message: 'Le poids estimé doit être supérieur à zéro.')]
    private float $poidsKg = 1;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date limite est obligatoire.')]
    #[Assert\GreaterThanOrEqual('today', message: 'La date limite ne peut pas être dans le passé.')]
    private ?\DateTimeImmutable $dateLimite = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $retraitDebut = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    #[Assert\GreaterThan(propertyPath: 'retraitDebut', message: 'La fin du créneau doit être après le début.')]
    private ?\DateTimeImmutable $retraitFin = null;

    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_BROUILLON;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommerce(): ?Commerce
    {
        return $this->commerce;
    }

    public function setCommerce(Commerce $commerce): static
    {
        $this->commerce = $commerce;

        return $this;
    }

    public function getCategorie(): ?Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(?Categorie $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getQuantite(): float
    {
        return $this->quantite;
    }

    public function setQuantite(float $quantite): static
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getUnite(): string
    {
        return $this->unite;
    }

    public function setUnite(string $unite): static
    {
        $this->unite = $unite;

        return $this;
    }

    public function getPoidsKg(): float
    {
        return $this->poidsKg;
    }

    public function setPoidsKg(float $poidsKg): static
    {
        $this->poidsKg = $poidsKg;

        return $this;
    }

    public function getDateLimite(): ?\DateTimeImmutable
    {
        return $this->dateLimite;
    }

    public function setDateLimite(?\DateTimeImmutable $dateLimite): static
    {
        $this->dateLimite = $dateLimite;

        return $this;
    }

    public function getRetraitDebut(): ?\DateTimeImmutable
    {
        return $this->retraitDebut;
    }

    public function setRetraitDebut(?\DateTimeImmutable $retraitDebut): static
    {
        $this->retraitDebut = $retraitDebut;

        return $this;
    }

    public function getRetraitFin(): ?\DateTimeImmutable
    {
        return $this->retraitFin;
    }

    public function setRetraitFin(?\DateTimeImmutable $retraitFin): static
    {
        $this->retraitFin = $retraitFin;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    /**
     * Appelé par le Workflow (marking store "method").
     *
     * @param array<string, mixed> $context
     */
    public function setStatut(string $statut, array $context = []): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /** Un lot est périmé dès le lendemain de sa date limite. */
    public function estPerime(?\DateTimeImmutable $maintenant = null): bool
    {
        if (null === $this->dateLimite) {
            return false;
        }

        $aujourdhui = ($maintenant ?? new \DateTimeImmutable())->setTime(0, 0);

        return $this->dateLimite->setTime(0, 0) < $aujourdhui;
    }
}
