<?php

namespace App\Entity;

use App\Repository\ReminderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReminderRepository::class)]
class Reminder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $send_day = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTime $send_time = null;

    #[ORM\Column]
    private ?bool $is_active = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getSendDay(): ?int
    {
        return $this->send_day;
    }

    public function setSendDay(int $send_day): static
    {
        $this->send_day = $send_day;

        return $this;
    }

    public function getSendTime(): ?\DateTime
    {
        return $this->send_time;
    }

    public function setSendTime(\DateTime $send_time): static
    {
        $this->send_time = $send_time;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->is_active;
    }

    public function setIsActive(bool $is_active): static
    {
        $this->is_active = $is_active;

        return $this;
    }
}
