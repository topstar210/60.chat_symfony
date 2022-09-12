<?php

namespace App\Repository;

class UserTodoPnRepository extends Base
{
    /**
     * {@inheritDoc}
     */
    public function getRepositoryName()
    {
        return 'App\Entity\UserTodoPn';
    }

    public function minusRemainHours()
    {
        $this
            ->getRepository()
            ->createQueryBuilder('u')
            ->update()
            ->set('u.remain_hours', 'u.remain_hours - 1')
            ->andWhere('u.remain_hours > 0')
            ->getQuery()
            ->execute();
    }

    public function getTodoPn($kind)
    {
        $result = $this
            ->getRepository()
            ->createQueryBuilder('u')
            ->andWhere('u.kind = :kind')
            ->setParameter('kind', $kind)
            ->andWhere('u.remain_hours <= 0')
            ->getQuery()
            ->getResult();

        $this
            ->getRepository()
            ->createQueryBuilder('u')
            ->delete()
            ->andWhere('u.kind = :kind')
            ->setParameter('kind', $kind)
            ->andWhere('u.remain_hours <= 0')
            ->getQuery()
            ->execute();

        return $result;
    }

    public function getLoginPn()
    {
        $result = $this
            ->getRepository()
            ->createQueryBuilder('u')
            ->andWhere('u.kind = 10')
            ->andWhere('u.remain_hours <= 0')
            ->getQuery()
            ->getResult();

        $this
            ->getRepository()
            ->createQueryBuilder('u')
            ->update()
            ->set('u.remain_hours', '48')
            ->andWhere('u.remain_hours <= 0')
            ->getQuery()
            ->execute();

        return $result;
    }


    // /**
    //  * @return UserTodoPn[] Returns an array of UserTodoPn objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?UserTodoPn
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
