<?php

namespace App\Service;

use App\Entity\User;

/*
 * DailyRequirementsCalculator computes personalized daily nutritional targets.
 *
 * This service calculates recommended daily intake targets for macronutrients
 * and micronutrients (vitamins and minerals) based on the user's age, gender, weight, and height.
 * It uses established nutritional formulas and government dietary guidelines (DRI - Dietary Reference Intakes)
 * to generate personalized targets.
 *
 * The calculator also applies user defined goal overrides if they have customized their targets.
 * All targets are returned as an array suitable for display on the home dashboard.
 *
 */

final class DailyRequirementsCalculator
{
    /*
     * Calculates complete daily nutritional targets for a user.
     *
     * This method combines default targets based on age, gender, weight, height with any
     * user customized goal overrides. The result is a comprehensive set of daily targets
     * including macronutrients and micronutrients
     *
     * @param User $user The user entity containing age, gender, weight, height, and custom goals
     * @return array Array with keys: 'user', 'macros', 'micronutrients'
     */
    public function calculate(User $user): array
    {
        $result = $this->calculateDefaults($user);

        $overrides = $this->goalOverridesByKey($user);
        $result['macros'] = $this->applyMacroOverrides($result['macros'], $overrides);
        $result['micronutrients'] = $this->applyMicronutrientOverrides($result['micronutrients'], $overrides);

        return $result;
    }

    /*
     * Calculates default nutritional targets without any user customizations.
     *
     * This method performs the core calculation:
     * 1. Extracts user demographics
     * 2. Calculates BMR using the Mifflin-St Jeor equation
     * 3. Applies a sedentary activity factor (1.2) to get Total Daily Energy Expenditure
     * 4. Breaks down daily calories into macronutrient targets
     * 5. Sets micronutrient targets based on age and gender using DRI guidelines
     *
     * @param User $user The user entity with demographic data
     * @return array Array containing 'user' demographics, 'macros', and 'micronutrients' keys
     */
    public function calculateDefaults(User $user): array
    {
        $age = (int) ($user->getAge() ?? 0);  //NFS: conditional operator
        $gender = strtolower((string) ($user->getGender() ?? ''));
        $isFemale = $gender === 'female';

        $weightKg = (float) ($user->getWeight() ?? 0.0);
        $heightCm = (float) ($user->getHeight() ?? 0.0);

        // Maifflin–St Jeor BMR equation. A sedentary activity factor (1.2) is used
        //tdee refers to total daily energy expenditure
        $bmr = ($isFemale ? -161 : 5) + (10 * $weightKg) + (6.25 * $heightCm) - (5 * $age);
        $tdee = max(0.0, $bmr * 1.2);  //NTS: doesn't allow the tdee to be negative

        $macros = $this->macrosFromCalories($tdee);
        $micros = $this->micronutrients($age, $isFemale);

        return [
            'user' => [
                'age' => $age,
                'gender' => $gender,
                'weightKg' => $weightKg,
                'heightCm' => $heightCm,
            ],
            'macros' => $macros,
            'micronutrients' => $micros,
        ];
    }

    /*
     * Calculates macronutrient targets from daily calorie expenditure.
     *
     * Uses standard macronutrient distribution ranges from dietary guidelines
     *
     * @param float $calories Daily calorie expenditure (TDEE)
     * @return array Array with 'carbs_g', 'fat_g', 'protein_g', 'fiber_g' targets
     */
    private function macrosFromCalories(float $calories): array
    {

        // - Carbs: 45–65% of kcal / 4 kcal per g
        // - Fat: 20–35% of kcal / 9 kcal per g
        // - Protein: 15% of kcal / 4 kcal per g
        // - Fiber: 14g per 1000 kcal
        $minCarbs = (int) round(($calories * 0.45) / 4);
        $maxCarbs = (int) round(($calories * 0.65) / 4);

        $minFat = (int) round(($calories * 0.20) / 9);
        $maxFat = (int) round(($calories * 0.35) / 9);

        $protein = (int) round(($calories * 0.15) / 4);
        $fiber = (int) round(($calories / 1000.0) * 14);

        return [
            'carbs_g' => ['min' => max(0, $minCarbs), 'max' => max(0, $maxCarbs)],
            'fat_g' => ['min' => max(0, $minFat), 'max' => max(0, $maxFat)],
            'protein_g' => max(0, $protein),
            'fiber_g' => max(0, $fiber),
        ];
    }

