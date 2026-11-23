<?php

namespace App\Tests\Entity;

use App\Entity\Disease;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    public function testDefaultValuesAreNull(): void
    {
        $this->assertNull($this->user->getId());
        $this->assertNull($this->user->getFirstName());
        $this->assertNull($this->user->getLastName());
        $this->assertNull($this->user->getEmail());
        $this->assertNull($this->user->getPassword());
        $this->assertNull($this->user->getAge());
        $this->assertNull($this->user->getWeight());
        $this->assertNull($this->user->getHeight());
        $this->assertNull($this->user->getGender());
        $this->assertNull($this->user->getLastLogin());
        $this->assertNull($this->user->getDisease());
    }

    public function testDefaultWeightUnit(): void
    {
        $this->assertSame('kg', $this->user->getWeightUnit());
    }

    public function testDefaultHeightUnit(): void
    {
        $this->assertSame('cm', $this->user->getHeightUnit());
    }

    public function testSetAndGetFirstName(): void
    {
        $result = $this->user->setFirstName('Jane');

        $this->assertSame('Jane', $this->user->getFirstName());
        $this->assertSame($this->user, $result);
    }

    public function testSetAndGetLastName(): void
    {
        $result = $this->user->setLastName('Doe');

        $this->assertSame('Doe', $this->user->getLastName());
        $this->assertSame($this->user, $result);
    }

    public function testSetAndGetEmail(): void
    {
        $result = $this->user->setEmail('jane@example.com');

        $this->assertSame('jane@example.com', $this->user->getEmail());
        $this->assertSame($this->user, $result);
    }

    public function testSetAndGetPassword(): void
    {
        $result = $this->user->setPassword('secret123');

        $this->assertSame('secret123', $this->user->getPassword());
        $this->assertSame($this->user, $result);
    }

    public function testSetAndGetAge(): void
    {
        $result = $this->user->setAge(30);

        $this->assertSame(30, $this->user->getAge());
        $this->assertSame($this->user, $result);
    }

    public function testSetAndGetWeight(): void
    {
        $result = $this->user->setWeight(70.5);

        $this->assertSame(70.5, $this->user->getWeight());
        $this->assertSame($this->user, $result);
    }

    public function testSetAndGetHeight(): void
    {
        $result = $this->user->setHeight(175.0);

        $this->assertSame(175.0, $this->user->getHeight());
        $this->assertSame($this->user, $result);
    }

    public function testSetAndGetWeightUnit(): void
    {
        $result = $this->user->setWeightUnit('lbs');

        $this->assertSame('lbs', $this->user->getWeightUnit());
        $this->assertSame($this->user, $result);
    }

    public function testSetAndGetHeightUnit(): void
    {
        $result = $this->user->setHeightUnit('ft');

        $this->assertSame('ft', $this->user->getHeightUnit());
        $this->assertSame($this->user, $result);
    }

    public function testSetAndGetGender(): void
    {
        $result = $this->user->setGender('female');

        $this->assertSame('female', $this->user->getGender());
        $this->assertSame($this->user, $result);
    }

    public function testSetAndGetLastLogin(): void
    {
        $date = new \DateTime('2025-01-15 10:00:00');

        $result = $this->user->setLastLogin($date);

        $this->assertSame($date, $this->user->getLastLogin());
        $this->assertSame($this->user, $result);
    }

    public function testSetLastLoginToNull(): void
    {
        $this->user->setLastLogin(new \DateTime());

        $this->user->setLastLogin(null);

        $this->assertNull($this->user->getLastLogin());
    }

    public function testSetAndGetDisease(): void
    {
        $disease = new Disease();

        $result = $this->user->setDisease($disease);

        $this->assertSame($disease, $this->user->getDisease());
        $this->assertSame($this->user, $result);
    }

    public function testSetDiseaseToNull(): void
    {
        $this->user->setDisease(new Disease());

        $this->user->setDisease(null);

        $this->assertNull($this->user->getDisease());
    }

    public function testGetWeightDisplayValueInKg(): void
    {
        $this->user->setWeight(70.0)->setWeightUnit('kg');

        $this->assertSame(70.0, $this->user->getWeightDisplayValue());
    }

    public function testGetWeightDisplayValueInLbs(): void
    {
        $this->user->setWeight(70.0)->setWeightUnit('lbs');

        $this->assertEqualsWithDelta(154.32, $this->user->getWeightDisplayValue(), 0.05);
    }

    public function testGetWeightDisplayValueWhenWeightIsNull(): void
    {
        $this->assertSame(0.0, $this->user->getWeightDisplayValue());
    }

    public function testGetHeightFeetPartFor175cm(): void
    {
        // 175 cm → ~68.9 in → 5 ft
        $this->user->setHeight(175.0);

        $this->assertSame(5, $this->user->getHeightFeetPart());
    }

    public function testGetHeightFeetPartWhenHeightIsNull(): void
    {
        $this->assertSame(0, $this->user->getHeightFeetPart());
    }

    public function testGetHeightFeetPartWhenHeightIsZero(): void
    {
        $this->user->setHeight(0.0);

        $this->assertSame(0, $this->user->getHeightFeetPart());
    }

    public function testGetHeightInchesPartFor175cm(): void
    {
        $this->user->setHeight(175.0);

        $this->assertEqualsWithDelta(8.9, $this->user->getHeightInchesPart(), 0.2);
    }

    public function testGetHeightInchesPartWhenHeightIsNull(): void
    {
        $this->assertSame(0.0, $this->user->getHeightInchesPart());
    }

    public function testGetHeightInchesPartWhenHeightIsZero(): void
    {
        $this->user->setHeight(0.0);

        $this->assertSame(0.0, $this->user->getHeightInchesPart());
    }
}
