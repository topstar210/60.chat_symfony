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
 * @ORM\Table(name="facetime_party",
 *      indexes={
 *           @ORM\Index(columns={"name"})
 *      }
 * )
 */
class Party extends Base
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $host;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $subject;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $eventdate;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $starttime;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $endtime;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $email;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $phone;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $background;

    /**
     * @ORM\Column(type="integer", length=3, nullable=true)
     */
    protected $timezone;


    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $date_created ;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $last_modified;


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
     * Returns the member name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the member email.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Returns the phone.
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Returns the key.
     *
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Returns the key.
     *
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Returns the key.
     *
     * @return string
     */
    public function getEventdate ()
    {
        return $this->eventdate ;
    }
    /**
     * Returns the key.
     *
     * @return string
     */
    public function getStartTime()
    {
        return $this->starttime;
    }
    /**
     * Returns the key.
     *
     * @return string
     */
    public function getEndTime()
    {
        return $this->endtime;
    }

    /**
     * Returns the key.
     *
     * @return string
     */
    public function getBackground()
    {
        return $this->background;
    }
/**
     * Returns the key.
     *
     * @return integer
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

}
