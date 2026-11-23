<?php

namespace App\Repository;

use App\Entity\FoodIntake;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/*
 * FoodIntakeRepository provides database queries for FoodIntake entities.
 *
 * This repository handles custom database queries related to food intake tracking.
 * This class adds a custom query method for fetching food intakes within a specific µ
 * date range, which is used by the home dashboard.
 *
 * @extends ServiceEntityRepository<FoodIntake>
 */
class FoodIntakeRepository extends ServiceEntityRepository
{

    /*
     * Constructor for FoodIntakeRepository.
     *
     * Registers this repository with Doctrine's EntityManager for the FoodIntake entity.
     * This allows the repository to perform database queries on the food_intake table.
     *
     * @param ManagerRegistry $registry Doctrine's registry of all entity managers
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FoodIntake::class);
    }

    /*
     * Retrieves all food intakes for a specific user within a date range.
     *
     * This method queries the database for all FoodIntake records belonging to a user
     * that were created between a start and end date. The date range is inclusive of
     * the start date but exclusive of the end date.
     *
     * Results are ordered chronologically from earliest to latest by creation time.
     *
     * Used by HomeController to fetch food intakes for the selected calendar view
     * (day or week), which are then displayed in the food intake dashboard.
     *
     * @param User $user The user whose food intakes to fetch
     * @param \DateTimeImmutable $start The start date (inclusive) for the date range
     * @param \DateTimeImmutable $end The end date (exclusive) for the date range
     * @return FoodIntake[] Array of FoodIntake entities ordered by creation time
     */
    public function findByUserCreatedBetween(User $user, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->andWhere('f.createdAt >= :start')
            ->andWhere('f.createdAt < :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('f.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
