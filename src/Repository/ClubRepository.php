<?php

namespace App\Repository;

use App\Entity\User;

class ClubRepository extends Base
{
    /**
     * {@inheritDoc}
     */
    public function getRepositoryName()
    {
        return 'App\Entity\Club';
    }

    /**
     * Finds clubs by filters.
     *
     * @param array   $filters Filters (optional)
     * @param string  $sort    Sort result (optional)
     * @param integer $page    Pagination offset (optional)
     * @param integer $limit   Maximum number of results to retrieve (optional)
     *
     * Filters:
     *
     * @param string  $query       Search query (optional)
     * @param string  $username    User's username (optional)
     * @param float   $latitude    Distance from.. (optional)
     * @param float   $longitude   Distance from.. (optional)
     * @param integer $distance    Maximum distance (optional)
     * @param string  $distance_by Distance by (optional)
     * @param boolean $only_owned  Whether or not to only return user owned clubs (optional)
     */
    public function search(array $filters = array(), $sort = null, $page = 1, $limit = 20)
    {
        $query = $this
            ->getRepository()
            ->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->andWhere('u.enabled = true')
        ;

        if (isset($filters['query']) && $filters['query']) {
            $filters['query'] = '%'.$filters['query'].'%';

            $query
                ->andWhere('c.name like :name')
                ->setParameter('name', $filters['query'])
                ->orWhere('c.description like :description')
                ->setParameter('description', $filters['query'])
            ;
        }

        if (isset($filters['username']) && $filters['username']) {
            if (isset($filters['only_owned']) && $filters['only_owned']) {
                $query
                    ->andWhere('u.username = :username')
                    ->setParameter('username', $filters['username'])
                ;
            } else {
                $query
                    ->leftJoin('c.participants', 'p')
                    ->leftJoin('p.user', 'pu')
                    ->andWhere('pu.username = :username')
                    ->setParameter('username', $filters['username'])
                ;
            }
        }

        if (isset($filters['latitude']) && $filters['latitude'] &&
            isset($filters['longitude']) && $filters['longitude']
        ) {
            // to search by kilometers instead of miles, replace 3959 with 6371
            $distanceBy = isset($filters['distance_by']) && $filters['distance_by'] == User::DISTANCEBY_MILES ? 6371 : 3959;

            $query
                ->addSelect(sprintf('(%d * acos(cos(radians(%f)) * cos(radians(c.latitude)) * cos(radians(c.longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(c.latitude)))) as distance', $distanceBy, $filters['latitude'], $filters['longitude'], $filters['latitude']))
                ->andWhere('c.latitude is not null')
                ->andWhere('c.longitude is not null')
            ;

            if (isset($filters['distance']) && $filters['distance']) {
                $query
                    ->andWhere(sprintf('(%d * acos(cos(radians(%f)) * cos(radians(c.latitude)) * cos(radians(c.longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(c.latitude)))) <= :distance', $distanceBy, $filters['latitude'], $filters['longitude'], $filters['latitude']))
                    ->setParameter('distance', $filters['distance'])
                ;
            }
        }

        switch ($sort) {
            case 'distance':
                if (isset($distanceBy)) {
                    $query->orderBy('distance');
                } else {
                    $query->orderBy('u.region');
                }

                break;

            case 'oldest':
                $query->orderBy('c.date_created');
                break;

            case 'recent':
            default:
                $query->orderBy('c.date_created', 'desc');
                break;
        }

        return parent::doSearch($query, 'c', $filters, $page, $limit);
    }
}
