<?php

namespace App\Tests\Controller;

use App\Entity\FoodIntake;
use App\Entity\User;
use App\Enum\FoodIntakeUnit;
use App\Tests\Support\ReceiptTestImageFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container as SymfonyContainer;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Functional tests for /food/input (manual + receipt upload).
 * Uses DATABASE_URL from .env.test and MockHttpClient for OpenAI + USDA APIs.
 */
final class FoodIntakeControllerTest extends WebTestCase
{
    private const USER_EMAIL = 'foodintake_test@example.test';

    private const USER_PASSWORD = 'FoodTest!99';

    public function testGetRedirectsToLoginWhenAnonymous(): void
    {
        $client = static::createClient();

        $client->request('GET', '/food/input');

        self::assertResponseRedirects('/login');
    }

    public function testGetRedirectsToHomeWhenLoggedIn(): void
    {
        $client = $this->createLoggedInClient();
        $client->request('GET', '/food/input');

        // GET /food/input is a permanent redirect to /home (input UI now lives on the dashboard).
        self::assertResponseRedirects('/home', 301);
    }

    public function testManualSubmitCreatesFoodIntake(): void
    {
        $http = $this->createMultiFoodUsdaMock();
        $client = $this->createLoggedInClient($http);

        $client->request('POST', '/food/input', [
            '_action' => 'manual',
            'food_name' => 'banana raw',
            'amount' => '2',
            'unit' => 'unit',
        ]);

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/home', $location);

        $session = $client->getRequest()->getSession();
        $pendingFoods = $session->get('_pending_foods');
        self::assertIsArray($pendingFoods);
        self::assertGreaterThan(0, count($pendingFoods['foods']));
    }

    public function testReceiptNormalUploadRedirectsToVerify(): void
    {
        $fixture = $this->loadReceiptFixture('receipt_normal');
        $http = $this->createOpenAiAndUsdaMock($fixture['items']);
        $client = $this->createLoggedInClient($http);

        $path = ReceiptTestImageFactory::sharpFixturePath();
        $file = new UploadedFile($path, 'receipt.jpg', ReceiptTestImageFactory::sharpMimeType(), null, true);
        $client->request('POST', '/food/input', ['_action' => 'receipt'], ['receipt' => $file]);

        self::assertResponseRedirects('/food/input/receipt/verify');
        $client->followRedirect();
        self::assertSelectorTextContains('h2', 'Verify receipt');
    }

