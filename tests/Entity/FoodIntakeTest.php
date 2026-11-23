<?php

namespace App\Tests\Entity;

use App\Entity\FoodIntake;
use App\Entity\User;
use App\Enum\FoodIntakeUnit;
use PHPUnit\Framework\TestCase;

class FoodIntakeTest extends TestCase
{
    private FoodIntake $foodIntake;

    protected function setUp(): void
    {
        $this->foodIntake = new FoodIntake();
    }

    public function testDefaultValuesAreNull(): void
    {
        $this->assertNull($this->foodIntake->getId());
        $this->assertNull($this->foodIntake->getUser());
        $this->assertNull($this->foodIntake->getSrcId());
        $this->assertNull($this->foodIntake->getSrcName());
        $this->assertNull($this->foodIntake->getAmount());
        $this->assertNull($this->foodIntake->getUnit());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();

        $foodIntake = new FoodIntake();

        $after = new \DateTimeImmutable();
        $this->assertGreaterThanOrEqual($before, $foodIntake->getCreatedAt());
        $this->assertLessThanOrEqual($after, $foodIntake->getCreatedAt());
    }

    public function testSetAndGetUser(): void
    {
        $user = new User();

        $result = $this->foodIntake->setUser($user);

        $this->assertSame($user, $this->foodIntake->getUser());
        $this->assertSame($this->foodIntake, $result);
    }

    public function testSetUserToNull(): void
    {
        $this->foodIntake->setUser(new User());

        $this->foodIntake->setUser(null);

        $this->assertNull($this->foodIntake->getUser());
    }

    public function testSetAndGetSrcId(): void
    {
        $result = $this->foodIntake->setSrcId(123456);

        $this->assertSame(123456, $this->foodIntake->getSrcId());
        $this->assertSame($this->foodIntake, $result);
    }

    public function testSetAndGetSrcName(): void
    {
        $result = $this->foodIntake->setSrcName('Banana');

        $this->assertSame('Banana', $this->foodIntake->getSrcName());
        $this->assertSame($this->foodIntake, $result);
    }

    public function testSetSrcNameToNull(): void
    {
        $this->foodIntake->setSrcName('Apple');

        $this->foodIntake->setSrcName(null);

        $this->assertNull($this->foodIntake->getSrcName());
    }

    public function testSetAndGetAmount(): void
    {
        $result = $this->foodIntake->setAmount(150.0);

        $this->assertSame(150.0, $this->foodIntake->getAmount());
        $this->assertSame($this->foodIntake, $result);
    }

    public function testSetAndGetUnitGram(): void
    {
        $result = $this->foodIntake->setUnit(FoodIntakeUnit::Gram);

        $this->assertSame(FoodIntakeUnit::Gram, $this->foodIntake->getUnit());
        $this->assertSame($this->foodIntake, $result);
    }

    public function testSetAndGetUnitUnit(): void
    {
        $result = $this->foodIntake->setUnit(FoodIntakeUnit::Unit);

        $this->assertSame(FoodIntakeUnit::Unit, $this->foodIntake->getUnit());
        $this->assertSame($this->foodIntake, $result);
    }

    public function testSetAndGetCreatedAt(): void
    {
        $date = new \DateTimeImmutable('2025-06-01 12:00:00');

        $result = $this->foodIntake->setCreatedAt($date);

        $this->assertSame($date, $this->foodIntake->getCreatedAt());
        $this->assertSame($this->foodIntake, $result);
    }
}
