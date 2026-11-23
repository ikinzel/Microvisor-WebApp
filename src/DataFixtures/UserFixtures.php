<?php

namespace App\DataFixtures;

use App\Entity\Disease;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class UserFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        $diseases = $manager->getRepository(Disease::class)->findAll();

        for ($i = 0; $i < 20; $i++) {
            $user = new User();
            $user->setFirstName($faker->firstName());
            $user->setLastName($faker->lastName());
            $user->setEmail($faker->unique()->email());
            $user->setPassword(password_hash('password123', PASSWORD_BCRYPT));
            $user->setAge($faker->numberBetween(18, 80));
            $user->setWeight($faker->randomFloat(1, 45, 120));
            $user->setHeight($faker->randomFloat(1, 140, 200));
            $user->setGender($faker->randomElement(['male', 'female', 'other']));
            $user->setLastLogin($faker->optional(0.7)->dateTimeBetween('-1 year', 'now'));

            if (!empty($diseases) && $faker->boolean(60)) {
                $user->setDisease($faker->randomElement($diseases));
            }

            $manager->persist($user);
            $this->addReference('user_' . $i, $user);
        }
        $manager->flush();
    }
}
