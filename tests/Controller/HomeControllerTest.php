<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    private function loginUser(mixed $client): string
    {
        $email = 'home_test_' . uniqid() . '@gmail.com';

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
        $client->request('GET', '/home');
        self::assertResponseRedirects('/login');
    }

    public function testAuthenticatedUserCanAccessHome(): void
    {
        $client = static::createClient();
        $this->loginUser($client);
        $client->request('GET', '/home');
        self::assertResponseIsSuccessful();
    }

    public function testHomePageShowsUserName(): void
    {
        $client = static::createClient();
        $this->loginUser($client);
        $client->request('GET', '/home');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Test');
    }

    public function testHomePageWeekView(): void
    {
        $client = static::createClient();
        $this->loginUser($client);
        $client->request('GET', '/home?view=week');
        self::assertResponseIsSuccessful();
    }

    public function testHomePageDayView(): void
    {
        $client = static::createClient();
        $this->loginUser($client);
        $client->request('GET', '/home?view=day');
        self::assertResponseIsSuccessful();
    }

    public function testHomePageWithSpecificDate(): void
    {
        $client = static::createClient();
        $this->loginUser($client);
        $client->request('GET', '/home?date=2026-01-15');
        self::assertResponseIsSuccessful();
    }

    public function testHomePageWithInvalidView(): void
    {
        $client = static::createClient();
        $this->loginUser($client);
        $client->request('GET', '/home?view=invalid');
        self::assertResponseIsSuccessful();
    }

    public function testHomePageWithoutWeekends(): void
    {
        $client = static::createClient();
        $this->loginUser($client);
        $client->request('GET', '/home?view=week&show_weekends=0');
        self::assertResponseIsSuccessful();
    }

    public function testHomeRedirectsAfterLogout(): void
    {
        $client = static::createClient();
        $this->loginUser($client);
        $client->request('GET', '/logout');
        $client->request('GET', '/home');
        self::assertResponseRedirects('/login');
    }

}
