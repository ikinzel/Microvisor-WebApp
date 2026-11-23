<?php

namespace App\Entity;

use App\Enum\FoodIntakeUnit;
use App\Repository\FoodIntakeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FoodIntakeRepository::class)]
#[ORM\Table(name: 'food_intake')]
class FoodIntake
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** FDC (FoodData Central) food id from the USDA API. */
    #[ORM\Column]
    private ?int $srcId = null;

    /** Display name from FDC (search or food detail); optional for legacy rows until backfilled. */
    #[ORM\Column(length: 512, nullable: true)]
    private ?string $srcName = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $amount = null;

    #[ORM\Column(enumType: FoodIntakeUnit::class)]
    private ?FoodIntakeUnit $unit = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getSrcId(): ?int
    {
        return $this->srcId;
    }

    public function setSrcId(int $srcId): static
    {
        $this->srcId = $srcId;

        return $this;
    }

    public function getSrcName(): ?string
    {
        return $this->srcName;
    }

    public function setSrcName(?string $srcName): static
    {
        $this->srcName = $srcName;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getUnit(): ?FoodIntakeUnit
    {
        return $this->unit;
    }

    public function setUnit(FoodIntakeUnit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    /** Backed enum value for templates (Twig cannot stringify enum objects). */
    public function getUnitValue(): string
    {
        return $this->unit?->value ?? '';
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
