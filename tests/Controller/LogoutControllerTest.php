<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LogoutControllerTest extends WebTestCase
{
    private function loginUser(mixed $client): void
    {
        $email = 'logout_test_' . uniqid() . '@gmail.com';

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
    }

    public function testLogoutRedirectsToLanding(): void
    {
        $client = static::createClient();
        $client->request('GET', '/logout');

        self::assertResponseRedirects('/');
    }

    public function testLogoutWorksWhenNotLoggedIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/logout');

        self::assertResponseRedirects('/');
    }

    public function testLogoutClearsSession(): void
    {
        $client = static::createClient();

        $this->loginUser($client);
        $client->request('GET', '/logout');
        self::assertResponseRedirects('/');
        $client->request('GET', '/home');
        self::assertResponseRedirects('/login');
    }

    public function testLogoutCanBeCalledTwice(): void
    {
        $client = static::createClient();

        $client->request('GET', '/logout');
        self::assertResponseRedirects('/');

        $client->request('GET', '/logout');
        self::assertResponseRedirects('/');
    }
}
