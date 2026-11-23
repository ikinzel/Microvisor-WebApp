<?php

namespace App\Repository;

use App\Entity\BloodTestResult;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BloodTestResult>
 */
class BloodTestResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BloodTestResult::class);
    }

    /**
     * All results for a user, newest uploads first, nutrients alphabetical within each upload.
     *
     * @return BloodTestResult[]
     */
    public function findByUserOrderedByUpload(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.user = :user')
            ->setParameter('user', $user)
            ->orderBy('b.uploadedAt', 'DESC')
            ->addOrderBy('b.nutrientName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
