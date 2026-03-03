<?php

namespace App\Entity;

use App\Repository\HourEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HourEntryRepository::class)]
class HourEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'affect')]
    #[ORM\JoinColumn(name: 'activity_id', referencedColumnName : 'id', nullable: true)]
    private ?Activities $affect = null;

    #[ORM\ManyToOne(inversedBy: 'selected')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName : 'id', nullable: false)]
    private ?User $selected = null;

    #[ORM\ManyToOne(inversedBy: 'link')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName : 'id', nullable: true)]
    private ?Project $project_id = null;

    #[ORM\Column]
    private ?\DateTime $start_date = null;

    #[ORM\Column]
    private ?\DateTime $end_date = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentary = null;

    #[ORM\Column]
    private ?\DateTime $created_at = null;

    #[ORM\ManyToOne(inversedBy: 'created')]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName : 'id', nullable: false)]
    private ?User $created = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getActivityId(): ?Activities
    {
        return $this->affect;
    }

    public function setActivityId(?Activities $activity_id): static
    {
        $this->affect = $affect;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function setUserId(int $user_id): static
    {
        $this->user_id = $user_id;

        return $this;
    }

    public function getProjectId(): ?Project
    {
        return $this->project_id;
    }

    public function setProjectId(?Project $project_id): static
    {
        $this->project_id = $project_id;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->start_date;
    }

    public function setStartDate(\DateTime $start_date): static
    {
        $this->start_date = $start_date;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->end_date;
    }

    public function setEndDate(\DateTime $end_date): static
    {
        $this->end_date = $end_date;

        return $this;
    }

    public function getCommentary(): ?string
    {
        return $this->commentary;
    }

    public function setCommentary(?string $commentary): static
    {
        $this->commentary = $commentary;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTime $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->created_by;
    }

    public function setCreatedBy(User $created): static
    {
        $this->created_by = $created;

        return $this;
    }

    public function setActivities(?Activities $activities): static
    {
        $this->activities = $activities;

        return $this;
    }

    public function getLink(): ?Project
    {
        return $this->link;
    }

    public function setLink(?Project $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function getSelected(): ?User
    {
        return $this->selected;
    }

    public function setSelected(?User $user): static
    {
        $this->selected = $user;

        return $this;
    }
}
