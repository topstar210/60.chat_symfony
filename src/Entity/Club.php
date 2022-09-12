<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Default ORM Club implementation.
 *
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table(name="clubs",
 *      indexes={
 *           @ORM\Index(columns={"name"})
 *      }
 * )
 */
class Club extends Base
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="clubs")
     * @ORM\JoinColumn(onDelete="cascade")
     */
    protected $user;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $name;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $description;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $photo;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $background;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    protected $latitude;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    protected $longitude;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $enabled = false;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $date_created;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $last_modified;

    /**
     * @ORM\OneToMany(targetEntity="ClubParticipant", mappedBy="club")
     */
    protected $participants = array();

    /**
     * @ORM\OneToMany(targetEntity="Chat", mappedBy="club")
     */
    protected $chats = array();

    /**
     * Returns the unique id.
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Returns the club name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the club description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Returns the club photo.
     *
     * @return string
     */
    public function getPhoto()
    {
        return $this->photo;
    }

    /**
     * Returns the club background.
     *
     * @return string
     */
    public function getBackground()
    {
        return $this->background;
    }

    /**
     * Returns the user latitude.
     *
     * @return string
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Returns the user longitude.
     *
     * @return string
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * Returns club status.
     *
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

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
     * Get enabled participants
     *
     * @return Collection
     */
    public function getEnabledParticipants()
    {
        $participants = new ArrayCollection();

        foreach ($this->getParticipants() as $participant) {
            if ($participant->getEnabled()) {
                $participants->add($participant);
            }
        }

        return $participants;
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
     * Get chats
     *
     * @return Collection
     */
    public function getChats()
    {
        return $this->chats ?: $this->chats = new ArrayCollection();
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

    /**
     * Returns a string representation
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->getName();
    }
}
