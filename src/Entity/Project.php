<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom du projet est obligatoire.")]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: "Le nom doit faire au moins {{ limit }} caractères."
    )]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull(message: "La date de création est requise.")]
    #[Assert\Type("\DateTimeImmutable")]
    private ?DateTimeImmutable $created_at = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $archived_at = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $color = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'managedProjects')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Veuillez sélectionner un responsable de projet.")]
    private ?User $manager = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'projects')]
    private Collection $usersInProject;

    /**
     * @var Collection<int, HourEntry>
     */
    #[ORM\OneToMany(targetEntity: HourEntry::class, mappedBy: 'link')]
    private Collection $link;

    public function __construct()
    {
        $this->usersInProject = new ArrayCollection();
        $this->link = new ArrayCollection();
        $this->created_at = new DateTimeImmutable();

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

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(?DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
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
            $user->addProject($this);
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
    public function getArchivedAt(): ?DateTimeImmutable
    {
        return $this->archived_at;
    }

    public function setArchivedAt(?DateTimeImmutable $archived_at): static
    {
        $this->archived_at = $archived_at;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * @return Collection<int, HourEntry>
     */
    public function getLink(): Collection
    {
        return $this->link;
    }

    public function addLink(HourEntry $link): static
    {
        if (!$this->link->contains($link)) {
            $this->link->add($link);
            $link->setLink($this);
        }

        return $this;
    }

    public function removeLink(HourEntry $link): static
    {
        if ($this->link->removeElement($link)) {
            if ($link->getLink() === $this) {
                $link->setLink(null);
            }
        }

        return $this;
    }

    public function isActive(): bool
    {
        return $this->archived_at === null;
    }
}