    /*
     * Retrieves user defined goal overrides as a key value array.
     * Queries the user's DailyGoals relationship to find any customized targets.
     * Returns a dictionary mapping goal keys to values.
     *
     * @param User $user The user entity
     * @return array<string, float> Map of goal key to override value
     */
    private function goalOverridesByKey(User $user): array
    {
        $overrides = [];

        foreach ($user->getDailyGoals() as $goal) {
            $key = $goal->getGoalKey();
            $value = $goal->getValue();
            if ($key !== null && $value !== null) {
                $overrides[$key] = $value;
            }
        }

        return $overrides;
    }

    /*
     * Applies user defined macro overrides to default macronutrient targets.
     *
     * Users can customize:
     * - Carbs minimum/maximum
     * - Fat minimum/maximum
     * - Fiber target
     * - Protein target
     *
     * @param array $macros The default macronutrient targets
     * @param array<string, float> $overrides User-defined goal overrides
     * @return array Updated macronutrient targets with overrides applied
     */
    private function applyMacroOverrides(array $macros, array $overrides): array
    {
        foreach (['carbs_g', 'fat_g'] as $rangeKey) {
            foreach (['min', 'max'] as $bound) {
                $overrideKey = $rangeKey.'_'.$bound;
                if (isset($overrides[$overrideKey]) && is_numeric($overrides[$overrideKey])) {
                    $macros[$rangeKey][$bound] = max(0, (int) round((float) $overrides[$overrideKey]));
                }
            }
        }

        foreach (['fiber_g', 'protein_g'] as $singleKey) {
            if (isset($overrides[$singleKey]) && is_numeric($overrides[$singleKey])) {
                $macros[$singleKey] = max(0, (int) round((float) $overrides[$singleKey]));
            }
        }

        return $macros;
    }

    /*
     * Applies user defined micronutrient overrides to default targets.
     *
     * @param array $micros The default micronutrient targets
     * @param array<string, float> $overrides User-defined goal overrides
     * @return array Updated micronutrient targets with overrides applied
     */
    private function applyMicronutrientOverrides(array $micros, array $overrides): array
    {
        foreach ($micros as $key => $item) {
            if (isset($overrides[$key]) && is_numeric($overrides[$key])) {
                $micros[$key]['value'] = max(0, (float) $overrides[$key]);
            }
        }

        return $micros;
    }



    /*
     * Sets micronutrient targets based on age and gender.
     *
     * Uses Dietary Reference Intake (DRI) guidelines from U.S. National Institutes of Health.
     * Targets vary by:
     * - Age
     * - Gender
     * - Special cases handled by age dependent helper methods
     *
     * @param int $age User's age in years
     * @param bool $isFemale Whether user is female
     * @return array<string, array{value: float|int, unit: string}> Micronutrient targets with units
     */
    private function micronutrients(int $age, bool $isFemale): array
    {

        $out = [
            'vitamin_a' => ['value' => $isFemale ? 700 : 900, 'unit' => 'µg'],
            'vitamin_c' => ['value' => $isFemale ? 75 : 90, 'unit' => 'mg'],
            'vitamin_d' => ['value' => 15, 'unit' => 'µg'],
            'vitamin_e' => ['value' => 15, 'unit' => 'mg'],
            'vitamin_k' => ['value' => $isFemale ? 90 : 120, 'unit' => 'µg'],
            'thiamin' => ['value' => $isFemale ? 1.1 : 1.2, 'unit' => 'mg'],
            'vitamin_b12' => ['value' => 2.4, 'unit' => 'µg'],
            'riboflavin' => ['value' => $isFemale ? 1.1 : 1.3, 'unit' => 'mg'],
            'folate' => ['value' => 400, 'unit' => 'µg'],
            'niacin' => ['value' => $isFemale ? 14 : 16, 'unit' => 'mg'],
            'choline' => ['value' => $isFemale ? 425 : 550, 'unit' => 'mg'],
            'pantothenic_acid' => ['value' => 5, 'unit' => 'mg'],
            'biotin' => ['value' => 30, 'unit' => 'µg'],
        ];

        $out['vitamin_b6'] = $this->vitaminB6($age, $isFemale);

        //minerals
        $out['fluoride'] = ['value' => $isFemale ? 3 : 4, 'unit' => 'mg'];
        $out['manganese'] = ['value' => $isFemale ? 1.8 : 2.3, 'unit' => 'mg'];
        $out['potassium'] = ['value' => $isFemale ? 2.6 : 3.4, 'unit' => 'g'];
        $out['zinc'] = ['value' => $isFemale ? 8 : 11, 'unit' => 'mg'];

        // Age based minerals
        $out['calcium'] = $this->calcium($age, $isFemale);
        $out['chloride'] = $this->chloride($age);
        $out['chromium'] = $this->chromium($age, $isFemale);
        $out['iron'] = $this->iron($age, $isFemale);
        $out['magnesium'] = $this->magnesium($age, $isFemale);

        return $out;
    }

