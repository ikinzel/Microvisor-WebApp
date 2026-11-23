<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/*
 * FoodNutrientCache caches USDA nutrient data for efficient retrieval.
 *
 * This service caches nutrient information for individual foods to avoid repeated
 * API calls to the USDA FoodData Central service. Nutrient data for a specific food
 * ID is stable and rarely changes, making it ideal for caching.
 *
 * Cache duration: 24 hours.
 *
 * Used by NutrientProgressCalculator to efficiently retrieve nutrient composition
 * for all foods the user has logged.
 */
final class FoodNutrientCache implements FoodNutrientProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly UsdaFoodDataCentralClient $fdc,
    ) {
    }

    /**
     * @return list<array{name: string, unit: string, value: float}>
     */
    public function getFoodNutrients(int $fdcId): array
    {
        return $this->cache->get('fdc_food_nutrients_'.$fdcId, function (ItemInterface $item) use ($fdcId): array {
            // 24h cache: USDA nutrient data for a given food id is stable enough.
            $item->expiresAfter(24 * 60 * 60);

            return $this->fdc->getFoodNutrients($fdcId);
        });
    }
}

