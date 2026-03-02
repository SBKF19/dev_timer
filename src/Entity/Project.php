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

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $created_at = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $archived = null;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $manage = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'projects')]
    private Collection $users;

    /**
     * @var Collection<int, HourEntry>
     */
    #[ORM\OneToMany(targetEntity: HourEntry::class, mappedBy: 'link')]
    private Collection $link;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->link = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
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

    public function setDescription(string $description): static
    {
        $this->description = $description;

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

    public function getArchived(): ?\DateTime
    {
        return $this->archived;
    }

    public function setArchived(?\DateTime $archived): static
    {
        $this->archived = $archived;

        return $this;
    }

    public function getManage(): ?User
    {
        return $this->manage;
    }

    public function setManage(?User $manage): static
    {
        $this->manage = $manage;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        $this->users->removeElement($user);

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
            // set the owning side to null (unless already changed)
            if ($link->getLink() === $this) {
                $link->setLink(null);
            }
        }

        return $this;
    }
}
