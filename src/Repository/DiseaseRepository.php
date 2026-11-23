<?php

namespace App\Repository;

use App\Entity\Disease;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/*
 * DiseaseRepository provides database queries for Disease entities.
 *
 * This repository handles custom database queries related to medical conditions/diseases.
 * It extends Doctrine's ServiceEntityRepository which provides basic CRUD operations.
 *
 * @extends ServiceEntityRepository<Disease>
 */
class DiseaseRepository extends ServiceEntityRepository
{
    /*
     * Constructor for DiseaseRepository.
     *
     * Registers this repository with Doctrine's EntityManager for the Disease entity.
     * This allows the repository to perform database queries on the diseases table.
     *
     * @param ManagerRegistry $registry Doctrine's registry of all entity managers
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Disease::class);
    }

    /*
     * Retrieves all diseases from database, ordered alphabetically, excluding test data.
     *
     * This method queries the database for all Disease entities, excludes any diseases
     * with "test" in their label or code (to hide test/dummy data), and orders results
     * alphabetically by disease label.
     *
     * Used in ConditionsController to populate the disease selection dropdown form.
     *
     * @return array<int, Disease> Array of Disease entities ordered by label, excluding test entries
     */
    public function findAllOrderedByLabelExcludingTest(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.label NOT LIKE :test')
            ->andWhere('d.code NOT LIKE :test')
            ->setParameter('test', '%test%')
            ->orderBy('d.label', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