    public function testReceiptEnglishUploadRedirectsToVerify(): void
    {
        $fixture = $this->loadReceiptFixture('receipt_english');
        $http = $this->createOpenAiAndUsdaMock($fixture['items']);
        $client = $this->createLoggedInClient($http);

        $path = ReceiptTestImageFactory::englishFixturePath();
        $file = new UploadedFile($path, 'receipt_en.jpg', ReceiptTestImageFactory::englishMimeType(), null, true);
        $client->request('POST', '/food/input', ['_action' => 'receipt'], ['receipt' => $file]);

        self::assertResponseRedirects('/food/input/receipt/verify');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'ROTI CKN PLN');
        self::assertSelectorTextContains('body', 'OG GALA APPLES');
    }

    public function testReceiptDutchUploadRedirectsToVerify(): void
    {
        $fixture = $this->loadReceiptFixture('receipt_dutch');
        $http = $this->createOpenAiAndUsdaMock($fixture['items']);
        $client = $this->createLoggedInClient($http);

        $path = ReceiptTestImageFactory::sharpFixturePath();
        $file = new UploadedFile($path, 'receipt_nl.png', 'image/png', null, true);
        $client->request('POST', '/food/input', ['_action' => 'receipt'], ['receipt' => $file]);

        self::assertResponseRedirects('/food/input/receipt/verify');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'OLIJVEN');
        self::assertSelectorTextContains('body', 'AARDBEIEN');
    }

    public function testReceiptModelFlagsUnreadableShowsErrorFlash(): void
    {
        $http = $this->createOpenAiAndUsdaMock([], false);
        $client = $this->createLoggedInClient($http);

        $path = ReceiptTestImageFactory::sharpFixturePath();
        $file = new UploadedFile($path, 'receipt.jpg', ReceiptTestImageFactory::sharpMimeType(), null, true);
        $client->request('POST', '/food/input', ['_action' => 'receipt'], ['receipt' => $file]);

        $response = $client->getResponse();
        self::assertTrue($response->isRedirect());
        self::assertStringStartsWith('/home', $response->headers->get('Location'));
        $client->followRedirect();
        self::assertSelectorTextContains('.form-error', 'could not read');
    }

    /**
     * Blur detection uses GD in ReceiptImageBlurChecker. PHPUnit docblock @requires is unreliable here; skip explicitly.
     */
    public function testReceiptBlurryImageRejectedBeforeOcr(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('ext-gd is required so ReceiptImageBlurChecker can measure blur.');
        }

        $client = $this->createLoggedInClient();

        $path = ReceiptTestImageFactory::blurryFixturePath();
        $file = new UploadedFile($path, 'blurry.png', 'image/png', null, true);
        $client->request('POST', '/food/input', ['_action' => 'receipt'], ['receipt' => $file]);

        $response = $client->getResponse();
        self::assertTrue($response->isRedirect());
        self::assertStringStartsWith('/home', $response->headers->get('Location'));
        $client->followRedirect();
        self::assertSelectorTextContains('.form-error', 'too blurry');
    }

    public function testReceiptVerifySubmitPersistsFoodIntakes(): void
    {
        $fixture = $this->loadReceiptFixture('receipt_normal');
        $http = $this->createOpenAiAndUsdaMock($fixture['items']);
        $client = $this->createLoggedInClient($http);

        $path = ReceiptTestImageFactory::sharpFixturePath();
        $file = new UploadedFile($path, 'receipt.jpg', ReceiptTestImageFactory::sharpMimeType(), null, true);
        $client->request('POST', '/food/input', ['_action' => 'receipt'], ['receipt' => $file]);

        $client->followRedirect();

        $client->submitForm('Save selected to intake', [
            'rows[0][include]' => '1',
            'rows[0][amount]' => '100',
            'rows[0][unit]' => 'gram',
            'rows[1][include]' => '1',
            'rows[1][amount]' => '1',
            'rows[1][unit]' => 'unit',
        ]);

        $response = $client->getResponse();
        self::assertTrue($response->isRedirect());
        self::assertStringStartsWith('/home', $response->headers->get('Location'));
        $client->followRedirect();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => self::USER_EMAIL]);
        self::assertNotNull($user);
        $rows = $em->getRepository(FoodIntake::class)->findBy(['user' => $user]);
        self::assertCount(2, $rows);
    }

    public function testEditIntakeRedirectsWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->request('GET', '/food/intake/999/edit');

        self::assertResponseRedirects('/login');
    }

    public function testDeleteIntakeRedirectsWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->request('POST', '/food/intake/999/delete');

        self::assertResponseRedirects('/login');
    }

    public function testDeleteIntakeRemovesRowAndRedirectsHome(): void
    {
        $client = $this->createLoggedInClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => self::USER_EMAIL]);
        self::assertNotNull($user);

        $intake = new FoodIntake();
        $intake->setUser($user);
        $intake->setSrcId(987654);
        $intake->setSrcName('Delete me');
        $intake->setAmount(10.0);
        $intake->setUnit(FoodIntakeUnit::Gram);
        $intake->setCreatedAt(new \DateTimeImmutable('2026-05-08 12:00:00'));
        $em->persist($intake);
        $em->flush();
        $id = (int) $intake->getId();

        $client->request('POST', '/food/intake/'.$id.'/delete', [
            '_return_view' => 'week',
            '_return_show_weekends' => '1',
        ]);

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/home', $location);
        self::assertStringContainsString('date=2026-05-08', $location);

        $em->clear();
        self::assertNull($em->getRepository(FoodIntake::class)->find($id));
    }

    public function testEditIntakeUpdatesCreatedAtAndRedirectsHome(): void
    {
        $client = $this->createLoggedInClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => self::USER_EMAIL]);
        self::assertNotNull($user);

        $intake = new FoodIntake();
        $intake->setUser($user);
        $intake->setSrcId(123456);
        $intake->setSrcName('Test food');
        $intake->setAmount(50.0);
        $intake->setUnit(FoodIntakeUnit::Gram);
        $intake->setCreatedAt(new \DateTimeImmutable('2026-05-10 09:15:00'));
        $em->persist($intake);
        $em->flush();
        $id = (int) $intake->getId();

        $client->request('GET', '/food/intake/'.$id.'/edit?view=week&date=2026-05-10&show_weekends=1');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Edit food entry');

        $client->request('POST', '/food/intake/'.$id.'/edit', [
            '_return_view' => 'week',
            '_return_show_weekends' => '1',
            'consumed_at' => '2026-05-12T14:30',
            'amount' => '100',
            'unit' => 'gram',
        ]);

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/home', $location);
        self::assertStringContainsString('date=2026-05-12', $location);
        self::assertStringContainsString('view=week', $location);

        $em->clear();
        $again = $em->getRepository(FoodIntake::class)->find($id);
        self::assertNotNull($again);
        self::assertSame('2026-05-12', $again->getCreatedAt()->format('Y-m-d'));
        self::assertSame('14:30', $again->getCreatedAt()->format('H:i'));
        self::assertSame(100.0, $again->getAmount());
    }

    private function createLoggedInClient(?MockHttpClient $http = null): KernelBrowser
    {
        $client = static::createClient();
        // KernelBrowser reboots the kernel after the first request by default, which drops container::set() mocks.
        $client->disableReboot();
        $this->removeFixtureUserIfExists();
        if ($http !== null) {
            $this->replaceHttpClientInPublicContainer($http, $client);
        }
        $this->seedUser();

        $client->request('POST', '/login', [
            'email' => self::USER_EMAIL,
            'password' => self::USER_PASSWORD,
        ]);
        $client->followRedirect();

        return $client;
    }

    /**
     * Use the kernel DI container's set() API (not TestContainer::set), which wires the HttpClientInterface
     * fileMap entry used by lazy factories.
     */
    private function replaceHttpClientInPublicContainer(MockHttpClient $mock, KernelBrowser $client): void
    {
        $public = $client->getKernel()->getContainer();
        if (!$public instanceof SymfonyContainer) {
            throw new \LogicException('Expected Symfony DI Container from kernel.');
        }

        $public->set(HttpClientInterface::class, $mock);
    }

    private function removeFixtureUserIfExists(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $existing = $em->getRepository(User::class)->findOneBy(['email' => self::USER_EMAIL]);
        if ($existing !== null) {
            $em->remove($existing);
            $em->flush();
        }
    }

    private function seedUser(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User();
        $user->setEmail(self::USER_EMAIL);
        $user->setFirstName('Fixture');
        $user->setLastName('User');
        $user->setPassword(password_hash(self::USER_PASSWORD, PASSWORD_DEFAULT));
        $user->setAge(25);
        $user->setWeight(70.0);
        $user->setHeight(175.0);
        $user->setGender('other');
        $em->persist($user);
        $em->flush();
    }

    /**
     * @return array{description?: string, items: list<array<string, mixed>>}
     */
    private function loadReceiptFixture(string $name): array
    {
        $path = dirname(__DIR__).'/Fixtures/receipts/'.$name.'.json';
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!\is_array($data) || !isset($data['items']) || !\is_array($data['items'])) {
            throw new \RuntimeException('Invalid fixture: '.$name);
        }

        return $data;
    }

    private function createManualUsdaMock(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url) {
            if (str_contains($url, 'api.nal.usda.gov') && str_contains($url, 'foods/search')) {
                $json = json_encode([
                    'foods' => [['fdcId' => 777002, 'description' => 'Bananas, raw']],
                ], JSON_THROW_ON_ERROR);

                return new MockResponse($json, ['http_code' => 200, 'headers' => ['content-type' => 'application/json']]);
            }

            return new MockResponse('not found', ['http_code' => 404]);
        });
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function createOpenAiAndUsdaMock(array $items, bool $readable = true): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url) use ($items, $readable) {
            if (str_contains($url, 'api.openai.com')) {
                $payload = json_encode(['readable' => $readable, 'items' => $readable ? $items : []], JSON_THROW_ON_ERROR);
                $body = json_encode([
                    'choices' => [['message' => ['content' => $payload]]],
                ], JSON_THROW_ON_ERROR);

                return new MockResponse($body, ['http_code' => 200, 'headers' => ['content-type' => 'application/json']]);
            }
            if (str_contains($url, 'api.nal.usda.gov') && str_contains($url, 'foods/search')) {
                $json = json_encode([
                    'foods' => [['fdcId' => 900001, 'description' => 'Fixture match food']],
                ], JSON_THROW_ON_ERROR);

                return new MockResponse($json, ['http_code' => 200, 'headers' => ['content-type' => 'application/json']]);
            }

            return new MockResponse('not found', ['http_code' => 404]);
        });
    }
    public function testManualSearchStoresMultipleFoodsInSession(): void
    {
        $http = $this->createMultiFoodUsdaMock();
        $client = $this->createLoggedInClient($http);

        $client->request('POST', '/food/input', [
            '_action' => 'manual',
            'food_name' => 'apple',
            'amount' => '100',
            'unit' => 'gram',
            'consumed_date' => '2026-05-18',
            '_return_view' => 'week',
            '_return_show_weekends' => '1',
        ]);

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/home', $location);

        $session = $client->getRequest()->getSession();
        $pendingFoods = $session->get('_pending_foods');

        self::assertIsArray($pendingFoods);
        self::assertArrayHasKey('foods', $pendingFoods);
        self::assertArrayHasKey('foodName', $pendingFoods);
        self::assertEquals('apple', $pendingFoods['foodName']);
        self::assertGreaterThan(0, count($pendingFoods['foods']));
        self::assertLessThanOrEqual(5, count($pendingFoods['foods']));
    }

    public function testConfirmFoodSelectionSavesChosenFood(): void
    {
        $http = $this->createMultiFoodUsdaMock();
        $client = $this->createLoggedInClient($http);

        // First search to populate session
        $client->request('POST', '/food/input', [
            '_action' => 'manual',
            'food_name' => 'banana',
            'amount' => '150',
            'unit' => 'gram',
            'consumed_date' => '2026-05-18',
            '_return_view' => 'week',
            '_return_show_weekends' => '1',
        ]);

        $firstSession = $client->getRequest()->getSession();
        $pendingFoods = $firstSession->get('_pending_foods');
        $firstFoodId = $pendingFoods['foods'][0]['fdcId'];

        $client->request('POST', '/food/input/confirm', [
            'selected_fdc_id' => (string) $firstFoodId,
        ]);

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/home', $location);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => self::USER_EMAIL]);
        $intakes = $em->getRepository(FoodIntake::class)->findBy(['user' => $user]);
        self::assertGreaterThan(0, count($intakes));
    }

    public function testConfirmWithoutSelectionShowsError(): void
    {
        $client = $this->createLoggedInClient();

        $client->request('POST', '/food/input/confirm', [
            'selected_fdc_id' => '0',
        ]);

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/home', $location);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'No pending food selection');
    }

    public function testSearchWithNoDuplicateDescriptions(): void
    {
        $http = $this->createDuplicateFoodsMock();
        $client = $this->createLoggedInClient($http);

        $client->request('POST', '/food/input', [
            '_action' => 'manual',
            'food_name' => 'yogurt',
            'amount' => '100',
            'unit' => 'gram',
            'consumed_date' => '2026-05-18',
            '_return_view' => 'week',
            '_return_show_weekends' => '1',
        ]);

        $session = $client->getRequest()->getSession();
        $pendingFoods = $session->get('_pending_foods');
        $foods = $pendingFoods['foods'];

        //check no duplicate descriptions (case insensitive)
        $descriptions = array_map(fn($f) => strtolower($f['description']), $foods);
        $unique = array_unique($descriptions);

        self::assertCount(count($unique), $descriptions, 'Should have no duplicate food descriptions');
    }

    private function createMultiFoodUsdaMock(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url) {
            if (str_contains($url, 'api.nal.usda.gov') && str_contains($url, 'foods/search')) {
                $json = json_encode([
                    'foods' => [
                        ['fdcId' => 111001, 'description' => 'Apple, raw'],
                        ['fdcId' => 111002, 'description' => 'Apple, cooked'],
                        ['fdcId' => 111003, 'description' => 'Apple juice'],
                        ['fdcId' => 111004, 'description' => 'Apple pie'],
                        ['fdcId' => 111005, 'description' => 'Apple sauce'],
                    ],
                ], JSON_THROW_ON_ERROR);

                return new MockResponse($json, ['http_code' => 200]);
            }
            if (str_contains($url, 'api.nal.usda.gov') && str_contains($url, '/food/')) {
                $json = json_encode([
                    'description' => 'Apple, raw',
                ], JSON_THROW_ON_ERROR);

                return new MockResponse($json, ['http_code' => 200]);
            }

            return new MockResponse('not found', ['http_code' => 404]);
        });
    }

    private function createDuplicateFoodsMock(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url) {
            if (str_contains($url, 'api.nal.usda.gov') && str_contains($url, 'foods/search')) {
                $json = json_encode([
                    'foods' => [
                        ['fdcId' => 222001, 'description' => 'YOGURT'],
                        ['fdcId' => 222002, 'description' => 'YOGURT'], //duplicate
                        ['fdcId' => 222003, 'description' => 'Yogurt, plain'],
                        ['fdcId' => 222004, 'description' => 'yogurt'], //duplicate (case)
                        ['fdcId' => 222005, 'description' => 'Greek yogurt'],
                        ['fdcId' => 222006, 'description' => 'Tofu yogurt'],
                    ],
                ], JSON_THROW_ON_ERROR);

                return new MockResponse($json, ['http_code' => 200]);
            }

            return new MockResponse('not found', ['http_code' => 404]);
        });
    }
}
