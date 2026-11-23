<?php

namespace App\DataFixtures;

use App\Entity\BloodTestResult;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class BloodTestResultFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();
        $nutrients = [
            ['name' => 'Calcium', 'unit' => 'mmol/L', 'min' => 2.0, 'max' => 2.8],
            ['name' => 'Chloride', 'unit' => 'mmol/L', 'min' => 95.0, 'max' => 110.0],
            ['name' => 'Sodium', 'unit' => 'mmol/L', 'min' => 135.0, 'max' => 148.0],
            ['name' => 'Potassium', 'unit' => 'mmol/L', 'min' => 3.5, 'max' => 5.5],
            ['name' => 'Glucose', 'unit' => 'mmol/L', 'min' => 3.5, 'max' => 11.0],
            ['name' => 'Iron', 'unit' => 'µmol/L', 'min' => 10.0, 'max' => 30.0],
            ['name' => 'Hemoglobin', 'unit' => 'g/L', 'min' => 120.0, 'max' => 180.0],
            ['name' => 'Cholesterol', 'unit' => 'mmol/L', 'min' => 3.0, 'max' => 7.0],
            ['name' => 'Vitamin D', 'unit' => 'nmol/L', 'min' => 25.0, 'max' => 200.0],
            ['name' => 'Vitamin B12', 'unit' => 'pmol/L', 'min' => 150.0, 'max' => 700.0],
        ];

        for ($i = 0; $i < 10; $i++) {
            $user = $this->getReference('user_' . $i, \App\Entity\User::class);
            $uploadedAt = \DateTimeImmutable::createFromMutable(
                $faker->dateTimeBetween('-1 year', 'now')
            );

            foreach ($nutrients as $nutrient) {
                $result = new BloodTestResult();
                $result->setUser($user);
                $result->setNutrientName($nutrient['name']);
                $result->setValue($faker->randomFloat(2, $nutrient['min'], $nutrient['max']));
                $result->setUnit($nutrient['unit']);
                $result->setUploadedAt($uploadedAt);

                $manager->persist($result);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
