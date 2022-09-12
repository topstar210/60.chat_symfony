<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Region
 *
 * @ORM\Entity
 * @ORM\Table(name="regions")
 */
class Region extends Base
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=14)
     */
    protected $combined_code;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=32)
     */
    protected $code;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=2)
     */
    protected $country_code;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     */
    protected $name;
}
