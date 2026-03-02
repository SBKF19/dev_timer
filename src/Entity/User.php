<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255,
        unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $firstname = null;

    #[ORM\Column(length: 255)]
    private ?string $lastname = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $hired_date = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column]
    private ?bool $status = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $contract_end_date = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Role $role = null;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'manage')]
    private Collection $projects;

    /**
     * @var Collection<int, HourEntry>
     */
    #[ORM\OneToMany(targetEntity: HourEntry::class, mappedBy: 'selected')]
    private Collection $selected;

    /**
     * @var Collection<int, HourEntry>
     */
    #[ORM\OneToMany(targetEntity: HourEntry::class, mappedBy: 'created')]
    private Collection $created;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
        $this->selected = new ArrayCollection();
        $this->created = new ArrayCollection();
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): static
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): static
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getHiredDate(): ?\DateTime
    {
        return $this->hired_date;
    }

    public function setHiredDate(\DateTime $hired_date): static
    {
        $this->hired_date = $hired_date;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function isStatus(): ?bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getContractEndDate(): ?\DateTime
    {
        return $this->contract_end_date;
    }

    public function setContractEndDate(?\DateTime $contract_end_date): static
    {
        $this->contract_end_date = $contract_end_date;

        return $this;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): static
    {
        $this->role = $role;

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->setManage($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project)) {
            // set the owning side to null (unless already changed)
            if ($project->getManage() === $this) {
                $project->setManage(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, HourEntry>
     */
    public function getSelected(): Collection
    {
        return $this->selected;
    }

    public function addSelected(HourEntry $selected): static
    {
        if (!$this->selected->contains($selected)) {
            $this->selected->add($selected);
            $selected->setSelected($this);
        }

        return $this;
    }

    public function removeSelected(HourEntry $selected): static
    {
        if ($this->selected->removeElement($selected)) {
            // set the owning side to null (unless already changed)
            if ($selected->getSelected() === $this) {
                $selected->setSelected(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, HourEntry>
     */
    public function getCreated(): Collection
    {
        return $this->created;
    }

    public function addCreated(HourEntry $selected): static
    {
        if (!$this->created->contains($created)) {
            $this->created->add($created);
            $created->setCreated($this);
        }

        return $this;
    }

    public function removeCreated(HourEntry $created): static
    {
        if ($this->created->removeElement($created)) {
            // set the owning side to null (unless already changed)
            if ($created->getCreated() === $this) {
                $created->setCreated(null);
            }
        }

        return $this;
    }


}
