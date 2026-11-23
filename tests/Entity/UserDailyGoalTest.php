<?php

namespace App\Tests\Entity;

use App\Entity\User;
use App\Entity\UserDailyGoal;
use PHPUnit\Framework\TestCase;

final class UserDailyGoalTest extends TestCase
{
    private UserDailyGoal $goal;
    private User $user;

    protected function setUp(): void
    {
        $this->goal = new UserDailyGoal();
        $this->user = new User();
        $this->user->setEmail('test@example.com');
        $this->user->setFirstName('Test');
        $this->user->setLastName('User');
        $this->user->setPassword('hashed_password');
        $this->user->setAge(25);
        $this->user->setWeight(70.0);
        $this->user->setHeight(175.0);
        $this->user->setGender('male');
    }

    public function testGoalHasNoIdInitially(): void
    {
        self::assertNull($this->goal->getId());
    }

    public function testSetAndGetUser(): void
    {
        $this->goal->setUser($this->user);

        self::assertSame($this->user, $this->goal->getUser());
    }

    public function testSetUserReturnsStatic(): void
    {
        $result = $this->goal->setUser($this->user);

        self::assertSame($this->goal, $result);
    }

    public function testUserCanBeNull(): void
    {
        $this->goal->setUser(null);

        self::assertNull($this->goal->getUser());
    }

    public function testSetAndGetGoalKey(): void
    {
        $this->goal->setGoalKey('vitamin_a');

        self::assertSame('vitamin_a', $this->goal->getGoalKey());
    }

    public function testSetGoalKeyReturnsStatic(): void
    {
        $result = $this->goal->setGoalKey('vitamin_c');

        self::assertSame($this->goal, $result);
    }

    public function testGoalKeyCanBeLong(): void
    {
        $longKey = 'pantothenic_acid_vitamin_b5_and_related_compounds';
        $this->goal->setGoalKey($longKey);

        self::assertSame($longKey, $this->goal->getGoalKey());
    }

    public function testSetAndGetValue(): void
    {
        $this->goal->setValue(800.5);

        self::assertSame(800.5, $this->goal->getValue());
    }

    public function testSetValueReturnsStatic(): void
    {
        $result = $this->goal->setValue(1000.0);

        self::assertSame($this->goal, $result);
    }

    public function testValueCanBeZero(): void
    {
        $this->goal->setValue(0.0);

        self::assertSame(0.0, $this->goal->getValue());
    }

    public function testValueCanBeLarge(): void
    {
        $this->goal->setValue(99999.99);

        self::assertSame(99999.99, $this->goal->getValue());
    }

    public function testSetAndGetUnit(): void
    {
        $this->goal->setUnit('mcg');

        self::assertSame('mcg', $this->goal->getUnit());
    }

    public function testSetUnitReturnsStatic(): void
    {
        $result = $this->goal->setUnit('mg');

        self::assertSame($this->goal, $result);
    }

    public function testUnitCommonValues(): void
    {
        $units = ['mcg', 'mg', 'g', 'IU', 'mmol'];

        foreach ($units as $unit) {
            $this->goal->setUnit($unit);
            self::assertSame($unit, $this->goal->getUnit());
        }
    }

    public function testCompleteGoalSetup(): void
    {
        $this->goal
            ->setUser($this->user)
            ->setGoalKey('calcium')
            ->setValue(1000.0)
            ->setUnit('mg');

        self::assertSame($this->user, $this->goal->getUser());
        self::assertSame('calcium', $this->goal->getGoalKey());
        self::assertSame(1000.0, $this->goal->getValue());
        self::assertSame('mg', $this->goal->getUnit());
    }

    public function testMultipleGoalsForSameUser(): void
    {
        $goal1 = new UserDailyGoal();
        $goal1->setUser($this->user)
            ->setGoalKey('vitamin_a')
            ->setValue(700.0)
            ->setUnit('mcg');

        $goal2 = new UserDailyGoal();
        $goal2->setUser($this->user)
            ->setGoalKey('vitamin_c')
            ->setValue(90.0)
            ->setUnit('mg');

        self::assertSame($this->user, $goal1->getUser());
        self::assertSame($this->user, $goal2->getUser());
        self::assertSame('vitamin_a', $goal1->getGoalKey());
        self::assertSame('vitamin_c', $goal2->getGoalKey());
    }

    public function testGoalWithFloatPrecision(): void
    {
        $this->goal->setValue(123.456789);

        self::assertSame(123.456789, $this->goal->getValue());
    }

    public function testGoalKeyIsNullInitially(): void
    {
        self::assertNull($this->goal->getGoalKey());
    }

    public function testValueIsNullInitially(): void
    {
        self::assertNull($this->goal->getValue());
    }

    public function testUnitIsNullInitially(): void
    {
        self::assertNull($this->goal->getUnit());
    }
}
