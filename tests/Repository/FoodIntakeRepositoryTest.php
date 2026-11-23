<?php

namespace App\Tests\Repository;

use App\Entity\FoodIntake;
use App\Entity\User;
use App\Repository\FoodIntakeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


final class FoodIntakeRepositoryTest extends KernelTestCase
{
    private const USER_EMAIL = 'repo_foodintake@example.test';
    private const OTHER_USER_EMAIL = 'repo_foodintake_other@example.test';

    private EntityManagerInterface $em;
    private FoodIntakeRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = $this->em->getRepository(FoodIntake::class);
        $this->removeFixtureData();
    }

    protected function tearDown(): void
    {
        $this->removeFixtureData();
        parent::tearDown();
    }

    public function testReturnsEmptyArrayForUserWithNoIntakes(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);

        $results = $this->repository->findByUserCreatedBetween(
            $user,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-31')
        );

        $this->assertSame([], $results);
    }

    public function testReturnsOnlyResultsForGivenUser(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);
        $otherUser = $this->seedUser(self::OTHER_USER_EMAIL);

        $date = new \DateTimeImmutable('2026-01-15 10:00:00');

        $this->persistFoodIntake($user, 'Banana', $date);
        $this->persistFoodIntake($otherUser, 'Apple', $date);
        $this->em->flush();

        $results = $this->repository->findByUserCreatedBetween(
            $user,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-31')
        );

        $this->assertCount(1, $results);
        $this->assertSame('Banana', $results[0]->getSrcName());
    }

    public function testIncludesStartDate(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);

        $start = new \DateTimeImmutable('2026-01-15 10:00:00');
        $end = new \DateTimeImmutable('2026-01-31');

        $this->persistFoodIntake($user, 'Banana', $start);
        $this->em->flush();
        $results = $this->repository->findByUserCreatedBetween($user, $start, $end);
        $this->assertCount(1, $results);
        $this->assertSame('Banana', $results[0]->getSrcName());
    }

    public function testExcludesEndDate(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);

        $start = new \DateTimeImmutable('2026-01-01');
        $end = new \DateTimeImmutable('2026-01-31 10:00:00');

        $this->persistFoodIntake($user, 'Banana', $end);
        $this->em->flush();
        $results = $this->repository->findByUserCreatedBetween($user, $start, $end);
        $this->assertCount(0, $results);
    }

    public function testIncludesIntakesBeforeEndDate(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);

        $start = new \DateTimeImmutable('2026-01-01');
        $end = new \DateTimeImmutable('2026-01-31 23:59:59');

        $this->persistFoodIntake($user, 'Banana', new \DateTimeImmutable('2026-01-15'));
        $this->persistFoodIntake($user, 'Apple', new \DateTimeImmutable('2026-01-30'));
        $this->em->flush();

        $results = $this->repository->findByUserCreatedBetween($user, $start, $end);

        $this->assertCount(2, $results);
    }

    public function testExcludesIntakesBeforeStartDate(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);

        $start = new \DateTimeImmutable('2026-01-15');
        $end = new \DateTimeImmutable('2026-01-31');

        $this->persistFoodIntake($user, 'Banana', new \DateTimeImmutable('2026-01-10'));
        $this->persistFoodIntake($user, 'Apple', new \DateTimeImmutable('2026-01-20'));
        $this->em->flush();

        $results = $this->repository->findByUserCreatedBetween($user, $start, $end);

        $this->assertCount(1, $results);
        $this->assertSame('Apple', $results[0]->getSrcName());
    }

    public function testOrdersChronologicallyEarliestFirst(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);

        $start = new \DateTimeImmutable('2026-01-01');
        $end = new \DateTimeImmutable('2026-01-31');

        $this->persistFoodIntake($user, 'Grape', new \DateTimeImmutable('2026-01-20 15:00:00'));
        $this->persistFoodIntake($user, 'Banana', new \DateTimeImmutable('2026-01-15 10:00:00'));
        $this->persistFoodIntake($user, 'Apple', new \DateTimeImmutable('2026-01-10 08:00:00'));
        $this->em->flush();
        $results = $this->repository->findByUserCreatedBetween($user, $start, $end);

        $names = array_map(static fn (FoodIntake $f) => $f->getSrcName(), $results);
        $this->assertSame(['Apple', 'Banana', 'Grape'], $names);
    }

    public function testMultipleIntakesOnSameDayOrderedByTime(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);

        $start = new \DateTimeImmutable('2026-01-15');
        $end = new \DateTimeImmutable('2026-01-16');

        $this->persistFoodIntake($user, 'Lunch: Salad', new \DateTimeImmutable('2026-01-15 12:00:00'));
        $this->persistFoodIntake($user, 'Breakfast: Toast', new \DateTimeImmutable('2026-01-15 08:00:00'));
        $this->persistFoodIntake($user, 'Dinner: Fish', new \DateTimeImmutable('2026-01-15 18:00:00'));
        $this->em->flush();

        $results = $this->repository->findByUserCreatedBetween($user, $start, $end);

        $names = array_map(static fn (FoodIntake $f) => $f->getSrcName(), $results);
        $this->assertSame(['Breakfast: Toast', 'Lunch: Salad', 'Dinner: Fish'], $names);
    }

    public function testDayViewRange(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);

        $day = new \DateTimeImmutable('2026-01-15');
        $start = $day;
        $end = $day->add(new \DateInterval('P1D'));  // Next day (exclusive)

        $this->persistFoodIntake($user, 'Before', new \DateTimeImmutable('2026-01-14 23:59:59'));
        $this->persistFoodIntake($user, 'On day', new \DateTimeImmutable('2026-01-15 10:00:00'));
        $this->persistFoodIntake($user, 'After', new \DateTimeImmutable('2026-01-16 00:00:00'));
        $this->em->flush();

        $results = $this->repository->findByUserCreatedBetween($user, $start, $end);

        $this->assertCount(1, $results);
        $this->assertSame('On day', $results[0]->getSrcName());
    }

    public function testWeekViewRange(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);

        $monday = new \DateTimeImmutable('2026-01-12');  // Monday
        $nextMonday = new \DateTimeImmutable('2026-01-19');  // Next Monday (exclusive)

        $this->persistFoodIntake($user, 'Sunday before', new \DateTimeImmutable('2026-01-11'));
        $this->persistFoodIntake($user, 'Monday', new \DateTimeImmutable('2026-01-12'));
        $this->persistFoodIntake($user, 'Wednesday', new \DateTimeImmutable('2026-01-14'));
        $this->persistFoodIntake($user, 'Friday', new \DateTimeImmutable('2026-01-16'));
        $this->persistFoodIntake($user, 'Next Monday', new \DateTimeImmutable('2026-01-19'));
        $this->em->flush();

        $results = $this->repository->findByUserCreatedBetween($user, $monday, $nextMonday);

        $this->assertCount(3, $results);
        $names = array_map(static fn (FoodIntake $f) => $f->getSrcName(), $results);
        $this->assertSame(['Monday', 'Wednesday', 'Friday'], $names);
    }

    public function testSingleIntakeInRange(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);

        $date = new \DateTimeImmutable('2026-01-15 10:00:00');

        $this->persistFoodIntake($user, 'Banana', $date);
        $this->em->flush();

        $results = $this->repository->findByUserCreatedBetween(
            $user,
            new \DateTimeImmutable('2026-01-15'),
            new \DateTimeImmutable('2026-01-16')
        );

        $this->assertCount(1, $results);
        $this->assertSame('Banana', $results[0]->getSrcName());
    }

    public function testEmptyRangeReturnsNothing(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);

        $this->persistFoodIntake($user, 'Banana', new \DateTimeImmutable('2026-01-15'));
        $this->em->flush();
        $results = $this->repository->findByUserCreatedBetween(
            $user,
            new \DateTimeImmutable('2026-02-01'),
            new \DateTimeImmutable('2026-02-28')
        );
        $this->assertCount(0, $results);
    }

    private function seedUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Repo');
        $user->setLastName('Test');
        $user->setPassword(password_hash('test', PASSWORD_DEFAULT));
        $user->setAge(25);
        $user->setWeight(70.0);
        $user->setHeight(175.0);
        $user->setGender('other');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function persistFoodIntake(User $user, string $foodName, \DateTimeImmutable $createdAt): void
    {
        $intake = new FoodIntake();
        $intake->setUser($user);
        $intake->setSrcName($foodName);
        $intake->setSrcId(12345);
        $intake->setAmount(100.0);
        $intake->setUnit(\App\Enum\FoodIntakeUnit::Gram);
        $intake->setCreatedAt($createdAt);
        $this->em->persist($intake);
    }

    private function removeFixtureData(): void
    {
        foreach ([self::USER_EMAIL, self::OTHER_USER_EMAIL] as $email) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($user !== null) {
                $this->em->remove($user);
            }
        }
        $this->em->flush();
    }
}
