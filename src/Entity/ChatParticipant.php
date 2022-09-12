<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Default ORM Chat Participant implementation.
 *
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table(name="chats_participants")
 */
class ChatParticipant extends Base
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Chat", inversedBy="participants")
     * @ORM\JoinColumn(onDelete="cascade")
     */
    protected $chat;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(onDelete="cascade")
     */
    protected $user;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $open = true;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $unread = 0;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $date_created;

    /**
     * Hook on pre-persist operations
     */
    public function prePersist()
    {
        $this->date_created = new \DateTime();
    }
}
