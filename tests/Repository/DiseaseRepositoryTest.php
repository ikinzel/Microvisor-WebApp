<?php

namespace App\Tests\Repository;

use App\Entity\Disease;
use App\Repository\DiseaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DiseaseRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DiseaseRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = $this->em->getRepository(Disease::class);
        $this->removeFixtureData();
    }

    protected function tearDown(): void
    {
        $this->removeFixtureData();
        parent::tearDown();
    }

    public function testReturnsEmptyArrayWhenNoDiseases(): void
    {
        $results = $this->repository->findAllOrderedByLabelExcludingTest();

        $this->assertSame([], $results);
    }

    public function testReturnsAllDiseasesWhenNoneAreTestData(): void
    {
        $this->persistDisease('ANEMIA', 'Anemia');
        $this->persistDisease('DIABETES', 'Diabetes');
        $this->persistDisease('CKDA', 'Chronic Kidney Disease');
        $this->em->flush();

        $results = $this->repository->findAllOrderedByLabelExcludingTest();

        $this->assertCount(3, $results);
    }

    public function testFiltersOutTestInLabel(): void
    {
        $this->persistDisease('ANEMIA', 'Anemia');
        $this->persistDisease('TEST_DISEASE', 'Test Disease');
        $this->persistDisease('DIABETES', 'Diabetes');
        $this->em->flush();
        $results = $this->repository->findAllOrderedByLabelExcludingTest();
        $this->assertCount(2, $results);
        $labels = array_map(static fn (Disease $d) => $d->getLabel(), $results);
        $this->assertNotContains('Test Disease', $labels);
    }

    public function testFiltersOutTestInCode(): void
    {
        $this->persistDisease('ANEMIA', 'Anemia');
        $this->persistDisease('TEST_CODE', 'Some Disease');
        $this->persistDisease('DIABETES', 'Diabetes');
        $this->em->flush();

        $results = $this->repository->findAllOrderedByLabelExcludingTest();

        $this->assertCount(2, $results);
        $codes = array_map(static fn (Disease $d) => $d->getCode(), $results);
        $this->assertNotContains('TEST_CODE', $codes);
    }

    public function testFiltersOutBothTestLabelAndTestCode(): void
    {
        $this->persistDisease('ANEMIA', 'Anemia');
        $this->persistDisease('TEST_DISEASE', 'Test Disease');
        $this->persistDisease('CODE_TEST', 'Another Disease');
        $this->persistDisease('DIABETES', 'Diabetes');
        $this->em->flush();

        $results = $this->repository->findAllOrderedByLabelExcludingTest();

        $this->assertCount(2, $results);
        $labels = array_map(static fn (Disease $d) => $d->getLabel(), $results);
        $this->assertSame(['Anemia', 'Diabetes'], $labels);
    }

    public function testOrdersAlphabeticallyByLabel(): void
    {
        $this->persistDisease('ZINC', 'Zinc Deficiency');
        $this->persistDisease('ANEMIA', 'Anemia');
        $this->persistDisease('DIABETES', 'Diabetes');
        $this->persistDisease('CANCER', 'Cancer');
        $this->em->flush();

        $results = $this->repository->findAllOrderedByLabelExcludingTest();

        $labels = array_map(static fn (Disease $d) => $d->getLabel(), $results);
        $this->assertSame(['Anemia', 'Cancer', 'Diabetes', 'Zinc Deficiency'], $labels);
    }

    public function testCaseInsensitiveFilteringForTest(): void
    {
        $this->persistDisease('ANEMIA', 'Anemia');
        $this->persistDisease('TEST_LOWER', 'Test Lower');  // "test" in code
        $this->persistDisease('UPPER_TEST', 'Upper Test');  // "TEST" in label
        $this->persistDisease('DIABETES', 'Diabetes');
        $this->em->flush();

        $results = $this->repository->findAllOrderedByLabelExcludingTest();

        $this->assertCount(2, $results);
        $labels = array_map(static fn (Disease $d) => $d->getLabel(), $results);
        $this->assertSame(['Anemia', 'Diabetes'], $labels);
    }

    public function testSingleDiseaseNoTestData(): void
    {
        $this->persistDisease('ANEMIA', 'Anemia');
        $this->em->flush();

        $results = $this->repository->findAllOrderedByLabelExcludingTest();

        $this->assertCount(1, $results);
        $this->assertSame('Anemia', $results[0]->getLabel());
    }

    public function testMixedValidAndTestDiseasesReturnsOnlyValid(): void
    {
        $this->persistDisease('ANEMIA', 'Anemia');
        $this->persistDisease('BETA_TEST', 'Beta Test Disease');
        $this->persistDisease('DIABETES', 'Diabetes');
        $this->persistDisease('TEST_PLACEHOLDER', 'Test Placeholder');
        $this->persistDisease('CKDA', 'Chronic Kidney Disease');
        $this->em->flush();

        $results = $this->repository->findAllOrderedByLabelExcludingTest();

        $this->assertCount(3, $results);
        $labels = array_map(static fn (Disease $d) => $d->getLabel(), $results);
        $this->assertSame(['Anemia', 'Chronic Kidney Disease', 'Diabetes'], $labels);
    }


    private function persistDisease(string $code, string $label): void
    {
        $disease = new Disease();
        $disease->setCode($code);
        $disease->setLabel($label);
        $this->em->persist($disease);
    }

    private function removeFixtureData(): void
    {
        $diseases = $this->em->getRepository(Disease::class)->findAll();
        foreach ($diseases as $disease) {
            $this->em->remove($disease);
        }
        $this->em->flush();
    }
}
