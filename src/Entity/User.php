<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User
{
    public function __construct()
    {
        $this->dailyGoals = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column]
    private ?int $age = null;

    #[ORM\Column]
    private ?float $weight = null;

    #[ORM\Column]
    private ?float $height = null;

    /**stored weight is always kg. */
    #[ORM\Column(length: 10, options: ['default' => 'kg'])]
    private string $weightUnit = 'kg';

    /** stored height is always cm. */
    #[ORM\Column(length: 10, options: ['default' => 'cm'])]
    private string $heightUnit = 'cm';

    #[ORM\Column(length: 255)]
    private ?string $gender = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $lastLogin = null;

    #[ORM\ManyToOne(targetEntity: Disease::class)]
    #[ORM\JoinColumn(name: 'disease_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Disease $disease = null;

    /**
     * @var Collection<int, UserDailyGoal>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserDailyGoal::class, orphanRemoval: true)]
    private Collection $dailyGoals;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(int $age): static
    {
        $this->age = $age;

        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(float $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    public function getHeight(): ?float
    {
        return $this->height;
    }

    public function setHeight(float $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getWeightUnit(): string
    {
        return $this->weightUnit;
    }

    public function setWeightUnit(string $weightUnit): static
    {
        $this->weightUnit = $weightUnit;

        return $this;
    }

    public function getHeightUnit(): string
    {
        return $this->heightUnit;
    }

    public function setHeightUnit(string $heightUnit): static
    {
        $this->heightUnit = $heightUnit;

        return $this;
    }

    /** Weight formatted for the user's preferred unit (kg or lbs). */
    public function getWeightDisplayValue(): float
    {
        if ($this->weight === null) {
            return 0.0;
        }

        return $this->weightUnit === 'lbs'
            ? round($this->weight / 0.453592, 2)
            : round((float) $this->weight, 2);
    }

    /** Whole feet derived from stored cm (for imperial UI). */
    public function getHeightFeetPart(): int
    {
        if ($this->height === null || $this->height <= 0) {
            return 0;
        }

        $totalInches = $this->height / 2.54;

        return (int) floor($totalInches / 12);
    }

    /** Inches remainder for imperial UI. */
    public function getHeightInchesPart(): float
    {
        if ($this->height === null || $this->height <= 0) {
            return 0.0;
        }

        $totalInches = $this->height / 2.54;
        $feet = (int) floor($totalInches / 12);

        return round($totalInches - ($feet * 12), 1);
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(string $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function getLastLogin(): ?\DateTime
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTime $lastLogin): static
    {
        $this->lastLogin = $lastLogin;

        return $this;
    }

    public function getDisease(): ?Disease
    {
        return $this->disease;
    }

    public function setDisease(?Disease $disease): static
    {
        $this->disease = $disease;

        return $this;
    }

    /**
     * @return Collection<int, UserDailyGoal>
     */
    public function getDailyGoals(): Collection
    {
        return $this->dailyGoals;
    }

    public function addDailyGoal(UserDailyGoal $dailyGoal): static
    {
        if (!$this->dailyGoals->contains($dailyGoal)) {
            $this->dailyGoals->add($dailyGoal);
            $dailyGoal->setUser($this);
        }

        return $this;
    }

    public function removeDailyGoal(UserDailyGoal $dailyGoal): static
    {
        if ($this->dailyGoals->removeElement($dailyGoal) && $dailyGoal->getUser() === $this) {
            $dailyGoal->setUser(null);
        }

        return $this;
    }
}
