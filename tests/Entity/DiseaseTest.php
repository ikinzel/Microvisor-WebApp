<?php

namespace App\Tests\Entity;

use App\Entity\Disease;
use PHPUnit\Framework\TestCase;

class DiseaseTest extends TestCase
{
    private Disease $disease;

    protected function setUp(): void
    {
        $this->disease = new Disease();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->disease->getId());
        $this->assertSame('', $this->disease->getCode());
        $this->assertSame('', $this->disease->getLabel());
        $this->assertNull($this->disease->getDescription());
        $this->assertSame([], $this->disease->getIntakeSuggestions());
    }

    public function testSetAndGetCode(): void
    {
        $result = $this->disease->setCode('ANEMIA');

        $this->assertSame('ANEMIA', $this->disease->getCode());
        $this->assertSame($this->disease, $result);
    }

    public function testSetAndGetLabel(): void
    {
        $result = $this->disease->setLabel('Anemia');

        $this->assertSame('Anemia', $this->disease->getLabel());
        $this->assertSame($this->disease, $result);
    }

    public function testSetAndGetDescription(): void
    {
        $result = $this->disease->setDescription('Low red blood cell count.');

        $this->assertSame('Low red blood cell count.', $this->disease->getDescription());
        $this->assertSame($this->disease, $result);
    }

    public function testSetDescriptionToNull(): void
    {
        $this->disease->setDescription('Some description');

        $this->disease->setDescription(null);

        $this->assertNull($this->disease->getDescription());
    }

    public function testSetAndGetIntakeSuggestions(): void
    {
        $suggestions = ['iron' => 'Increase iron-rich foods.', 'vitaminC' => 'Pair with vitamin C.'];

        $result = $this->disease->setIntakeSuggestions($suggestions);

        $this->assertSame($suggestions, $this->disease->getIntakeSuggestions());
        $this->assertSame($this->disease, $result);
    }

    public function testSetIntakeSuggestionsToEmptyArray(): void
    {
        $this->disease->setIntakeSuggestions(['iron' => 'Eat spinach.']);

        $this->disease->setIntakeSuggestions([]);

        $this->assertSame([], $this->disease->getIntakeSuggestions());
    }
}
