<?php

namespace App\Repository;

use App\Entity\Chat;

class ChatMessageRepository extends Base
{
    /**
     * {@inheritDoc}
     */
    public function getRepositoryName()
    {
        return 'App\Entity\ChatMessage';
    }

    /**
     * Find last chat message made or received.
     *
     * @param Chat $chat
     *
     * @return Message
     */
    public function findLastMessage(Chat $chat)
    {
        $messages = $this
            ->getRepository()
            ->createQueryBuilder('m')
            ->andWhere('m.chat = :chat')
            ->setParameter('chat', $chat)
            ->orderBy('m.date_created', 'desc')
            ->setFirstResult(0)
            ->setMaxResults(1)
            ->getQuery()
            ->execute()
        ;
        return count($messages) === 1 ? $messages[0] : null;
    }

    /**
     * Finds messages by filters.
     *
     * @param array   $filters Filters (optional)
     * @param string  $sort    Sort result (optional)
     * @param integer $page    Pagination offset (optional)
     * @param integer $limit   Maximum number of results to retrieve (optional)
     *
     * Filters:
     *
     * @param string  $query     Search query (optional)
     * @param Chat    $chat      Chat's id (optional)
     * @param integer $from_id   Filter results by id (optional)
     * @param string  $from_date Filter results by date (optional)
     * @param string  $to_date   Filter results by date (optional)
     */
    public function search(array $filters = array(), $sort = null, $page = 1, $limit = 20)
    {
        $query = $this
            ->getRepository()
            ->createQueryBuilder('m')
        ;

        if (isset($filters['query']) && $filters['query']) {
            $filters['query'] = '%'.$filters['query'].'%';

            $query
                ->andWhere('m.message like :message')
                ->setParameter('message', $filters['query'])
            ;
        }

        if (isset($filters['chat']) && $filters['chat']) {
            $query
                ->andWhere('m.chat = :chat')
                ->setParameter('chat', $filters['chat'])
            ;
        }

        if (isset($filters['from_id']) && $filters['from_id']) {
            $query
                ->andWhere('m.id > :id')
                ->setParameter('id', $filters['from_id'])
            ;
        }

        if (isset($filters['from_date']) && $filters['from_date']) {
            $query
                ->andWhere('m.date_created > :date_created')
                ->setParameter('date_created', $filters['from_date'])
            ;
        }

        if (isset($filters['to_date']) && $filters['to_date']) {
            $query
                ->andWhere('m.date_created < :date_created')
                ->setParameter('date_created', $filters['to_date'])
            ;
        }

        switch ($sort) {
            default:
                $query->orderBy('m.date_created', 'desc');
                break;
        }

        return parent::doSearch($query, 'm', $filters, $page, $limit);
    }
}
