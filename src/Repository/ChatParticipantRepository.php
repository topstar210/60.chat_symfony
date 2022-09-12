<?php

namespace App\Repository;

use Doctrine\ORM\NoResultException;
use App\Entity\User;
use App\Entity\Chat;
use App\Entity\Club;

class ChatParticipantRepository extends Base
{
    /**
     * {@inheritDoc}
     */
    public function getRepositoryName()
    {
        return 'App\Entity\ChatParticipant';
    }

    /**
     * Find all open chats user participant.
     *
     * @param User    $user         User object (optional)
     * @param Chat    $chat         Chat object (optional)
     * @param boolean $include_club Whether or not to include club messages (optional)
     * @param integer $page         Pagination offset (optional)
     * @param integer $limit        Maximum number of results to retrieve (optional)
     *
     * @return \App\Entity\Chat[]
     */
    public function findOpenChats(User $user = null, Chat $chat = null, $includeClub = false, $page = 1, $limit = 20)
    { 
        $queryString = null;

        // filter by user
        if ($user) {
            $queryString .= sprintf(' and p.user_id = %d', $user->getId());
        }

        // filter by chat
        if ($chat) {
            $queryString .= sprintf(' and c.id = %d', $chat->getId());
        }

        // add club filter
        if ($includeClub !== true) {
            if ($includeClub instanceof Club) {
                $queryString .= sprintf(' and c.club_id = %d', $includeClub->getId());
            } elseif ($includeClub == -1) {
                $queryString .= ' and c.club_id is not null';
            } else {
                $queryString .= ' and c.club_id is null';
            }
        }

        // finds chats along with latest message ordered by latest message date desc
        $queryString = sprintf('
            select sql_calc_found_rows
                c.id as "chat_id", c.subject, m1.message, m1.files, m1.date_created, u.username,
                cl.id as "club_id", cl.name as "club_name", cl.description as "club_description", cl.photo as "club_photo"
            from chats c
                left outer join clubs as cl on (c.club_id = cl.id)
                left join chats_participants p on (p.chat_id = c.id)
                left join chats_messages m1 on (m1.chat_id = c.id)
                left outer join chats_messages as m2 on (
                    c.id = m2.chat_id and (m1.date_created < m2.date_created or m1.date_created = m2.date_created and m1.id < m2.id)
                )
                left join users u on (u.id = m1.user_id)
            where p.open = 1 and m2.id is null %s
            group by
                c.id, c.subject, m1.message, m1.files, m1.date_created, u.username,
                cl.id, cl.name, cl.description, cl.photo
            order by m1.date_created desc
        ', $queryString);

        $offset = -1;
        if ($limit > -1) {
            $offset = ($page - 1) * $limit;
            $queryString .= sprintf(' limit %s, %s', (int) $offset, (int) $limit);
        }

        $query = $this->getEntityManager()->getConnection()->prepare($queryString);
        $query->execute();
        $results = $query->fetchAll();

        $totalQuery = $this->getEntityManager()->getConnection()->executeQuery('select found_rows()');
        $total = $totalQuery->fetchColumn();

        $chats = array();
        foreach ($results as $result) {
            $id = $result['chat_id'];
            $chats[$id] = array(
                'chat_id' => $id,
                'subject' => $result['subject'],
                'participants' => array(),
                'last_message' => array(
                    'from_username' => $result['username'],
                    'to_usernames' => array(),
                    'message' => $result['message'],
                    'files' => (array) unserialize($result['files']),
                    'date_created' => new \DateTime($result['date_created']),
                ),
            );

            if ($result['club_id']) {
                $chats[$id]['club'] = array(
                    'id' => $result['club_id'],
                    'name' => $result['club_name'],
                    'description' => $result['club_description'],
                    'photo' => $result['club_photo'],
                );
            }
        }

        $participants = $this
            ->getRepository()
            ->createQueryBuilder('p')
            ->select('partial p.{id, unread}, partial c.{id}, partial u.{id, username, name, gender, aboutme, photo}')
            ->leftJoin('p.user', 'u')
            ->leftJoin('p.chat', 'c')
            ->where('p.chat in (:chats)')
            ->setParameter('chats', array_keys($chats))
            ->getQuery()
            ->getResult();

        foreach ($participants as $participant) {
            $user = $participant->getUser();
            $id = $participant->getChat()->getId();
            $chats[$id]['participants'][] = array(
                'username' => $user->getUsername(),
                'name' => $user->getName(),
                'gender' => $user->getGender(),
                'aboutme' => $user->getAboutme(), // deprecated
                'photo' => $user->getPhoto(),
                'unread' => $participant->getUnread(),
            );

            if ($user->getUsername() !== $chats[$id]['last_message']['from_username']) {
                $chats[$id]['last_message']['to_usernames'][] = $user->getUsername();
            }
        }

        return array(
            'info' => array(
                'offset' => $offset,
                'limit'  => $limit,
                'count'  => $total,
            ),
            'count'  => count($chats),
            'result' => array_values($chats),
        );
    }

    /**
     * Get total unread messages.
     *
     * @param User    $user     User object
     * @param boolean $use_club Whether or not to use club messages (optional)
     *
     * @return int
     */
    public function totalUnreadMessages(User $user, $userClub = false)
    {
        try {
            if ($userClub) {
                return (int) $this
                    ->getRepository()
                    ->createQueryBuilder('p')
                    ->select('sum(p.unread) as total')
                    ->leftJoin('p.chat', 'c')
                    ->andWhere('c.club is not null')
                    ->andWhere('p.unread > 0')
                    ->andWhere('p.user = :user')
                    ->setParameter('user', $user)
                    ->getQuery()
                    ->getSingleScalarResult();
                ;
            }

            return (int) $this
                ->getRepository()
                ->createQueryBuilder('p')
                ->select('sum(p.unread) as total')
                ->leftJoin('p.chat', 'c')
                ->andWhere('c.club is null')
                ->andWhere('p.unread > 0')
                ->andWhere('p.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getSingleScalarResult();
            ;
        } catch (NoResultException $e) {}

        return 0;
    }
}
