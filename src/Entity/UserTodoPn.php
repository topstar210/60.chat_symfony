<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Default ORM Moment implementation.
 *
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table(name="user_todo_pn")
 */
class UserTodoPn extends Base
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $user_id;

    /**
     * @ORM\Column(type="integer", options={"comment":"1: profile image, 2: interest, 3: moment"})
     */
    private $kind;

    /**
     * @ORM\Column(type="integer")
     */
    private $remain_hours;


    public function getId(): ?int
    {
        return $this->id;
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

    public function getKind(): ?int
    {
        return $this->kind;
    }

    public function setKind(int $kind): self
    {
        $this->kind = $kind;

        return $this;
    }

    public function getRemainHours(): ?int
    {
        return $this->remain_hours;
    }

    public function setRemainHours(int $remain_hours): self
    {
        $this->remain_hours = $remain_hours;

        return $this;
    }

}
