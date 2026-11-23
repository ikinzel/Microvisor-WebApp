<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileControllerTest extends WebTestCase
{
    private function loginUser(mixed $client): string
    {
        $client->disableReboot();
        $email = 'profile_' . uniqid() . '@gmail.com';

        $client->request('POST', '/register', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'password' => 'secret123',
            'confirmPassword' => 'secret123',
            'age' => '25',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        $client->request('POST', '/login', [
            'email' => $email,
            'password' => 'secret123',
        ]);

        return $email;
    }

    public function testRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile');

        self::assertResponseRedirects('/login');
    }

    public function testProfilePageLoadsWhenLoggedIn(): void
    {
        $client = static::createClient();
        $this->loginUser($client);

        $client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
    }


    public function testSuccessfulProfileUpdate(): void
    {
        $client = static::createClient();
        $email = $this->loginUser($client);

        $client->request('POST', '/profile', [
            'firstName' => 'Updated',
            'lastName' => 'Name',
            'email' => $email,
            'age' => '30',
            'weight' => '75',
            'weightUnit' => 'kg',
            'height' => '180',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Profile saved.');
    }

    public function testShowsErrorWhenFirstNameEmpty(): void
    {
        $client = static::createClient();
        $email = $this->loginUser($client);

        $client->request('POST', '/profile', [
            'firstName' => '',
            'lastName' => 'User',
            'email' => $email,
            'age' => '25',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'First name is required.');
    }

    public function testShowsErrorWhenLastNameEmpty(): void
    {
        $client = static::createClient();
        $email = $this->loginUser($client);

        $client->request('POST', '/profile', [
            'firstName' => 'Test',
            'lastName' => '',
            'email' => $email,
            'age' => '25',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Last name is required.');
    }

    public function testShowsErrorWhenEmailInvalid(): void
    {
        $client = static::createClient();
        $this->loginUser($client);

        $client->request('POST', '/profile', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => 'notanemail',
            'age' => '25',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Please enter a valid email address.');
    }

    public function testShowsErrorWhenAgeInvalid(): void
    {
        $client = static::createClient();
        $email = $this->loginUser($client);

        $client->request('POST', '/profile', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'age' => '0',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Age must be between 1 and 120.');
    }

    public function testShowsErrorWhenAgeOver120(): void
    {
        $client = static::createClient();
        $email = $this->loginUser($client);

        $client->request('POST', '/profile', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'age' => '150',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Age must be between 1 and 120.');
    }

    public function testShowsErrorWhenWeightNegative(): void
    {
        $client = static::createClient();
        $email = $this->loginUser($client);

        $client->request('POST', '/profile', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'age' => '25',
            'weight' => '-10',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Weight must be a positive number.');
    }

    public function testShowsErrorWhenHeightNegative(): void
    {
        $client = static::createClient();
        $email = $this->loginUser($client);

        $client->request('POST', '/profile', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'age' => '25',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '-10',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Height must be a positive number.');
    }

    public function testProfileUpdateWithLbs(): void
    {
        $client = static::createClient();
        $email = $this->loginUser($client);

        $client->request('POST', '/profile', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'age' => '25',
            'weight' => '150',
            'weightUnit' => 'lbs',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Profile saved.');
    }

    public function testProfileUpdateWithFeet(): void
    {
        $client = static::createClient();
        $email = $this->loginUser($client);

        $client->request('POST', '/profile', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'age' => '25',
            'weight' => '70',
            'weightUnit' => 'kg',
            'heightFt' => '5',
            'heightIn' => '10',
            'heightUnit' => 'ft',
            'gender' => 'female',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Profile saved.');
    }

    public function testShowsErrorWhenGenderInvalid(): void
    {
        $client = static::createClient();
        $email = $this->loginUser($client);

        $client->request('POST', '/profile', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'age' => '25',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'invalid',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Please choose a valid gender.');
    }

    public function testProfileRedirectsAfterLogout(): void
    {
        $client = static::createClient();
        $this->loginUser($client);

        $client->request('GET', '/logout');
        $client->request('GET', '/profile');
        self::assertResponseRedirects('/login');
    }

    public function testSaveCustomDailyGoals(): void
    {
        $client = static::createClient();
        $this->loginUser($client);

        $client->request('POST', '/profile', [
            '_action' => 'goals',
            'macros' => [
                'carbs_g' => ['min' => 200, 'max' => 300],
                'fiber_g' => 30,
                'protein_g' => 100,
                'fat_g' => ['min' => 50, 'max' => 80],
            ],
            'micronutrients' => [
                'vitamin_a' => 900,
                'vitamin_c' => 100,
                'vitamin_d' => 20,
                'vitamin_e' => 15,
                'vitamin_k' => 120,
                'thiamin' => 1.2,
                'riboflavin' => 1.3,
                'niacin' => 16,
                'vitamin_b6' => 1.7,
                'folate' => 400,
                'vitamin_b12' => 2.4,
                'pantothenic_acid' => 5,
                'biotin' => 30,
                'choline' => 550,
                'calcium' => 1200,
                'chloride' => 2300,
                'chromium' => 35,
                'fluoride' => 4,
                'iron' => 15,
                'magnesium' => 400,
                'manganese' => 2.3,
                'potassium' => 3400,
                'zinc' => 11,
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Daily goals saved.');
    }

    public function testResetDailyGoals(): void
    {
        $client = static::createClient();
        $this->loginUser($client);
        $client->request('POST', '/profile', [
            '_action' => 'goals',
            'resetGoals' => '1',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Daily goals reset to calculated recommendations.');
    }

    public function testGoalsValidationNegativeNumbers(): void
    {
        $client = static::createClient();
        $this->loginUser($client);

        $client->request('POST', '/profile', [
            '_action' => 'goals',
            'macros' => [
                'carbs_g' => ['min' => -10, 'max' => 300],
                'fiber_g' => 30,
                'protein_g' => 100,
                'fat_g' => ['min' => 50, 'max' => 80],
            ],
            'micronutrients' => [
                'vitamin_a' => 900,
                'vitamin_c' => 100,
                'vitamin_d' => 20,
                'vitamin_e' => 15,
                'vitamin_k' => 120,
                'thiamin' => 1.2,
                'riboflavin' => 1.3,
                'niacin' => 16,
                'vitamin_b6' => 1.7,
                'folate' => 400,
                'vitamin_b12' => 2.4,
                'pantothenic_acid' => 5,
                'biotin' => 30,
                'choline' => 550,
                'calcium' => 1200,
                'chloride' => 2300,
                'chromium' => 35,
                'fluoride' => 4,
                'iron' => 15,
                'magnesium' => 400,
                'manganese' => 2.3,
                'potassium' => 3400,
                'zinc' => 11,
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Macro ranges must be non-negative numbers.');
    }

    public function testGoalsValidationMinGreaterThanMax(): void
    {
        $client = static::createClient();
        $this->loginUser($client);

        $client->request('POST', '/profile', [
            '_action' => 'goals',
            'macros' => [
                'carbs_g' => ['min' => 400, 'max' => 300],  // min > max
                'fiber_g' => 30,
                'protein_g' => 100,
                'fat_g' => ['min' => 50, 'max' => 80],
            ],
            'micronutrients' => [
                'vitamin_a' => 900,
                'vitamin_c' => 100,
                'vitamin_d' => 20,
                'vitamin_e' => 15,
                'vitamin_k' => 120,
                'thiamin' => 1.2,
                'riboflavin' => 1.3,
                'niacin' => 16,
                'vitamin_b6' => 1.7,
                'folate' => 400,
                'vitamin_b12' => 2.4,
                'pantothenic_acid' => 5,
                'biotin' => 30,
                'choline' => 550,
                'calcium' => 1200,
                'chloride' => 2300,
                'chromium' => 35,
                'fluoride' => 4,
                'iron' => 15,
                'magnesium' => 400,
                'manganese' => 2.3,
                'potassium' => 3400,
                'zinc' => 11,
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Macro minimums cannot be greater than maximums.');
    }

}
