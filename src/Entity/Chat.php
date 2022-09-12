<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Default ORM Chat implementation.
 *
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table(name="chats")
 */
class Chat extends Base
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Club", inversedBy="chats")
     * @ORM\JoinColumn(onDelete="cascade")
     */
    protected $club;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $subject;

    /**
     * List of participants
     *
     * @ORM\OneToMany(targetEntity="ChatParticipant", mappedBy="chat")
     */
    protected $participants;

    /**
     * @ORM\OneToMany(targetEntity="ChatMessage", mappedBy="chat")
     */
    protected $messages;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $date_created;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $last_modified;

    /**
     * Get participants
     *
     * @return Collection
     */
    public function getParticipants()
    {
        return $this->participants ?: $this->participants = new ArrayCollection();
    }

    /**
     * Get participant
     *
     * @param User $user
     *
     * @return ClubParticipant|null
     */
    public function getParticipantByUser(User $user)
    {
        foreach ($this->getParticipants() as $participant) {
            if ($participant->getUser()->getId() == $user->getId()) {
                return $participant;
            }
        }

        return null;
    }

    /**
     * Get messages
     *
     * @return Collection
     */
    public function getMessages()
    {
        return $this->messages ?: $this->messages = new ArrayCollection();
    }

    /**
     * Hook on pre-persist operations
     */
    public function prePersist()
    {
        $this->date_created = new \DateTime();
        $this->last_modified = new \DateTime();
    }

    /**
     * Hook on pre-update operations
     */
    public function preUpdate()
    {
        $this->last_modified = new \DateTime();
    }
}
