<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        self::assertResponseIsSuccessful();
    }

    public function testRegisterShowsErrorWhenPasswordsDoNotMatch(): void
    {
        $client = static::createClient();
        $email = 'password_'.uniqid().'@gmail.com';
        $client->request('POST', '/register', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'password' => 'secret123',
            'confirmPassword' => 'different123',
            'age' => '20',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error', 'Passwords do not match.');
    }

    public function testRegisterShowsErrorWhenAgeIsNegative(): void
    {
        $client = static::createClient();
        $email = 'negativeage_'.uniqid().'@gmail.com';
        $client->request('POST', '/register', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'password' => 'secret123',
            'confirmPassword' => 'secret123',
            'age' => '0',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'other',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error', 'Age must be a positive number.');
    }


    public function testRegisterShowsErrorWhenWeightIsZero(): void
    {
        $client = static::createClient();
        $email = 'zeroweight_'.uniqid().'@gmail.com';
        $client->request('POST', '/register', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'password' => 'secret123',
            'confirmPassword' => 'secret123',
            'age' => '20',
            'weight' => '0',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'female',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error', 'Weight must be a positive number.');
    }

    public function testRegisterShowsErrorWhenWeightIsNegative(): void
    {
        $client = static::createClient();
        $email = 'negativeweight_'.uniqid().'@gmail.com';
        $client->request('POST', '/register', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'password' => 'secret123',
            'confirmPassword' => 'secret123',
            'age' => '20',
            'weight' => '-10',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'female',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error', 'Weight must be a positive number.');
    }

    public function testRegisterShowsErrorWhenHeightIsZero(): void
    {
        $client = static::createClient();
        $email = 'zeroheight_'.uniqid().'@gmail.com';
        $client->request('POST', '/register', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'password' => 'secret123',
            'confirmPassword' => 'secret123',
            'age' => '20',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '0',
            'heightUnit' => 'cm',
            'gender' => 'female',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error', 'Height must be a positive number.');
    }

    public function testRegisterShowsErrorWhenHeightIsNegative(): void
    {
        $client = static::createClient();
        $email = 'negativeheight_'.uniqid().'@gmail.com';
        $client->request('POST', '/register', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'password' => 'secret123',
            'confirmPassword' => 'secret123',
            'age' => '20',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '-30',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error', 'Height must be a positive number.');
    }

    public function testRegisterShowsErrorWhenAgeIsZero(): void
    {
        $client = static::createClient();
        $email = 'zeroage_'.uniqid().'@gmail.com';
        $client->request('POST', '/register', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => $email,
            'password' => 'secret123',
            'confirmPassword' => 'secret123',
            'age' => '0',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error', 'Age must be a positive number.');
    }


    public function testRegisterRedirectsToHomeOnValidInput(): void
    {
        $client = static::createClient();
        $email = 'valid_'.uniqid().'@gmail.com';
        $client->request('POST', '/register', [
            'firstName' => 'Valid',
            'lastName' => 'User',
            'email' => $email,
            'password' => 'secret123',
            'confirmPassword' => 'secret123',
            'age' => '22',
            'weight' => '65',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'other',
        ]);
        self::assertResponseRedirects('/home');
    }

    public function testRegisterShowsErrorWhenPasswordTooShort(): void
    {
        $client = static::createClient();
        $client->request('POST', '/register', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => 'short_'.uniqid().'@gmail.com',
            'password' => '123',
            'confirmPassword' => '123',
            'age' => '20',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error', 'Password must be at least 6 characters.');
    }

    public function testRegisterShowsErrorWhenEmailAlreadyExists(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $client->request('POST', '/register', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => 'duplicate@gmail.com',
            'password' => 'secret123',
            'confirmPassword' => 'secret123',
            'age' => '20',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        $client->request('POST', '/register', [
            'firstName' => 'Test',
            'lastName' => 'User',
            'email' => 'duplicate@gmail.com',
            'password' => 'secret123',
            'confirmPassword' => 'secret123',
            'age' => '20',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error', 'This email is already registered.');
    }



}

