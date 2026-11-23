<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * USDA FoodData Central API — see https://fdc.nal.usda.gov/api-guide
 */
final class UsdaFoodDataCentralClient
{
    private const SEARCH_URL = 'https://api.nal.usda.gov/fdc/v1/foods/search';

    private const FOOD_URL = 'https://api.nal.usda.gov/fdc/v1/food';

    /** Public FoodData Central UI (food record) — hash route used by the official site. */
    public const PUBLIC_FOOD_DETAILS_URL_TEMPLATE = 'https://fdc.nal.usda.gov/fdc-app.html#/food-details/%d';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'USDA_FDC_API_KEY')]
        private readonly string $apiKey,
    ) {
    }

    /**
     * Returns the first matching food FDC id and description, or null if none.
     *
     * @return array{fdcId: int, description: string}|null
     */
    public function searchFirstFood(string $query): ?array
    {
        $q = trim($query);
        if ($q === '') {
            return null;
        }

        if ($this->apiKey === '') {
            throw new \RuntimeException('USDA_FDC_API_KEY is not set. Add it to your .env or .env.local.');
        }

        $response = $this->httpClient->request('GET', self::SEARCH_URL, [
            'query' => [
                'api_key' => $this->apiKey,
                'query' => $q,
                'pageSize' => 5,
                'pageNumber' => 1,
            ],
            'timeout' => 20,
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('FoodData Central API error: HTTP '.$status);
        }

        $data = $response->toArray();
        $foods = $data['foods'] ?? [];
        if (!\is_array($foods) || $foods === []) {
            return null;
        }

        $first = $foods[0];
        if (!\is_array($first)) {
            return null;
        }

        $fdcId = $first['fdcId'] ?? null;
        if (!\is_int($fdcId) && !\is_float($fdcId)) {
            return null;
        }

        $description = $first['description'] ?? '';
        if (!\is_string($description)) {
            $description = '';
        }

        return [
            'fdcId' => (int) $fdcId,
            'description' => $description,
        ];
    }

    /**
     * GET /food/{fdcId} — description for display and backfill.
     *
     * @see https://fdc.nal.usda.gov/api-guide
     */
    public function getFoodDescription(int $fdcId): ?string
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('USDA_FDC_API_KEY is not set. Add it to your .env or .env.local.');
        }

        $response = $this->httpClient->request('GET', self::FOOD_URL.'/'.$fdcId, [
            'query' => [
                'api_key' => $this->apiKey,
            ],
            'timeout' => 20,
        ]);

        $status = $response->getStatusCode();
        if ($status === 404) {
            return null;
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('FoodData Central API error: HTTP '.$status);
        }

        $data = $response->toArray();
        $description = $data['description'] ?? null;
        if (!\is_string($description)) {
            return null;
        }

        $description = trim($description);

        return $description === '' ? null : $description;
    }

    /**
     * for progress bars --turns an array of food nutrients with their name, unit, and value
     */
    public function getFoodNutrients(int $fdcId): array
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('USDA_FDC_API_KEY is not set. Add it to your .env or .env.local.');
        }

        $response = $this->httpClient->request('GET', self::FOOD_URL.'/'.$fdcId, [
            'query' => [
                'api_key' => $this->apiKey,
            ],
            'timeout' => 30,
        ]);

        $status = $response->getStatusCode();
        if ($status === 404) {
            return [];
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('FoodData Central API error: HTTP '.$status);
        }

        $data = $response->toArray(false);
        $foodNutrients = $data['foodNutrients'] ?? [];
        if (!\is_array($foodNutrients)) {
            return [];
        }

        $out = [];
        foreach ($foodNutrients as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $nutr = $row['nutrient'] ?? null;
            if (!\is_array($nutr)) {
                continue;
            }
            $name = $nutr['name'] ?? null;
            $unit = $nutr['unitName'] ?? null;
            $value = $row['amount'] ?? null;
            if (!\is_string($name) || !\is_string($unit) || (!\is_int($value) && !\is_float($value))) {
                continue;
            }
            $name = trim($name);
            $unit = trim($unit);
            if ($name === '' || $unit === '') {
                continue;
            }

            $out[] = [
                'name' => $name,
                'unit' => $unit,
                'value' => (float) $value,
            ];
        }

        return $out;
    }

    public static function publicFoodDetailsUrl(int $fdcId): string
    {
        return sprintf(self::PUBLIC_FOOD_DETAILS_URL_TEMPLATE, $fdcId);
    }

    public function searchFoods(string $query): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }

        if ($this->apiKey === '') {
            throw new \RuntimeException('USDA_FDC_API_KEY is not set. Add it to your .env or .env.local.');
        }

        $response = $this->httpClient->request('GET', self::SEARCH_URL, [
            'query' => [
                'api_key' => $this->apiKey,
                'query' => $q,
                'pageSize' => 15,
                'pageNumber' => 1,
            ],
            'timeout' => 20,
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('FoodData Central API error: HTTP '.$status);
        }

        $data = $response->toArray();
        $foods = $data['foods'] ?? [];
        if (!\is_array($foods)) {
            return [];
        }

        $seen = [];
        $results = [];

        foreach ($foods as $food) {
            if (!\is_array($food)) {
                continue;
            }

            $fdcId = $food['fdcId'] ?? null;
            if (!\is_int($fdcId) && !\is_float($fdcId)) {
                continue;
            }

            $description = $food['description'] ?? '';
            if (!\is_string($description)) {
                $description = '';
            }

            $descLower = strtolower(trim($description));
            if (isset($seen[$descLower])) {
                continue;
            }
            $seen[$descLower] = true;

            $results[] = [
                'fdcId' => (int) $fdcId,
                'description' => $description,
            ];

            if (count($results) >= 5) {
                break;
            }
        }

        return $results;
    }
}
