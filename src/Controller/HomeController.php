<?php

namespace App\Controller;

use App\Entity\FoodIntake;
use App\Entity\User;
use App\Enum\CalendarView;
use App\Repository\FoodIntakeRepository;
use App\Service\DailyRequirementsCalculator;
use App\Service\FoodIntakeNameHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;


/*
 * HomeController handles the main dashboard/homa page of Microvisor.
 *
 * This is the primary controller for authenticated users. It manages:
 * -Displaying the user's food intake data in calender view (day or week)
 * -Filtering food intake by date range based on selected view
 * - Calculating daily nutritional requirements for the user
 * - Displaying medical condition-specific dietary suggestions
 * - Handling calendar navigation
 * - Supporting multiple calendar view options (day, week)
 * - Managing optional weekend visibility toggle
 */

final class HomeController extends AbstractController
{

    /*
     * Displays the home dashboard with user's food intake and nutritional data
     *
     * This action:
     * -Requires authentication (redirects to log in if not logged in)
     * - Parses view type (day or week) from URL parameters
     * - Retrieves food intake records for the selected date range
     * - Calculates daily nutritional requirements based on user's age, weight, height, sex
     * - Organizes food intake data by date for calendar display
     * - Handles optional weekend visibility in calendar
     * - Passes all data to template for dashboard rendering
     *
     * @param Request $request The HTTP request (contains view, date, month parameters)
     * @param SessionInterface $session The user session (contains user_id, user_name, is_returning)
     * @param EntityManagerInterface $entityManager Doctrine ORM manager for database operations
     * @param FoodIntakeRepository $foodIntakeRepository Repository for querying food intake records
     * @param FoodIntakeNameHelper $foodIntakeNameHelper Service for ensuring food names are loaded
     * @param DailyRequirementsCalculator $calculator Service for calculating nutritional requirements
     * @return Response The rendered home dashboard template
     *
     */
    #[Route('/home', name: 'app_home')]
    public function index(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $entityManager,
        FoodIntakeRepository $foodIntakeRepository,
        FoodIntakeNameHelper $foodIntakeNameHelper,
        DailyRequirementsCalculator $calculator,
    ): Response {
        $userId = $session->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $user = $entityManager->getRepository(User::class)->find((int) $userId);
        if (!$user) {
            $session->remove('user_id');
            $session->remove('user_name');

            return $this->redirectToRoute('app_login');
        }

        $view = CalendarView::tryFrom((string) $request->query->get('view', 'week')) ?? CalendarView::Week;
        $showWeekends = $request->query->get('show_weekends', '1') !== '0';

        $anchor = $this->resolveAnchor($request);

        [$rangeStart, $rangeEnd] = $this->viewRange($view, $anchor);

        /** @var list<FoodIntake> $intakes */
        $intakes = $foodIntakeRepository->findByUserCreatedBetween($user, $rangeStart, $rangeEnd);

        try {
            $foodIntakeNameHelper->ensureNames($intakes);
        } catch (\Throwable) {
        }

        /** @var array<string, list<FoodIntake>> $byDay */
        $byDay = [];
        foreach ($intakes as $intake) {
            $createdAt = $intake->getCreatedAt();
            if ($createdAt === null) {
                continue;
            }
            $key = $createdAt->format('Y-m-d');
            if (!isset($byDay[$key])) {
                $byDay[$key] = [];
            }
            $byDay[$key][] = $intake;
        }

        $today = new \DateTimeImmutable('today');
        $todayKey = $today->format('Y-m-d');

        $monthForPicker = $anchor->modify('first day of this month');
        $monthYm = $monthForPicker->format('Y-m');
        $prevNav = $this->navAnchors($view, $anchor);

        $weekColumns = [];
        if ($view === CalendarView::Week) {
            $weekColumns = $this->buildWeekColumns($anchor, $byDay, $showWeekends);
        }

        $viewOptions = [];
        foreach (CalendarView::cases() as $case) {
            $viewOptions[] = [
                'value' => $case->value,
                'label' => $case->label(),
                'shortcut' => $case->shortcut(),
            ];
        }

        $dayItems = $byDay[$anchor->format('Y-m-d')] ?? [];

        return $this->render('home/index.html.twig', [
            'userName' => $session->get('user_name'),
            'isReturning' => $session->get('is_returning'),
            'viewName' => $view->value,
            'viewLabel' => $view->label(),
            'viewOptions' => $viewOptions,
            'showWeekends' => $showWeekends,
            'anchorDate' => $anchor->format('Y-m-d'),
            'rangeTitle' => $this->rangeTitle($view, $anchor),
            'monthLabel' => $monthForPicker->format('F Y'),
            'monthValue' => $monthYm,
            'prevDate' => $prevNav['prev']->format('Y-m-d'),
            'nextDate' => $prevNav['next']->format('Y-m-d'),
            'weekColumns' => $weekColumns,
            'dayItems' => $dayItems,
            'todayKey' => $todayKey,
            'recs' => $calculator->calculate($user),
            'disease' => $user->getDisease(),
        ]);
    }

    /*
     * Resolves the anchor date from URL parameters.
     *
     * The anchor date is the center date that the calendar is displaying.
     * Looks for ?month=Y-m or ?date=Y-m-d.
     * Falls back to today's date if neither parameter is provided.
     *
     * @param Request $request The HTTP request containing query parameters
     * @return \DateTimeImmutable The resolved anchor date at midnight.
     *
     */
    private function resolveAnchor(Request $request): \DateTimeImmutable
    {
        $monthQ = $request->query->get('month');
        if (\is_string($monthQ) && preg_match('/^\d{4}-\d{2}$/', $monthQ)) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m', $monthQ);
            if ($parsed !== false) {
                return $parsed->setTime(0, 0, 0);
            }
        }

