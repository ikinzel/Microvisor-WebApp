<?php

namespace App\Service;

use App\Entity\FoodIntake;
use Doctrine\ORM\EntityManagerInterface;


final class FoodIntakeNameHelper
{
    public function __construct(
        private readonly UsdaFoodDataCentralClient $fdc,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param iterable<FoodIntake> $intakes
     */
    public function ensureNames(iterable $intakes): void
    {
        $list = [];
        foreach ($intakes as $i) {
            $list[] = $i;
        }
        if ($list === []) {
            return;
        }

        $ids = [];
        foreach ($list as $i) {
            $name = $i->getSrcName();
            if (($name === null || $name === '') && $i->getSrcId() !== null) {
                $ids[$i->getSrcId()] = true;
            }
        }
        if ($ids === []) {
            return;
        }

        $changed = false;
        foreach (array_keys($ids) as $fdcId) {
            try {
                $desc = $this->fdc->getFoodDescription($fdcId);
            } catch (\Throwable) {
                continue;
            }
            if ($desc === null) {
                continue;
            }
            $desc = $this->truncate($desc);
            foreach ($list as $i) {
                if ($i->getSrcId() === $fdcId && ($i->getSrcName() === null || $i->getSrcName() === '')) {
                    $i->setSrcName($desc);
                    $changed = true;
                }
            }
        }

        if ($changed) {
            $this->entityManager->flush();
        }
    }

    private function truncate(string $s): string
    {
        if (\strlen($s) <= 512) {
            return $s;
        }

        return substr($s, 0, 509).'...';
    }
}
