<?php

namespace App\Tests\Entity;

use App\Entity\BloodTestResult;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class BloodTestResultTest extends TestCase
{
    private BloodTestResult $bloodTestResult;

    protected function setUp(): void
    {
        $this->bloodTestResult = new BloodTestResult();
    }

    public function testDefaultValuesAreNull(): void
    {
        $this->assertNull($this->bloodTestResult->getId());
        $this->assertNull($this->bloodTestResult->getUser());
        $this->assertNull($this->bloodTestResult->getNutrientName());
        $this->assertNull($this->bloodTestResult->getValue());
        $this->assertNull($this->bloodTestResult->getUnit());
        $this->assertNull($this->bloodTestResult->getUploadedAt());
    }

    public function testSetAndGetUser(): void
    {
        $user = new User();

        $result = $this->bloodTestResult->setUser($user);

        $this->assertSame($user, $this->bloodTestResult->getUser());
        $this->assertSame($this->bloodTestResult, $result);
    }

    public function testSetUserToNull(): void
    {
        $this->bloodTestResult->setUser(new User());

        $this->bloodTestResult->setUser(null);

        $this->assertNull($this->bloodTestResult->getUser());
    }

    public function testSetAndGetNutrientName(): void
    {
        $result = $this->bloodTestResult->setNutrientName('Iron');

        $this->assertSame('Iron', $this->bloodTestResult->getNutrientName());
        $this->assertSame($this->bloodTestResult, $result);
    }

    public function testSetAndGetValue(): void
    {
        $result = $this->bloodTestResult->setValue(12.5);

        $this->assertSame(12.5, $this->bloodTestResult->getValue());
        $this->assertSame($this->bloodTestResult, $result);
    }

    public function testSetAndGetUnit(): void
    {
        $result = $this->bloodTestResult->setUnit('mg/dL');

        $this->assertSame('mg/dL', $this->bloodTestResult->getUnit());
        $this->assertSame($this->bloodTestResult, $result);
    }

    public function testSetAndGetUploadedAt(): void
    {
        $date = new \DateTimeImmutable('2025-03-01 09:00:00');

        $result = $this->bloodTestResult->setUploadedAt($date);

        $this->assertSame($date, $this->bloodTestResult->getUploadedAt());
        $this->assertSame($this->bloodTestResult, $result);
    }
}