        $d = $this->parseDate($request->query->get('date'));
        if ($d !== null) {
            return $d->setTime(0, 0, 0);
        }

        return (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
    }


    /*
     * Parses a date string in Y-m-d format.
     *
     * Safely converts a date string to a DateTimeImmutable object.
     * Returns null if the input is not a valid Y-m-d formatted date.
     *
     * @param mixed $value The value to parse
     * @return \DateTimeImmutable|null The parsed date, or null if invalid
     */

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || $value === '') {
            return null;
        }

        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $d === false ? null : $d;
    }

    /*
     * Calculates the date range to fetch food intake data for.
     *
     * Based on the view type and anchor date:
     * - Day view: returns single day (anchor to anchor+1 day)
     * - Week view: returns full week (Monday to following Monday)
     *
     * @param CalendarView $view The current calendar view (day or week)
     * @param \DateTimeImmutable $anchor The center date for the view
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} [startDate, endDate]
     */

    private function viewRange(CalendarView $view, \DateTimeImmutable $anchor): array
    {
        return match ($view) {
            CalendarView::Day => [
                $anchor,
                $anchor->modify('+1 day'),
            ],
            CalendarView::Week => $this->weekBounds($anchor),
        };
    }


    /*
     * Calculates Monday and Sunday dates for the week containing the anchor date.
     *
     * Takes any date and returns the Monday Sunday boundaries of that week.
     *
     * @param \DateTimeImmutable $anchor Any date in the desired week
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} [Monday, nextMonday]
     */

    private function weekBounds(\DateTimeImmutable $anchor): array
    {
        $n = (int) $anchor->format('N');
        $monday = $anchor->modify('-'.($n - 1).' days')->setTime(0, 0, 0);

        return [$monday, $monday->modify('+7 days')];
    }


    /*
     * Builds week view column data for template rendering.
     *
     * Creates an array representing each day of the week with:
     * - Date key
     * - Display labels
     * - Food intake items for that day
     * - Flags for whether it's today or selected date
     * - Optionally excludes weekends if showWeekends is false
     *
     * @param \DateTimeImmutable $anchor The selected date (for highlighting)
     * @param array<string, list<FoodIntake>> $byDay Food intakes grouped by date
     * @param bool $showWeekends Whether to include Saturday and Sunday
     * @return list<array{dateKey: string, label: string, shortLabel: string, items: list<FoodIntake>, isToday: bool, isSelected: bool}>
     */

    private function buildWeekColumns(\DateTimeImmutable $anchor, array $byDay, bool $showWeekends): array
    {
        [$monday] = $this->weekBounds($anchor);
        $selectedKey = $anchor->format('Y-m-d');
        $cols = [];
        for ($i = 0; $i < 7; ++$i) {
            $d = $monday->modify('+'.$i.' days');
            $w = (int) $d->format('N');
            if (!$showWeekends && ($w === 6 || $w === 7)) {
                continue;
            }
            $key = $d->format('Y-m-d');
            $cols[] = [
                'dateKey' => $key,
                'label' => $d->format('D j'),
                'shortLabel' => $d->format('D'),
                'items' => $byDay[$key] ?? [],
                'isToday' => $key === (new \DateTimeImmutable('today'))->format('Y-m-d'),
                'isSelected' => $key === $selectedKey,
            ];
        }

        return $cols;
    }

    /*
     * Generates a human-readable title for the date range being displayed.
     *
     * @param CalendarView $view The current calendar view
     * @param \DateTimeImmutable $anchor The anchor/selected date
     * @return string Human-readable range title
     */

    private function rangeTitle(CalendarView $view, \DateTimeImmutable $anchor): string
    {
        return match ($view) {
            CalendarView::Day => $anchor->format('l, F j, Y'),
            CalendarView::Week => $this->weekTitle($anchor),
        };
    }

    /*
     * Generates a week view title showing Monday to Sunday with year.
     *
     * @param \DateTimeImmutable $anchor Any date in the desired week
     * @return string Week title in "Mon Date-Sun Date, Year" format
     */

    private function weekTitle(\DateTimeImmutable $anchor): string
    {
        [$mon] = $this->weekBounds($anchor);
        $sun = $mon->modify('+6 days');

        return $mon->format('M j').' – '.$sun->format('M j, Y');
    }

    /*
     * Calculates previous and next dates for calendar navigation buttons.
     *
     * Based on view type:
     * - Day view: moves by 1 day
     * - Week view: moves by 7 days
     *
     * @param CalendarView $view The current calendar view
     * @param \DateTimeImmutable $anchor The current anchor date
     * @return array{prev: \DateTimeImmutable, next: \DateTimeImmutable} Previous and next dates
     */
    private function navAnchors(CalendarView $view, \DateTimeImmutable $anchor): array
    {
        return match ($view) {
            CalendarView::Day => [
                'prev' => $anchor->modify('-1 day'),
                'next' => $anchor->modify('+1 day'),
            ],
            CalendarView::Week => [
                'prev' => $anchor->modify('-7 days'),
                'next' => $anchor->modify('+7 days'),

            ],
        };
    }
}
