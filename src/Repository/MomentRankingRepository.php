<?php

namespace App\Repository;

use App\Entity\MomentRanking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @method MomentRanking|null find($id, $lockMode = null, $lockVersion = null)
 * @method MomentRanking|null findOneBy(array $criteria, array $orderBy = null)
 * @method MomentRanking[]    findAll()
 * @method MomentRanking[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MomentRankingRepository extends Base
{
    /**
     * {@inheritDoc}
     */
    public function getRepositoryName()
    {
        return 'App\Entity\MomentRanking';
    }

    public function getRank($kind, $moment_id, $profile_id, $user_id)
    {
        if($kind == 1){
            return $this
                ->getRepository()
                ->createQueryBuilder('u')
                ->andWhere('u.moment_id = :moment_id')
                ->setParameter('moment_id', $moment_id)
                ->andWhere('u.user_id = :user_id')
                ->setParameter('user_id', $user_id)
                ->getQuery()
                ->getResult();
        } else{
            return $this
                ->getRepository()
                ->createQueryBuilder('u')
                ->andWhere('u.profile_id = :profile_id')
                ->setParameter('profile_id', $profile_id)
                ->andWhere('u.user_id = :user_id')
                ->setParameter('user_id', $user_id)
                ->getQuery()
                ->getResult();
        }
    }

    public function getRankInfo($kind, $moment_id, $profile_id)
    {
        $rank_count = 0;
        $rank_sum = 0.0;
        $rank_average = 0.0;
        $view_count = 0;
        if($kind == 1){
            try {
                $rank_count = $this
                    ->getRepository()
                    ->createQueryBuilder('u')
                    ->select('COUNT(u.id)')
                    ->andWhere('u.moment_id = :moment_id')
                    ->setParameter('moment_id', $moment_id)
                    ->andWhere('u.rate > 0')
                    ->getQuery()
                    ->getSingleScalarResult();
            } catch (NoResultException $e) {
            } catch (NonUniqueResultException $e) {
            }
            try {
                $rank_sum = $this
                    ->getRepository()
                    ->createQueryBuilder('u')
                    ->select('SUM(u.rate)')
                    ->andWhere('u.moment_id = :moment_id')
                    ->setParameter('moment_id', $moment_id)
                    ->andWhere('u.rate > 0')
                    ->getQuery()
                    ->getSingleScalarResult();
            } catch (NoResultException $e) {
            } catch (NonUniqueResultException $e) {
            }
            try {
                $view_count = $this
                    ->getRepository()
                    ->createQueryBuilder('u')
                    ->select('COUNT(u.id)')
                    ->andWhere('u.moment_id = :moment_id')
                    ->setParameter('moment_id', $moment_id)
                    ->getQuery()
                    ->getSingleScalarResult();
            } catch (NoResultException $e) {
            } catch (NonUniqueResultException $e) {
            }
        } else{
            try {
                $rank_count = $this
                    ->getRepository()
                    ->createQueryBuilder('u')
                    ->select('COUNT(u.id)')
                    ->andWhere('u.profile_id = :profile_id')
                    ->setParameter('profile_id', $profile_id)
                    ->andWhere('u.rate > 0')
                    ->getQuery()
                    ->getSingleScalarResult();
            } catch (NoResultException $e) {
            } catch (NonUniqueResultException $e) {
            }
            try {
                $rank_sum = $this
                    ->getRepository()
                    ->createQueryBuilder('u')
                    ->select('SUM(u.rate)')
                    ->andWhere('u.profile_id = :profile_id')
                    ->setParameter('profile_id', $profile_id)
                    ->andWhere('u.rate > 0')
                    ->getQuery()
                    ->getSingleScalarResult();
            } catch (NoResultException $e) {
            } catch (NonUniqueResultException $e) {
            }
            try {
                $view_count = $this
                    ->getRepository()
                    ->createQueryBuilder('u')
                    ->select('COUNT(u.id)')
                    ->andWhere('u.profile_id = :profile_id')
                    ->setParameter('profile_id', $profile_id)
                    ->getQuery()
                    ->getSingleScalarResult();
            } catch (NoResultException $e) {
            } catch (NonUniqueResultException $e) {
            }
        }
        if( $rank_count > 0 ){
            $rank_average = sprintf("%.2f", $rank_sum / $rank_count);
        }

        return ["rank_count" => $rank_count, "rank_average" => $rank_average, "view_count" => $view_count];
    }


    // /**
    //  * @return MomentRanking[] Returns an array of MomentRanking objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?MomentRanking
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
