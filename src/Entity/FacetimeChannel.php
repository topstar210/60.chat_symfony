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
 * @ORM\Table(name="facetime_channel",
 *      indexes={
 *           @ORM\Index(columns={"name"})
 *      }
 * )
 */
class FacetimeChannel extends Base
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    /**
     * @ORM\Column(type="integer")
     */
    protected $status;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $channel;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $gender;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $client_1;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $client_2;


    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $created;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $client1_time;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $client2_time;

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
     * Returns the status.
     *
     * @return mixed
     */
    public function getStatus()
    {
        return $this->id;
    }


    /**
     * Returns the member name.
     *
     * @return string
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * Returns the member name.
     *
     * @return string
     */
    public function getGender()
    {
        return $this->gender;
    }


    /**
     * Returns the birthday.
     *
     * @return string
     */
    public function getClient_1()
    {
        return $this->client_1;
    }

    /**
     * Returns the gender.
     *
     * @return string
     */
    public function getClient_2()
    {
        return $this->client_2;
    }

    /**
     * Returns the the time of .
     *
     * @return string
     */
    public function getClient1_time()
    {
        return $this->client1_time;
    }

    /**
     * Returns the time of join.
     *
     * @return string
     */
    public function getClien2_time()
    {
        return $this->client2_time;
    }
}
