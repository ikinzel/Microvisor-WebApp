<?php

namespace App\Tests\Integration;

use App\Service\UsdaFoodDataCentralClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

final class RealApiTest extends TestCase
{
    private string $usdaApiKey;
    private string $openaiApiKey;

    protected function setUp(): void
    {
        $this->usdaApiKey = $_ENV['USDA_FDC_API_KEY'] ?? 'CYyxAQdqszkQVrjwVDYyWzLUTAVCPqn5y2qiXKxz';
        $this->openaiApiKey = $_ENV['OPENAI_API_KEY'] ?? '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';

        if (empty($this->usdaApiKey) || empty($this->openaiApiKey)) {
            $this->markTestSkipped('API keys not configured...');
        }
        if ($this->usdaApiKey === 'test' || $this->openaiApiKey === 'test') {
            $this->markTestSkipped('Using test/fake API keys. Set real keys to run real API tests.');
        }
    }

    public function testUsdaApiReturnsValidFoodData(): void
    {
        $httpClient = HttpClient::create();
        $client = new UsdaFoodDataCentralClient($httpClient, $this->usdaApiKey);
        $result = $client->searchFirstFood('banana');

        $this->assertNotNull($result, 'USDA API should return food data for "banana"');
        $this->assertArrayHasKey('fdcId', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertStringContainsString('banana', strtolower($result['description']));
    }

    public function testUsdaApiReturnsNutrients(): void
    {
        $httpClient = HttpClient::create();
        $client = new UsdaFoodDataCentralClient($httpClient, $this->usdaApiKey);
        $result = $client->searchFirstFood('apple');

        $this->assertNotNull($result);

        $fdcId = $result['fdcId'];
        $response = $httpClient->request('GET', 'https://api.nal.usda.gov/fdc/v1/food/' . $fdcId, [
            'query' => ['api_key' => $this->usdaApiKey]
        ]);

        $data = $response->toArray();

        $this->assertArrayHasKey('foodNutrients', $data);
        $this->assertNotEmpty($data['foodNutrients']);
        $nutrient = $data['foodNutrients'][0];
        $this->assertArrayHasKey('nutrient', $nutrient);
        $this->assertArrayHasKey('amount', $nutrient);
    }

    public function testUsdaApiHandles404(): void
    {
        $httpClient = HttpClient::create();

        $response = $httpClient->request('GET', 'https://api.nal.usda.gov/fdc/v1/food/999999999', [
            'query' => ['api_key' => $this->usdaApiKey]
        ]);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUsdaApiAuthenticationWorks(): void
    {
        $httpClient = HttpClient::create();

        // Test with invalid API key
        $response = $httpClient->request('GET', 'https://api.nal.usda.gov/fdc/v1/food/123', [
            'query' => ['api_key' => 'invalid-key-12345']
        ]);

        $this->assertGreaterThanOrEqual(400, $response->getStatusCode());
    }

    public function testOpenaiApiIsReachable(): void
    {
        $httpClient = HttpClient::create();
        try {
            $response = $httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'model' => 'gpt-4-vision-preview',
                    'messages' => [
                        ['role' => 'user', 'content' => 'Hello']
                    ]
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $this->assertNotEquals(401, $statusCode, 'API key is invalid');
            $this->assertNotEquals(403, $statusCode, 'API key is forbidden');
        } catch (\Exception $e) {
            $this->fail('OpenAI API should be reachable: ' . $e->getMessage());
        }
    }

    public function testOpenaiApiAuthenticationWorks(): void
    {
        $httpClient = HttpClient::create();

        $response = $httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer invalid-key-12345',
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'model' => 'gpt-4-vision-preview',
                'messages' => [['role' => 'user', 'content' => 'Test']]
            ]
        ]);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUsdaSearchIntegration(): void
    {
        $httpClient = HttpClient::create();
        $client = new UsdaFoodDataCentralClient($httpClient, $this->usdaApiKey);
        $foods = ['banana', 'apple', 'carrot', 'rice', 'chicken'];

        foreach ($foods as $food) {
            $result = $client->searchFirstFood($food);
            $this->assertNotNull($result, "Should find food: $food");
            $this->assertIsInt($result['fdcId']);
            $this->assertIsString($result['description']);
        }
    }

    public function testUsdaApiResponseFormat(): void
    {
        $httpClient = HttpClient::create();
        $client = new UsdaFoodDataCentralClient($httpClient, $this->usdaApiKey);

        $result = $client->searchFirstFood('banana');

        // Verify response has expected structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('fdcId', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertIsInt($result['fdcId']);
        $this->assertIsString($result['description']);
        $this->assertNotEmpty($result['description']);
    }
    public function testUsdaApiResponseTime(): void
    {
        $httpClient = HttpClient::create();
        $client = new UsdaFoodDataCentralClient($httpClient, $this->usdaApiKey);

        $start = microtime(true);
        $client->searchFirstFood('banana');
        $duration = microtime(true) - $start;
        $this->assertLessThan(5, $duration, "USDA API response too slow: {$duration}s");
    }

    public function testOpenaiApiResponseTime(): void
    {
        $httpClient = HttpClient::create();
        $start = microtime(true);
        try {
            $response = $httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'model' => 'gpt-4-vision-preview',
                    'messages' => [
                        ['role' => 'user', 'content' => 'Hello']
                    ]
                ]
            ]);
            $statusCode = $response->getStatusCode();
        } catch (\Exception $e) {
        }
        $duration = microtime(true) - $start;
        $this->assertLessThan(10, $duration, "OpenAI API response too slow: {$duration}s");
    }
}
