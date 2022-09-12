<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Default ORM MomentComment implementation.
 *
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table(name="moments_rates")
 */
class MomentRanking extends Base
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer", options={"comment":"1: moment, 2: profile image"})
     */
    private $kind;

    /**
     * @ORM\Column(type="integer")
     */
    private $user_id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $moment_id;

    /**
     * @ORM\Column(type="integer", nullable=true, options={"comment":"profile image user id"})
     */
    private $profile_id;

    /**
     * @ORM\Column(type="float")
     */
    private $rate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKind(): ?int
    {
        return $this->kind;
    }

    public function setKind(int $kind): self
    {
        $this->kind = $kind;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): self
    {
        $this->user_id = $user_id;

        return $this;
    }

    public function getRate(): ?float
    {
        return $this->rate;
    }

    public function setRate(float $rate): self
    {
        $this->rate = $rate;

        return $this;
    }

    public function getMomentId(): ?int
    {
        return $this->moment_id;
    }

    public function setMomentId(?int $moment_id): self
    {
        $this->moment_id = $moment_id;

        return $this;
    }

    public function getProfileId(): ?int
    {
        return $this->profile_id;
    }

    public function setProfileId(?int $profile_id): self
    {
        $this->profile_id = $profile_id;

        return $this;
    }

}
