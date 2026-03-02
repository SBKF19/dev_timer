<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $archived_at = null;

    /**
     * Relation ManyToOne vers User (le manager unique du projet)
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'managedProjects')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $manager = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'projects')]
    private Collection $usersInProject;

    public function __construct()
    {
        $this->usersInProject = new ArrayCollection();
        $this->created_at = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function getManager(): ?User
    {
        return $this->manager;
    }
    public function setManager(?User $manager): static
    {
        $this->manager = $manager;
        return $this;
    }

    /** @return Collection<int, User> */
    public function getUsersInProject(): Collection
    {
        return $this->usersInProject;
    }

    public function addUserInProject(User $user): static
    {
        if (!$this->usersInProject->contains($user)) {
            $this->usersInProject->add($user);
            $user->addProject($this); // Synchronise le côté User
        }
        return $this;
    }

    public function removeUserInProject(User $user): static
    {
        if ($this->usersInProject->removeElement($user)) {
            $user->removeProject($this);
        }
        return $this;
    }
}