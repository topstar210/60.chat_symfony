<?php

namespace App\Repository;

use App\Repository\Base;
use Doctrine\ORM\NoResultException;

class RegionRepository extends Base
{
    /**
     * {@inheritDoc}
     */
    public function getRepositoryName()
    {
        return 'App\Entity\Region';
    }

    /**
     * @return array
     */
    public function groupByCountry()
    {
        $regions = $this
            ->getRepository()
            ->createQueryBuilder('r')
            ->orderBy('r.country_code, r.name')
            ->getQuery()
            ->getResult();
        ;

        $data = array();

        foreach ($regions as $region) {
            if (!isset($data[$region->getCountryCode()])) {
                $data[$region->getCountryCode()] = array();
            }
            $data[$region->getCountryCode()][] = $region->getName();
        }

        return $data;
    }
}
