<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\FoodIntakeRepository;
use App\Service\DailyRequirementsCalculator;
use App\Service\NutrientProgressCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * ProfileProgressApiController provides real-time nutrition progress data via API.
 *
 * This API endpoint powers the nutrition progress bars on the dashboard.
 * When the user views the dashboard or switches between daily/weekly views,
 * JavaScript calls this endpoint to fetch nutrition progress data .
 * Response: JSON with consumed nutrients, targets, and percentages
 */
final class ProfileProgressApiController extends AbstractController
{
    #[Route('/api/profile/progress', name: 'api_profile_progress', methods: ['GET'])]
    public function index(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        FoodIntakeRepository $foodIntakeRepository,
        DailyRequirementsCalculator $dailyReqs,
        NutrientProgressCalculator $progress,
    ): JsonResponse {
        $userId = $session->get('user_id');
        if (!$userId) {
            return new JsonResponse(['error' => 'unauthenticated'], 401);
        }

        $user = $entityManager->getRepository(User::class)->find((int) $userId);
        if (!$user) {
            $session->remove('user_id');
            $session->remove('user_name');

            return new JsonResponse(['error' => 'unauthenticated'], 401);
        }

        //AI helped me with this code.
        $dateQ = $request->query->get('date');
        $day = $this->parseDate($dateQ) ?? (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        $range = (string) $request->query->get('range', 'day');
        if (!\in_array($range, ['day', 'week'], true)) {
            $range = 'day';
        }

        if ($range === 'week') {
            $start = $this->weekStart($day);
            $end = $start->modify('+7 days');
            $targetDays = 7;
        } else {
            $start = $day;
            $end = $day->modify('+1 day');
            $targetDays = 1;
        }

        //start and end dates depend on the range selected above.
        // This retrieves food entries belonging to this user between the start and end dates.
        $intakes = $foodIntakeRepository->findByUserCreatedBetween($user, $start, $end);

        $recs = $dailyReqs->calculate($user);
        $recommendedMicros = $this->multiplyRecommendations($recs['micronutrients'], $targetDays);

        $consumedResult = $progress->consumedMicronutrients($intakes, $recommendedMicros);
        $consumed = $consumedResult['consumed'];

        $percent = [];
        foreach ($recommendedMicros as $key => $rec) {
            $target = (float) $rec['value'];
            $got = (float) ($consumed[$key]['value'] ?? 0.0);
            $percent[$key] = $target > 0 ? min(999.0, ($got / $target) * 100.0) : 0.0;
        }

        return new JsonResponse([
            'date' => $day->format('Y-m-d'),
            'range' => $range,
            'startDate' => $start->format('Y-m-d'),
            'endDate' => $end->modify('-1 day')->format('Y-m-d'),
            'targetDays' => $targetDays,
            'recommended' => $recommendedMicros,
            'consumed' => $consumed,
            'percent' => $percent,
            'warnings' => $consumedResult['warnings'],
        ]);
    }
    
    // parses the date string in Y-m-d format. AI helped me with this code.
    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        //if the value is not a string or is empty, return null.
        if (!\is_string($value) || $value === '') {
            return null;
        }

        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $d === false ? null : $d;
    }

    
    private function weekStart(\DateTimeImmutable $day): \DateTimeImmutable
    {
        $weekday = (int) $day->format('N');

        return $day->modify('-'.($weekday - 1).' days')->setTime(0, 0, 0);
    }

    /**
     * Multiplies recommendation values by number of days.
     *
     * @param array<string, array{value: float|int, unit: string}> $recommendations Daily targets with units
     * @param int $days Number of days (1 for daily view, 7 for weekly view)
     * @return array<string, array{value: float|int, unit: string}> Adjusted targets
     */
    private function multiplyRecommendations(array $recommendations, int $days): array
    {
        if ($days === 1) {
            return $recommendations;
        }

        foreach ($recommendations as $key => $item) {
            $recommendations[$key]['value'] = ((float) $item['value']) * $days;
        }

        return $recommendations;
    }
}


