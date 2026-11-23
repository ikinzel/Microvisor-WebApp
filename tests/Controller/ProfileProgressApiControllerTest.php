<?php

namespace App\Tests\Controller;

use App\Controller\ProfileProgressApiController;
use App\Entity\User;
use App\Repository\FoodIntakeRepository;
use App\Service\DailyRequirementsCalculator;
use App\Service\FoodNutrientProviderInterface;
use App\Service\NutrientProgressCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class ProfileProgressApiControllerTest extends TestCase
{
    public function testWeeklyProgressUsesSevenDayRangeAndTargets(): void
    {
        $user = $this->makeUser();
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with('user_id')->willReturn(123);

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('find')->with(123)->willReturn($user);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(User::class)->willReturn($userRepository);

        $foodIntakes = $this->createMock(FoodIntakeRepository::class);
        $foodIntakes
            ->expects($this->once())
            ->method('findByUserCreatedBetween')
            ->with(
                $user,
                $this->callback(static fn (\DateTimeImmutable $date): bool => $date->format('Y-m-d') === '2026-05-11'),
                $this->callback(static fn (\DateTimeImmutable $date): bool => $date->format('Y-m-d') === '2026-05-18'),
            )
            ->willReturn([]);

        $foodNutrients = $this->createMock(FoodNutrientProviderInterface::class);
        $foodNutrients->expects($this->never())->method('getFoodNutrients');

        $controller = new ProfileProgressApiController();
        $response = $controller->index(
            new Request(['range' => 'week', 'date' => '2026-05-13']),
            $session,
            $entityManager,
            $foodIntakes,
            new DailyRequirementsCalculator(),
            new NutrientProgressCalculator($foodNutrients),
        );

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('week', $payload['range']);
        self::assertSame('2026-05-11', $payload['startDate']);
        self::assertSame('2026-05-17', $payload['endDate']);
        self::assertSame(7, $payload['targetDays']);
        self::assertEquals(630.0, $payload['recommended']['vitamin_c']['value']);
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setFirstName('Progress');
        $user->setLastName('User');
        $user->setEmail('progress_test_'.uniqid().'@gmail.com');
        $user->setPassword(password_hash('secret123', PASSWORD_DEFAULT));
        $user->setAge(25);
        $user->setWeight(70.0);
        $user->setHeight(170.0);
        $user->setWeightUnit('kg');
        $user->setHeightUnit('cm');
        $user->setGender('male');

        return $user;
    }
}
