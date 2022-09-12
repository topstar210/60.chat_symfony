<?php

namespace App\Repository;

class ChatRepository extends Base
{
    /**
     * {@inheritDoc}
     */
    public function getRepositoryName()
    {
        return 'App\Entity\Chat';
    }
}
