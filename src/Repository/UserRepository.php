<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Common\Persistence\ManagerRegistry;
use App\Utils\Inflector;
use App\Entity\User;

class UserRepository extends Base
{
    /**
     * {@inheritDoc}
     */
    public function getRepositoryName()
    {
        return 'App\Entity\User';
    }

    /**
     * Returns promo stats.
     */
    public function getPromoterStats()
    {
        try {
            return $this
                ->getRepository()
                ->createQueryBuilder('u')
                ->select('u.promo_code, date_format(u.date_created, \'%Y-%m-%d\') as date, count(u.id) as total')
                ->where('u.promo_code is not null')
                ->groupBy('u.promo_code, date')
                ->getQuery()
                ->getArrayResult()
            ;
        } catch (NoResultException $e) {}

        return null;
    }

    /**
     * Calculate the Distance Between Two Coordinates (latitude, longitude).
     *
     * @param $lat1
     * @param $lng1
     * @param $lat2
     * @param $lng2
     * @param bool $miles
     * @return float The distance in miles or kilometers
     */
    public function distance($lat1, $lng1, $lat2, $lng2, $miles = true)
    {
        if (!$lat1 || !$lng1 || !$lat2 || !$lng2) {
            return null;
        }

        $distance = (($miles ? 3959 : 6371) * acos(cos(deg2rad($lat2)) * cos(deg2rad($lat1)) * cos(deg2rad($lng1) - deg2rad($lng2)) + sin(deg2rad($lat2)) * sin(deg2rad($lat1))));
        return !is_nan($distance) ? $distance : null;
    }

    /**
     * Find a user by its token.
     *
     * @param integer id
     *
     * @return User or null if user does not exist
     * @throws NonUniqueResultException
     */
    public function findUserById($id)
    {
        try {
            return $this
                ->getRepository()
                ->createQueryBuilder('u')
                ->andWhere('u.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException $e) {}

        return null;
    }

    /**
     * Find a user by its token.
     *
     * @param string $token
     *
     * @return User or null if user does not exist
     * @throws NonUniqueResultException
     */
    public function findUserByToken($token)
    {
        try {
            return $this
                ->getRepository()
                ->createQueryBuilder('u')
                ->andWhere('u.enabled = true')
                //->andWhere('u.token = :token')
                ->andWhere('sha1(u.id) = :token')
                ->setParameter('token', $token)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException $e) {}

        return null;
    }

    /**
     * Find a user by its username.
     *
     * @param string $username
     *
     * @return User or null if user does not exist
     */
    public function findUserByUsername($username)
    {
        return $this->getRepository()->findOneByUsername($username);
    }

    /**
     * Finds a user by its phone number.
     *
     * @param string $phone_number
     *
     * @return User or null if user does not exist
     */
    public function findUserByPhoneNumber($phone_number)
    {
        return $this->getRepository()->findOneBy(array('phone_number' => $phone_number));
    }

    /**
     * Finds a user by its email.
     *
     * @param string $email
     *
     * @return User or null if user does not exist
     */
    public function findUserByEmail($email)
    {
        return $this->getRepository()->findOneByEmail($email);
    }

    /**
     * Finds a user by its facebook user id.
     *
     * @param string $facebookUid
     *
     * @return object
     */
    public function findUserByFacebookUid($facebookUid)
    {
        return $this->getRepository()->findOneBy(array('facebook_uid' => $facebookUid));
    }

    /**
     * Finds a user by its username, phone number or email.
     *
     * @param string $usernameOrPhoneNumberOrEmail
     *
     * @return User or null if user does not exist
     * @throws NonUniqueResultException
     */
    public function findUserByUsernameOrPhoneNumberOrEmail($usernameOrPhoneNumberOrEmail)
    {
        try {
            return $this
                ->getRepository()
                ->createQueryBuilder('u')
                ->andWhere('u.username = :username')
                ->setParameter('username', $usernameOrPhoneNumberOrEmail)
                ->orWhere('u.phone_number = :phone_number')
                ->setParameter('phone_number', $usernameOrPhoneNumberOrEmail)
                ->orWhere('u.email = :email')
                ->setParameter('email', $usernameOrPhoneNumberOrEmail)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException $e) {}

        return null;
    }

    /**
     * Find all users by their username.
     *
     * @param array $usernames
     *
     * @return array or null if no users found
     */
    public function findUsersByUsername(array $usernames = array())
    {
        return $this
            ->getRepository()
            ->createQueryBuilder('u')
            ->andWhere('u.enabled = true')
            ->andWhere('u.username in (:username)')
            ->setParameter('username', $usernames)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Returns a unique captcha.
     *
     * @return string
     * @throws NonUniqueResultException
     */
    public function generateCaptcha()
    {
        do {
            $found = $this->findUserByCaptcha($captcha = Inflector::getRandomString(6, '0123456789'));
        } while ($found);

        return $captcha;
    }

    /**
     * Finds a user by its captcha.
     *
     * @param string $captcha
     *
     * @return User or null if user does not exist
     * @throws NonUniqueResultException
     */
    public function findUserByCaptcha($captcha)
    {
        try {
            return $this
                ->getRepository()
                ->createQueryBuilder('u')
                ->andWhere('u.captcha like :captcha')
                ->setParameter('captcha', sprintf('%%"value":"%s"%%', $captcha))
                ->getQuery()
                ->getSingleResult();
            ;
        } catch (NoResultException $e) {}

        return null;
    }

    /**
     * Finds users by filters.
     *
     * @param array   $filters Filters (optional)
     * @param string  $sort    Sort result (optional)
     * @param integer $page    Pagination offset (optional)
     * @param integer $limit   Maximum number of results to retrieve (optional)
     *
     * Filters:
     *
     * @param string  $query       Search query (optional)
     * @param array   $equals      List of fields with values (optional)
     * @param string  $username    Search query (optional)
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
            ->createQueryBuilder('u')
            ->andWhere('u.enabled = true')
        ;

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

        if (isset($filters['username']) && $filters['username']) {
            $filters['username'] = '%'.$filters['username'].'%';

            $query
                ->andWhere('u.username like :username')
                ->setParameter('username', $filters['username'])
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
            isset($filters['longitude']) && $filters['longitude']
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
                ->setParameter('country', '%'.$filters['country'].'%')
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

            case 'username':
                $query->orderBy('u.username');
                break;

            case 'oldest':
                $query->orderBy('u.date_created');
                break;

            case 'recent':
            default:
                $query->orderBy('u.date_created', 'desc');
                break;
        }

        return parent::doSearch($query, 'u', $filters, $page, $limit);
    }
}
