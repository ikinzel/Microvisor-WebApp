<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Login');
    }


    public function testLoginShowsErrorWhenPasswordIsWrong(): void
    {
        $client = static::createClient();

        $client->request('POST', '/register', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => 'logintest@gmail.com',
            'password' => 'correctpassword',
            'confirmPassword' => 'correctpassword',
            'age' => '25',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        $client->request('POST', '/login', [
            'email' => 'logintest@gmail.com',
            'password' => 'wrongpassword',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Invalid email or password.');
    }

    public function testLoginShowsErrorWhenEmailNotFound(): void
    {
        $client = static::createClient();
        $client->request('POST', '/login', [
            'email' => 'nonexistent@gmail.com',
            'password' => 'secret123',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Invalid email or password.');
    }

    public function testLoginRedirectsToHomeOnValidCredentials(): void
    {
        $client = static::createClient();

        $email = 'validlogin_'.uniqid().'@gmail.com';
        $client->request('POST', '/register', [
            'firstName' => 'Valid',
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

        self::assertResponseRedirects('/home');
    }

    public function testLoginShowsErrorWhenEmailIsEmpty(): void
    {
        $client = static::createClient();
        $client->request('POST', '/login', [
            'email' => '',
            'password' => 'secret123',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Invalid email or password.');
    }

    public function testLoginShowsErrorWhenPasswordIsEmpty(): void
    {
        $client = static::createClient();
        $client->request('POST', '/login', [
            'email' => 'someone@gmail.com',
            'password' => '',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Invalid email or password.');
    }
    public function testLoginPageHasForgotPasswordLink(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/forgot-password"]');
    }
}
