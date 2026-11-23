<?php

namespace App\Service;

/**
 * Builds a Monday-first month grid (like many calendar UIs) with leading/trailing days.
 */
final class CalendarMonthGrid
{
    /**
     * @return list<list<\DateTimeImmutable>>
     */
    public function weeksForMonth(\DateTimeImmutable $anyDayInMonth): array
    {
        $first = $anyDayInMonth->modify('first day of this month')->setTime(0, 0, 0);
        $last = $anyDayInMonth->modify('last day of this month')->setTime(0, 0, 0);

        $n = (int) $first->format('N');
        $gridStart = $first->modify('-'.($n - 1).' days');

        $nLast = (int) $last->format('N');
        $gridEnd = $last->modify('+'.(7 - $nLast).' days');

        $weeks = [];
        $week = [];
        $d = $gridStart;
        while ($d <= $gridEnd) {
            $week[] = $d;
            if (\count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
            $d = $d->modify('+1 day');
        }
        if ($week !== []) {
            $weeks[] = $week;
        }

        return $weeks;
    }
}
