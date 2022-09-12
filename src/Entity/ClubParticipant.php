<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Default ORM Club Participant implementation.
 *
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table(name="clubs_participants")
 */
class ClubParticipant extends Base
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Club", inversedBy="participants")
     * @ORM\JoinColumn(onDelete="cascade")
     */
    protected $club;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(onDelete="cascade")
     */
    protected $user;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $enabled = false;

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
