<?php

namespace App\Entity;

use App\Repository\DiseaseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DiseaseRepository::class)]
#[ORM\Table(name: 'diseases')]
class Disease
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Short unique code, e.g. ANEMIA */
    #[ORM\Column(length: 64, unique: true)]
    private string $code = '';

    #[ORM\Column(length: 255)]
    private string $label = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Canonical nutrient key (same naming as trackers) → short dietary intake suggestion sentence.
     * Skip keys nutrients not relevant to this condition.
     *
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $intakeSuggestions = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

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

    /**
     * @return array<string, string>
     */
    public function getIntakeSuggestions(): array
    {
        return $this->intakeSuggestions;
    }

    /**
     * @param array<string, string> $intakeSuggestions
     */
    public function setIntakeSuggestions(array $intakeSuggestions): static
    {
        $this->intakeSuggestions = $intakeSuggestions;

        return $this;
    }
}
