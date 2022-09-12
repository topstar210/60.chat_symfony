<?php

namespace App\Repository;

use Doctrine\ORM\NoResultException;

class MomentCommentRepository extends Base
{
    /**
     * {@inheritDoc}
     */
    public function getRepositoryName()
    {
        return 'App\Entity\MomentComment';
    }
}
