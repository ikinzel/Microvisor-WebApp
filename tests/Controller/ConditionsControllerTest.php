<?php

namespace App\Tests\Controller;

use App\Entity\Disease;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ConditionsControllerTest extends WebTestCase
{
    private const USER_PASSWORD = 'ConditionsTest!99';

    private function loginUser(mixed $client, string $email): void
    {
        $client->disableReboot();
        $client->request('POST', '/register', [
            'firstName' => 'Conditions',
            'lastName' => 'Tester',
            'email' => $email,
            'password' => self::USER_PASSWORD,
            'confirmPassword' => self::USER_PASSWORD,
            'age' => '25',
            'weight' => '70',
            'weightUnit' => 'kg',
            'height' => '170',
            'heightUnit' => 'cm',
            'gender' => 'male',
        ]);

        $client->request('POST', '/login', [
            'email' => $email,
            'password' => self::USER_PASSWORD,
        ]);
    }

    private function seedDisease(string $code, string $label): Disease
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $disease = new Disease();
        $disease->setCode($code);
        $disease->setLabel($label);
        $disease->setDescription('Test condition for functional tests.');
        $disease->setIntakeSuggestions(['iron' => 'Prefer iron-rich foods.']);

        $em->persist($disease);
        $em->flush();

        return $disease;
    }

    public function testRedirectsToLoginWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->request('GET', '/conditions');

        self::assertResponseRedirects('/login');
    }

    public function testPageLoadsWhenLoggedIn(): void
    {
        $client = static::createClient();
        $email = 'conditions_page_' . uniqid('', true) . '@example.test';
        $this->loginUser($client, $email);

        $client->request('GET', '/conditions');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Medical conditions');
    }

    public function testPostConditionSelectionSavesDisease(): void
    {
        $client = static::createClient();
        $email = 'conditions_save_' . uniqid('', true) . '@example.test';
        $this->loginUser($client, $email);
        $disease = $this->seedDisease('COND_TEST_' . uniqid(), 'Test Disease');

        $client->request('POST', '/conditions', [
            'disease_id' => (string) $disease->getId(),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-success', 'Your condition has been saved.');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user);
        self::assertNotNull($user->getDisease());
        self::assertSame($disease->getId(), $user->getDisease()?->getId());
    }


    public function testPostNoneConditionClearsDisease(): void
    {
        $client = static::createClient();
        $email = 'conditions_none_' . uniqid('', true) . '@example.test';
        $this->loginUser($client, $email);

        $client->request('POST', '/conditions', [
            'disease_id' => 'none',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-success', 'no medical condition selected');
    }

    public function testPostEmptyConditionClearsDisease(): void
    {
        $client = static::createClient();
        $email = 'conditions_empty_' . uniqid('', true) . '@example.test';
        $this->loginUser($client, $email);

        $client->request('POST', '/conditions', [
            'disease_id' => '',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-success', 'no medical condition selected');
    }

    public function testPostInvalidDiseaseIdShowsError(): void
    {
        $client = static::createClient();
        $email = 'conditions_invalid_' . uniqid('', true) . '@example.test';
        $this->loginUser($client, $email);

        $client->request('POST', '/conditions', [
            'disease_id' => '99999',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Please choose a valid condition from the list.');
    }

    public function testRedirectsToLoginWhenSessionUserDoesNotExist(): void
    {
        $client = static::createClient();

        $session = static::getContainer()->get('session.factory')->createSession();
        $session->set('user_id', 999999);
        $session->set('user_name', 'Ghost User');
        $session->save();

        $client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie(
            $session->getName(),
            $session->getId()
        ));

        $client->request('GET', '/conditions');

        self::assertResponseRedirects('/login');
    }
}
