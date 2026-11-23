<?php

namespace App\Tests\Repository;

use App\Entity\BloodTestResult;
use App\Entity\User;
use App\Repository\BloodTestResultRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for BloodTestResultRepository::findByUserOrderedByUpload().
 * Verifies ordering: uploadedAt DESC, then nutrientName ASC within each upload.
 */
final class BloodTestResultRepositoryTest extends KernelTestCase
{
    private const USER_EMAIL       = 'repo_bloodtest@example.test';
    private const OTHER_USER_EMAIL = 'repo_bloodtest_other@example.test';

    private EntityManagerInterface $em;
    private BloodTestResultRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em         = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = $this->em->getRepository(BloodTestResult::class);
        $this->removeFixtureData();
    }

    protected function tearDown(): void
    {
        $this->removeFixtureData();
        parent::tearDown();
    }

    // -------------------------------------------------------
    // Tests
    // -------------------------------------------------------

    public function testReturnsEmptyArrayForUserWithNoResults(): void
    {
        $user    = $this->seedUser(self::USER_EMAIL);
        $results = $this->repository->findByUserOrderedByUpload($user);

        $this->assertSame([], $results);
    }

    public function testReturnsOnlyResultsForGivenUser(): void
    {
        $user      = $this->seedUser(self::USER_EMAIL);
        $otherUser = $this->seedUser(self::OTHER_USER_EMAIL);

        $t = new \DateTimeImmutable('2026-01-01 10:00:00');
        $this->persist($user,      'Iron',    90.0, $t);
        $this->persist($otherUser, 'Calcium', 300.0, $t);
        $this->em->flush();

        $results = $this->repository->findByUserOrderedByUpload($user);

        $this->assertCount(1, $results);
        $this->assertSame('Iron', $results[0]->getNutrientName());
    }

    public function testNewerUploadComesFirst(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);

        $this->persist($user, 'Iron',    90.0, new \DateTimeImmutable('2026-01-01 09:00:00'));
        $this->persist($user, 'Calcium', 300.0, new \DateTimeImmutable('2026-01-02 10:00:00'));
        $this->em->flush();

        $results = $this->repository->findByUserOrderedByUpload($user);

        $this->assertCount(2, $results);
        $this->assertSame('Calcium', $results[0]->getNutrientName());  // newer
        $this->assertSame('Iron',    $results[1]->getNutrientName());  // older
    }

    public function testNutrientsWithinSameUploadAreAlphabetical(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);
        $t    = new \DateTimeImmutable('2026-01-01 10:00:00');

        $this->persist($user, 'Zinc',    8.0,   $t);
        $this->persist($user, 'Iron',    90.0,  $t);
        $this->persist($user, 'Calcium', 300.0, $t);
        $this->em->flush();

        $results = $this->repository->findByUserOrderedByUpload($user);
        $names   = array_map(static fn (BloodTestResult $r) => $r->getNutrientName(), $results);

        $this->assertSame(['Calcium', 'Iron', 'Zinc'], $names);
    }

    public function testCombinedOrderingNewerUploadFirstThenAlphabetical(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);

        $older = new \DateTimeImmutable('2026-01-01 09:00:00');
        $newer = new \DateTimeImmutable('2026-01-02 10:00:00');

        $this->persist($user, 'Zinc',      8.0,   $older);
        $this->persist($user, 'Iron',      90.0,  $older);
        $this->persist($user, 'Vitamin D', 35.5,  $newer);
        $this->persist($user, 'Calcium',   300.0, $newer);
        $this->em->flush();

        $results = $this->repository->findByUserOrderedByUpload($user);
        $names   = array_map(static fn (BloodTestResult $r) => $r->getNutrientName(), $results);

        // Newer upload (Calcium, Vitamin D) before older upload (Iron, Zinc)
        $this->assertSame(['Calcium', 'Vitamin D', 'Iron', 'Zinc'], $names);
    }

    public function testSingleResultIsReturned(): void
    {
        $user = $this->seedUser(self::USER_EMAIL);
        $this->persist($user, 'Vitamin D', 35.5, new \DateTimeImmutable('2026-01-01 10:00:00'));
        $this->em->flush();

        $results = $this->repository->findByUserOrderedByUpload($user);

        $this->assertCount(1, $results);
        $this->assertSame('Vitamin D', $results[0]->getNutrientName());
        $this->assertSame(35.5, $results[0]->getValue());
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

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

    private function persist(User $user, string $nutrientName, float $value, \DateTimeImmutable $uploadedAt): void
    {
        $result = new BloodTestResult();
        $result->setUser($user);
        $result->setNutrientName($nutrientName);
        $result->setValue($value);
        $result->setUnit('mg');
        $result->setUploadedAt($uploadedAt);
        $this->em->persist($result);
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