    /*
     * Calculates Vitamin B6 target based on age and gender.
     *
     * Vitamin B6 requirements change significantly with age.
     *
     * @param int $age User's age in years
     * @param bool $isFemale Whether user is female
     * @return array{value: float|int, unit: string} Vitamin B6 target with unit
     */
    private function vitaminB6(int $age, bool $isFemale): array
    {
        $val = match (true) {
            $age >= 1 && $age <= 3 => 0.5,
            $age >= 4 && $age <= 8 => 0.6,
            $age >= 9 && $age <= 13 => 1.0,
            $age >= 14 && $age <= 18 => $isFemale ? 1.2 : 1.3,
            $age >= 19 && $age <= 50 => 1.3,
            $age >= 51 => $isFemale ? 1.5 : 1.7,
            default => 0.0,
        };

        return ['value' => $val, 'unit' => 'mg'];
    }


    /*
     * Calculates Calcium target based on age and gender.
     *
     * Calcium requirements vary significantly with age.
     * Teenage girls and post menopausal women have higher requirements.
     *
     * @param int $age User's age in years
     * @param bool $isFemale Whether user is female
     * @return array{value: int, unit: string} Calcium target with unit
     */
    private function calcium(int $age, bool $isFemale): array
    {
        $val = match (true) {
            $age <= 0 => 200,
            $age === 1 => 700,
            $age >= 2 && $age <= 3 => 700,
            $age >= 4 && $age <= 8 => 1000,
            $age >= 9 && $age <= 13 => $isFemale ? 800 : 1000,
            $age >= 14 && $age <= 18 => 1300,
            $age >= 19 && $age <= 50 => 1000,
            $age >= 51 && $age <= 70 => $isFemale ? 1200 : 1000,
            default => 1200,
        };

        return ['value' => $val, 'unit' => 'mg'];
    }

    /*
     * Calculates Chloride target based on age.
     * Chloride requirements increase with age.
     *
     * @param int $age User's age in years
     * @return array{value: float|int, unit: string} Chloride target with unit
     */
    private function chloride(int $age): array
    {
        $val = match (true) {
            $age <= 0 => 0.0,
            $age >= 1 && $age <= 3 => 1.5,
            $age >= 4 && $age <= 8 => 1.9,
            $age >= 9 && $age <= 50 => 2.3,
            $age >= 51 && $age <= 70 => 2.0,
            default => 1.8,
        };

        return ['value' => $val, 'unit' => 'g'];
    }


    /*
     * Calculates Chromium target based on age and gender.
     *
     * Chromium targets only apply to adults and vary by gender.
     *
     * @param int $age User's age in years
     * @param bool $isFemale Whether user is female
     * @return array{value: int, unit: string} Chromium target with unit
     */
    private function chromium(int $age, bool $isFemale): array
    {
        $val = match (true) {
            $age >= 19 && $age <= 50 => $isFemale ? 25 : 35,
            $age > 50 => $isFemale ? 20 : 30,
            default => 0,
        };

        return ['value' => $val, 'unit' => 'mcg'];
    }

    /*
     * Calculates Iron target based on age and gender.
     *
     * Iron requirements are especially different for females of reproductive age.
     * Menstruating women have higher iron needs due to periods.
     *
     * @param int $age User's age in years
     * @param bool $isFemale Whether user is female
     * @return array{value: float|int, unit: string} Iron target with unit
     */
    private function iron(int $age, bool $isFemale): array
    {
        $val = match (true) {
            $age >= 1 && $age <= 3 => 9,
            $age >= 4 && $age <= 8 => 10,
            $age >= 9 && $age <= 13 => 8,
            $age >= 14 && $age <= 18 => $isFemale ? 15 : 11,
            $age >= 19 && $age <= 49 => $isFemale ? 14.8 : 8.7,
            $age >= 50 => 8.7,
            default => 0.0,
        };

        return ['value' => $val, 'unit' => 'mg'];
    }

    /*
     * Calculates Magnesium target based on age and gender.
     * Magnesium requirements increase with age and vary slightly by gender.
     *
     * @param int $age User's age in years
     * @param bool $isFemale Whether user is female
     * @return array{value: int, unit: string} Magnesium target with unit
     */
    private function magnesium(int $age, bool $isFemale): array
    {
        $val = match (true) {
            $age >= 1 && $age < 3 => 170,
            $age >= 3 && $age < 10 => 230,
            $age >= 10 && $age < 18 => $isFemale ? 250 : 300,
            $age >= 18 => $isFemale ? 300 : 350,
            default => 0,
        };

        return ['value' => $val, 'unit' => 'mg'];
    }
}

