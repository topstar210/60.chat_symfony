<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Default ORM Friend implementation.
 *
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table(name="friends")
 */
class Friend extends Base
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="friends")
     * @ORM\JoinColumn(onDelete="cascade")
     */
    protected $user;

    /**
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(onDelete="cascade")
     */
    protected $friend;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $favorite = false;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $alias;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $date_created;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $last_modified;

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
