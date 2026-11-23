<?php

namespace App\Tests\Controller;

use App\Entity\BloodTestResult;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container as SymfonyContainer;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Integration tests for GET /bloodtest and POST /bloodtest/upload.
 * Uses the test database and MockHttpClient to stub the OpenAI API.
 */
final class BloodTestControllerTest extends WebTestCase
{
    private const USER_EMAIL    = 'bloodtest_test@example.test';
    private const USER_PASSWORD = 'BloodTest!99';

    // -------------------------------------------------------
    // GET /bloodtest
    // -------------------------------------------------------

    public function testGetRedirectsToLoginWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->request('GET', '/bloodtest');

        self::assertResponseRedirects('/login');
    }

    public function testGetLoadsPageWhenLoggedIn(): void
    {
        $client = $this->createLoggedInClient();
        $client->request('GET', '/bloodtest');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Blood Test');
        self::assertSelectorExists('input[name="bloodtest_pdf"]');
    }

    // -------------------------------------------------------
    // POST /bloodtest/upload — auth guard
    // -------------------------------------------------------

    public function testUploadRedirectsToLoginWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->request('POST', '/bloodtest/upload');

        self::assertResponseRedirects('/login');
    }

    // -------------------------------------------------------
    // POST /bloodtest/upload — file validation
    // -------------------------------------------------------

    public function testUploadWithNoFileShowsError(): void
    {
        $client = $this->createLoggedInClient();
        $client->request('POST', '/bloodtest/upload');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error', 'Please select a PDF file');
    }

    public function testUploadWithNonPdfFileShowsError(): void
    {
        if (!extension_loaded('fileinfo')) {
            self::markTestSkipped('php_fileinfo extension is required for getMimeType() — enable it in php.ini to run this test.');
        }

        $client  = $this->createLoggedInClient();
        $tmpPath = tempnam(sys_get_temp_dir(), 'bt_test_');
        file_put_contents($tmpPath, 'not a pdf');
        $file = new UploadedFile($tmpPath, 'report.txt', 'text/plain', null, true);

        $client->request('POST', '/bloodtest/upload', [], ['bloodtest_pdf' => $file]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error', 'Only PDF files are accepted');

        @unlink($tmpPath);
    }

    // -------------------------------------------------------
    // POST /bloodtest/upload — extraction outcomes
    // -------------------------------------------------------

    public function testSuccessfulUploadPersistsResultsAndRedirects(): void
    {
        $http = $this->createOpenAiMock([
            ['name' => 'Vitamin D', 'value' => 35.5, 'unit' => 'ng/mL'],
            ['name' => 'Iron',      'value' => 90.0, 'unit' => 'µg/dL'],
        ]);
        $client = $this->createLoggedInClient($http);
        $client->request('POST', '/bloodtest/upload', [], ['bloodtest_pdf' => $this->makePdfFile()]);

        self::assertResponseRedirects('/bloodtest');

        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => self::USER_EMAIL]);
        $rows = $em->getRepository(BloodTestResult::class)->findBy(['user' => $user]);

        self::assertCount(2, $rows);
        $names = array_map(static fn (BloodTestResult $r) => $r->getNutrientName(), $rows);
        self::assertContains('Vitamin D', $names);
        self::assertContains('Iron', $names);
    }

    public function testUploadWhenExtractionReturnsEmptyShowsError(): void
    {
        $http   = $this->createOpenAiMock([]);  // OpenAI finds no nutrients
        $client = $this->createLoggedInClient($http);
        $client->request('POST', '/bloodtest/upload', [], ['bloodtest_pdf' => $this->makePdfFile()]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error', 'No vitamin or mineral values were found');
    }

    public function testUploadWhenExtractorThrowsShowsError(): void
    {
        $http = new MockHttpClient(static function () {
            return new MockResponse('Internal Server Error', ['http_code' => 500]);
        });
        $client = $this->createLoggedInClient($http);
        $client->request('POST', '/bloodtest/upload', [], ['bloodtest_pdf' => $this->makePdfFile()]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-error', 'Could not process the PDF');
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function createLoggedInClient(?MockHttpClient $http = null): KernelBrowser
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->removeFixtureData();

        if ($http !== null) {
            $this->replaceHttpClientInPublicContainer($http, $client);
        }

        $this->seedUser();

        $client->request('POST', '/login', [
            'email'    => self::USER_EMAIL,
            'password' => self::USER_PASSWORD,
        ]);
        $client->followRedirect();

        return $client;
    }

    private function replaceHttpClientInPublicContainer(MockHttpClient $mock, KernelBrowser $client): void
    {
        $public = $client->getKernel()->getContainer();
        if (!$public instanceof SymfonyContainer) {
            throw new \LogicException('Expected Symfony DI Container from kernel.');
        }

        $public->set(HttpClientInterface::class, $mock);
    }

    private function removeFixtureData(): void
    {
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => self::USER_EMAIL]);
        if ($user !== null) {
            $em->remove($user);
            $em->flush();
        }
    }

    private function seedUser(): void
    {
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User();
        $user->setEmail(self::USER_EMAIL);
        $user->setFirstName('Blood');
        $user->setLastName('Tester');
        $user->setPassword(password_hash(self::USER_PASSWORD, PASSWORD_DEFAULT));
        $user->setAge(30);
        $user->setWeight(75.0);
        $user->setHeight(180.0);
        $user->setGender('other');
        $em->persist($user);
        $em->flush();
    }

    private function makePdfFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bt_pdf_');
        file_put_contents($path, '%PDF-1.4 dummy blood test content');

        return new UploadedFile($path, 'bloodtest.pdf', 'application/pdf', null, true);
    }

    /**
     * @param list<array{name: string, value: float, unit: string}> $results
     */
    private function createOpenAiMock(array $results): MockHttpClient
    {
        return new MockHttpClient(static function (string $method, string $url) use ($results) {
            if (str_contains($url, 'api.openai.com')) {
                $content = json_encode(['results' => $results], JSON_THROW_ON_ERROR);
                $body    = json_encode(
                    ['choices' => [['message' => ['content' => $content]]]],
                    JSON_THROW_ON_ERROR
                );

                return new MockResponse($body, [
                    'http_code' => 200,
                    'headers'   => ['content-type' => 'application/json'],
                ]);
            }

            return new MockResponse('not found', ['http_code' => 404]);
        });
    }
}
