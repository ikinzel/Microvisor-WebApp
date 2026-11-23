<?php

namespace App\Service;

interface FoodNutrientProviderInterface
{
    /**
     * @return list<array{name: string, unit: string, value: float}>
     */
    public function getFoodNutrients(int $fdcId): array;
}
