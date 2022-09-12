<?php

namespace App\Repository;

use Doctrine\ORM\NoResultException;
use App\Entity\User;

class FriendRepository extends Base
{
    /**
     * {@inheritDoc}
     */
    public function getRepositoryName()
    {
        return 'App\Entity\Friend';
    }

    /**
     * Checks if user already befriended with friend.
     *
     * @param App\Entity\User $user
     * @param App\Entity\User $friend
     *
     * @return \App\Entity\Friend or null if user does not exist
     */
    public function isBefriended($user, $friend)
    {
        try {
            return $this
                ->getRepository()
                ->createQueryBuilder('f')
                ->andWhere('f.user = :user')
                ->setParameter('user', $user)
                ->andWhere('f.friend = :friend')
                ->setParameter('friend', $friend)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException $e) {}

        return null;
    }

    /**
     * Finds friends by filters.
     *
     * @param array   $filters Filters (optional)
     * @param string  $sort    Sort result (optional)
     * @param integer $page    Pagination offset (optional)
     * @param integer $limit   Maximum number of results to retrieve (optional)
     *
     * Filters:
     *
     * @param string  $user        User entity (optional)
     * @param string  $query       Search query (optional)
     * @param array   $equals      List of fields with values (optional)
     * @param float   $latitude    Distance from.. (optional)
     * @param float   $longitude   Distance from.. (optional)
     * @param integer $distance    Maximum distance (optional)
     * @param string  $distance_by Distance by (optional)
     * @param string  $gender      User's gender (optional)
     * @param integer $age_from    User's age from (optional)
     * @param integer $age_to      User's age to (optional)
     * @param string  $ethnicity   User's ethnicity (optional)
     * @param string  $country     User's country (optional)
     * @param array   $block_users List of blocked users (optional)
     * @param boolean $block_only  Whether or not to only return blocked profiles (optional)
     * @param boolean $only_photo  Whether or not to only return profiles with photo (optional)
     */
    public function search(array $filters = array(), $sort = null, $page = 1, $limit = 20)
    {

        $query = $this
            ->getRepository()
            ->createQueryBuilder('f')
            ->leftJoin('f.friend', 'u')
            ->andWhere('u.enabled = 1')
        ;

        if (isset($filters['user']) && $filters['user']) {
            $query
                ->andWhere('f.user = :user')
                ->setParameter('user', $filters['user'])
            ;
        }

        if (isset($filters['query']) && $filters['query']) {
            $filters['query'] = '%'.$filters['query'].'%';

            $query
                ->andWhere('u.name like :name')
                ->setParameter('name', $filters['query'])
                ->orWhere('u.username like :username')
                ->setParameter('username', $filters['query'])
                ->orWhere('u.phone_number like :phone_number')
                ->setParameter('phone_number', $filters['query'])
                ->orWhere('u.aboutme like :aboutme')
                ->setParameter('aboutme', $filters['query'])
            ;
        }

        if (isset($filters['equals']) && is_array($filters['equals'])) {
            foreach ($filters['equals'] as $key => $value) {
                if ($value) {
                    switch ($key) {
                        case 'interest':
                            if (is_string($value)) {
                                $value = explode('|', $value);
                            }
                            break;
                    }
                    if (is_array($value)) {
                        foreach ($value as $k => $v) {
                            if ($k === 0) {
                                $query
                                    ->andWhere(sprintf('u.%s like :%s', $key, $key))
                                    ->setParameter($key, '%'.$v.'%')
                                ;
                            } else {
                                $query
                                    ->orWhere(sprintf('u.%s like :%s', $key, $key))
                                    ->setParameter($key, '%'.$v.'%')
                                ;
                            }
                        }
                    } else {
                        $query
                            ->andWhere(sprintf('u.%s like :%s', $key, $key))
                            ->setParameter($key, '%'.$value.'%')
                        ;
                    }
                } else {
                    $query
                        ->andWhere(sprintf('u.%s = :%s or u.%s is null', $key, $key, $key))
                        ->setParameter($key, $value)
                    ;
                }
            }
        }

        if (isset($filters['latitude']) && $filters['latitude'] &&
            isset($filters['longitude']) && $filters['longitude'] && isset($filters['distance']) && $filters['distance']
        ) {
            // to search by kilometers instead of miles, replace 3959 with 6371
            $distanceBy = isset($filters['distance_by']) && $filters['distance_by'] == User::DISTANCEBY_MILES ? 6371 : 3959;

            $query
                ->addSelect(sprintf('(%d * acos(cos(radians(%f)) * cos(radians(u.latitude)) * cos(radians(u.longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(u.latitude)))) as distance', $distanceBy, $filters['latitude'], $filters['longitude'], $filters['latitude']))
                ->andWhere('u.latitude is not null')
                ->andWhere('u.longitude is not null')
            ;

            if (isset($filters['distance']) && $filters['distance']) {
                $query
                    ->andWhere(sprintf('(%d * acos(cos(radians(%f)) * cos(radians(u.latitude)) * cos(radians(u.longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(u.latitude)))) <= :distance', $distanceBy, $filters['latitude'], $filters['longitude'], $filters['latitude']))
                    ->setParameter('distance', $filters['distance'])
                ;
            }
        }

        if (isset($filters['gender']) && $filters['gender']) {
            $query
                ->andWhere('u.gender = :gender')
                ->setParameter('gender', $filters['gender'])
            ;
        }

        if (isset($filters['age_from']) && $filters['age_from']) {
            $date = new \DateTime();
            $date->setTime(0, 0, 0);
            $date->sub(new \DateInterval(sprintf('P%dY', $filters['age_from'])));

            $query
                ->andWhere('u.birthday <= :from_birthday')
                ->setParameter('from_birthday', $date)
            ;
        }

        if (isset($filters['age_to']) && $filters['age_to']) {
            $date = new \DateTime();
            $date->setTime(0, 0, 0);
            $date->sub(new \DateInterval(sprintf('P%dY', $filters['age_to'])));

            $query
                ->andWhere('u.birthday >= :to_birthday')
                ->setParameter('to_birthday', $date)
            ;
        }

        if (isset($filters['ethnicity']) && $filters['ethnicity']) {
            $query
                ->andWhere('u.ethnicity = :ethnicity')
                ->setParameter('ethnicity', $filters['ethnicity'])
            ;
        }

        if (isset($filters['country']) && $filters['country']) {
            $query
                ->andWhere('u.region like :country')
                ->setParameter('country', '%'.$filters['country'])
            ;
        }

        if (isset($filters['block_users']) && $filters['block_users']) {
            if (isset($filters['block_only']) && $filters['block_only']) {
                $query
                    ->andWhere('u.id in (:id)')
                    ->setParameter('id', $filters['block_users'])
                ;
            } else {
                $query
                    ->andWhere('u.id not in (:id)')
                    ->setParameter('id', $filters['block_users'])
                ;
            }
        }

        if (isset($filters['only_photo']) && $filters['only_photo']) {
            $query->andWhere('u.photo is not null');
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
                $query->orderBy('f.date_created');
                break;

            case 'recent':
            default:
                $query->orderBy('f.date_created', 'desc');
                break;
        }

        return parent::doSearch($query, 'f', $filters, $page, $limit);
    }
}
