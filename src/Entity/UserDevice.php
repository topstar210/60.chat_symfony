<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Default ORM User Devices implementation.
 *
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table(name="users_devices")
 */
class UserDevice extends Base
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="devices")
     * @ORM\JoinColumn(onDelete="cascade")
     */
    protected $user;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $device_id;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $android = false;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $ios = false;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $enabled = false;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $date_created;

    /**
     * Returns true if device is Android.
     *
     * @return boolean
     */
    public function isAndroid()
    {
        return $this->android;
    }

    /**
     * Returns true if device is iOS.
     *
     * @return boolean
     */
    public function isIos()
    {
        return $this->ios;
    }

    /**
     * Returns device status.
     *
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Hook on pre-persist operations
     */
    public function prePersist()
    {
        $this->date_created = new \DateTime();
    }
}
