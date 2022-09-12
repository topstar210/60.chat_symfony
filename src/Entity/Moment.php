<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Default ORM Moment implementation.
 *
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table(name="moments")
 */
class Moment extends Base
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="moments")
     * @ORM\JoinColumn(onDelete="cascade")
     */
    protected $user;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $name;

    /**
     * @ORM\Column(type="array", nullable=true)
     */
    protected $images = array();

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $location;

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
    protected $cover_flag = false;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $unread = 0;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $date_created;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $last_modified;

    /**
     * @ORM\OneToMany(targetEntity="MomentComment", mappedBy="moment")
     */
    protected $comments = array();

    /**
     * List of users who "like" this moment
     *
     * @ORM\ManyToMany(targetEntity="User")
     * @ORM\JoinTable(
     *     name="moments_to_like_users",
     *     joinColumns={@ORM\JoinColumn(name="moments_id", referencedColumnName="id", onDelete="cascade")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="user_id", referencedColumnName="id", onDelete="cascade")}
     * )
     */
    protected $likes;

    /**
     * List of notified friends
     *
     * @ORM\ManyToMany(targetEntity="User")
     * @ORM\JoinTable(
     *     name="moments_to_mention_users",
     *     joinColumns={@ORM\JoinColumn(name="moments_id", referencedColumnName="id", onDelete="cascade")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="user_id", referencedColumnName="id", onDelete="cascade")}
     * )
     */
    protected $mentions;

    /**
     * Get images
     *
     * @return array
     */
    public function getImages()
    {
        return (array) $this->images;
    }

    /**
     * Get likes
     *
     * @return Collection
     */
    public function getLikes()
    {
        return $this->likes ?: $this->likes = new ArrayCollection();
    }

    /**
     * Get mentions
     *
     * @return Collection
     */
    public function getMentions()
    {
        return $this->mentions ?: $this->mentions = new ArrayCollection();
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
