<?php

namespace App\Service;

use App\Entity\FoodIntake;
use App\Enum\FoodIntakeUnit;

/**
 * Sums consumed nutrients for a set of FoodIntake rows.
 *
 * We map USDA nutrient names/units onto our canonical keys (same as DailyRequirementsCalculator).
 */
final class NutrientProgressCalculator
{
    public function __construct(
        private readonly FoodNutrientProviderInterface $foodNutrients,
    ) {
    }

    /**
     * @param iterable<FoodIntake> $intakes
     * @param array<string, array{value: float|int, unit: string}> $recommendedMicros
     *
     * @return array{
     *   consumed: array<string, array{value: float, unit: string}>,
     *   warnings: list<string>
     * }
     */
    public function consumedMicronutrients(iterable $intakes, array $recommendedMicros): array
    {
        // Initialize to 0 with the same units as the recommendations.
        $consumed = [];
        foreach ($recommendedMicros as $key => $rec) {
            $consumed[$key] = ['value' => 0.0, 'unit' => $rec['unit']];
        }

        $warnings = [];

        foreach ($intakes as $intake) {
            $fdcId = $intake->getSrcId();
            if ($fdcId === null) {
                continue;
            }

            $grams = $this->intakeToGrams($intake);
            if ($grams === null) {
                $warnings[] = 'Some intakes were skipped because unit amount could not be converted to grams.';
                continue;
            }

            $rows = $this->foodNutrients->getFoodNutrients($fdcId);
            if ($rows === []) {
                continue;
            }

            // USDA amounts are typically per 100g. Scale linearly.
            $scale = $grams / 100.0;

            foreach ($rows as $row) {
                $mappedKey = $this->mapUsdaNameToKey($row['name']);
                if ($mappedKey === null || !isset($consumed[$mappedKey])) {
                    continue;
                }

                $targetUnit = $consumed[$mappedKey]['unit'];
                $val = $this->convertUnit($row['value'] * $scale, $row['unit'], $targetUnit);
                if ($val === null) {
                    continue;
                }

                $consumed[$mappedKey]['value'] += $val;
            }
        }

        // Round for display stability.
        foreach ($consumed as $k => $v) {
            $consumed[$k]['value'] = $this->roundForUnit($v['value'], $v['unit']);
        }

        $warnings = array_values(array_unique($warnings));

        return [
            'consumed' => $consumed,
            'warnings' => $warnings,
        ];
    }

    private function intakeToGrams(FoodIntake $intake): ?float
    {
        $amount = $intake->getAmount();
        $unit = $intake->getUnit();
        if ($amount === null || $unit === null || $amount <= 0) {
            return null;
        }

        if ($unit === FoodIntakeUnit::Gram) {
            return $amount;
        }

        // "Unit" is ambiguous. We choose a simple assumption so the progress bars can still work.
        // Later we can improve this by capturing serving size or using FDC's "servingSize".
        return $amount * 100.0;
    }

    private function mapUsdaNameToKey(string $usdaName): ?string
    {
        $n = strtolower(trim($usdaName));

        return match (true) {
            str_contains($n, 'vitamin a') => 'vitamin_a',
            str_contains($n, 'vitamin c') => 'vitamin_c',
            str_contains($n, 'vitamin d') => 'vitamin_d',
            str_contains($n, 'vitamin e') => 'vitamin_e',
            str_contains($n, 'vitamin k') => 'vitamin_k',
            str_contains($n, 'thiamin') => 'thiamin',
            str_contains($n, 'riboflavin') => 'riboflavin',
            str_contains($n, 'niacin') => 'niacin',
            str_contains($n, 'vitamin b-6') || str_contains($n, 'vitamin b6') => 'vitamin_b6',
            str_contains($n, 'folate') => 'folate',
            str_contains($n, 'vitamin b-12') || str_contains($n, 'vitamin b12') => 'vitamin_b12',
            str_contains($n, 'pantothenic') => 'pantothenic_acid',
            str_contains($n, 'biotin') => 'biotin',
            str_contains($n, 'choline') => 'choline',
            str_contains($n, 'calcium') => 'calcium',
            str_contains($n, 'chloride') => 'chloride',
            str_contains($n, 'chromium') => 'chromium',
            str_contains($n, 'fluoride') => 'fluoride',
            str_contains($n, 'iron') => 'iron',
            str_contains($n, 'magnesium') => 'magnesium',
            str_contains($n, 'manganese') => 'manganese',
            str_contains($n, 'potassium') => 'potassium',
            $n === 'zinc' || str_contains($n, 'zinc') => 'zinc',
            default => null,
        };
    }

    private function convertUnit(float $value, string $fromUnit, string $toUnit): ?float
    {
        $from = strtolower(trim($fromUnit));
        $to = strtolower(trim($toUnit));

        // Normalize common USDA unit spellings.
        $from = match ($from) {
            'ug' => 'µg',
            'mcg' => 'µg',
            default => $from,
        };
        $to = match ($to) {
            'ug' => 'µg',
            'mcg' => 'µg',
            default => $to,
        };

        if ($from === $to) {
            return $value;
        }

        // µg <-> mg
        if ($from === 'µg' && $to === 'mg') {
            return $value / 1000.0;
        }
        if ($from === 'mg' && $to === 'µg') {
            return $value * 1000.0;
        }

        // mg <-> g
        if ($from === 'mg' && $to === 'g') {
            return $value / 1000.0;
        }
        if ($from === 'g' && $to === 'mg') {
            return $value * 1000.0;
        }

        // µg <-> g (rare)
        if ($from === 'µg' && $to === 'g') {
            return $value / 1_000_000.0;
        }
        if ($from === 'g' && $to === 'µg') {
            return $value * 1_000_000.0;
        }

        return null;
    }

    private function roundForUnit(float $value, string $unit): float
    {
        $u = strtolower(trim($unit));
        if ($u === 'g') {
            return round($value, 2);
        }
        if ($u === 'mg') {
            return round($value, 1);
        }
        if ($u === 'µg' || $u === 'mcg') {
            return round($value, 0);
        }

        return round($value, 2);
    }
}

