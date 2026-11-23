<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ForgotPasswordControllerTest extends WebTestCase
{

    private function registerUser(mixed $client, string $email): void
    {
        $client->request('POST', '/register', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'password' => 'oldpassword123',
            'confirmPassword' => 'oldpassword123',
            'age' => '25',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);
    }

    public function testForgotPasswordPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/forgot-password');

        self::assertResponseIsSuccessful();
    }

    public function testShowsErrorWhenEmailNotFound(): void
    {
        $client = static::createClient();
        $client->request('POST', '/forgot-password', [
            'email' => 'nonexistent@gmail.com',
            'newPassword' => 'newpass123',
            'confirmPassword' => 'newpass123',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'No account found with this email.');
    }

    public function testShowsErrorWhenPasswordsDoNotMatch(): void
    {
        $client = static::createClient();
        $email = 'forgot_' . uniqid() . '@gmail.com';
        $this->registerUser($client, $email);

        $client->request('POST', '/forgot-password', [
            'email' => $email,
            'newPassword' => 'newpass123',
            'confirmPassword' => 'different456',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Passwords do not match.');
    }

    public function testShowsErrorWhenPasswordTooShort(): void
    {
        $client = static::createClient();
        $email = 'forgot_short_' . uniqid() . '@gmail.com';
        $this->registerUser($client, $email);

        $client->request('POST', '/forgot-password', [
            'email' => $email,
            'newPassword' => '123',
            'confirmPassword' => '123',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Password must be at least 6 characters.');
    }

    public function testSuccessfulPasswordResetRedirectsToLogin(): void
    {
        $client = static::createClient();
        $email = 'forgot_success_' . uniqid() . '@gmail.com';
        $this->registerUser($client, $email);

        $client->request('POST', '/forgot-password', [
            'email' => $email,
            'newPassword' => 'newpassword123',
            'confirmPassword' => 'newpassword123',
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testCanLoginWithNewPasswordAfterReset(): void
    {
        $client = static::createClient();
        $email = 'forgot_login_' . uniqid() . '@gmail.com';
        $this->registerUser($client, $email);

        $client->request('POST', '/forgot-password', [
            'email' => $email,
            'newPassword' => 'brandnewpass',
            'confirmPassword' => 'brandnewpass',
        ]);

        $client->request('POST', '/login', [
            'email' => $email,
            'password' => 'brandnewpass',
        ]);

        self::assertResponseRedirects('/home');
    }

    public function testOldPasswordFailsAfterReset(): void
    {
        $client = static::createClient();
        $email = 'forgot_old_' . uniqid() . '@gmail.com';
        $this->registerUser($client, $email);

        $client->request('POST', '/forgot-password', [
            'email' => $email,
            'newPassword' => 'brandnewpass',
            'confirmPassword' => 'brandnewpass',
        ]);

        $client->request('POST', '/login', [
            'email' => $email,
            'password' => 'oldpassword123',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Invalid email or password.');
    }
}
