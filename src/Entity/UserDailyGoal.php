<?php

namespace App\Entity;

use App\Repository\UserDailyGoalRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserDailyGoalRepository::class)]
#[ORM\Table(name: 'user_daily_goal')]
#[ORM\UniqueConstraint(name: 'uniq_user_goal', columns: ['user_id', 'goal_key'])]
class UserDailyGoal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'dailyGoals')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'goal_key', length: 100)]
    private ?string $goalKey = null;

    #[ORM\Column]
    private ?float $value = null;

    #[ORM\Column(length: 20)]
    private ?string $unit = null;

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

    public function getGoalKey(): ?string
    {
        return $this->goalKey;
    }

    public function setGoalKey(string $goalKey): static
    {
        $this->goalKey = $goalKey;

        return $this;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(float $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }
}
