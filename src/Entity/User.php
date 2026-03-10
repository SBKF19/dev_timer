<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $firstname = null;

    #[ORM\Column(length: 255)]
    private ?string $lastname = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?DateTimeInterface $hired_date = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column]
    private ?bool $status = true;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\GreaterThan(
        propertyPath: 'hired_date',
        message: 'La date d’embauche doit être antérieure à la date de fin de contrat.'
    )]
    private ?DateTimeInterface $contract_end_date = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $create_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $last_login = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $deleted_at = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Role $role = null;

    /**
     * Projects auxquels appartient l'utilisateur
     */
    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'usersInProject')]
    private Collection $projects;

    /**
     * Heures saisies par l'utilisateur
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: HourEntry::class)]
    private Collection $hourEntries;

    /**
     * Heures créées par cet utilisateur (audit)
     */
    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: HourEntry::class)]
    private Collection $createdHourEntries;

    /**
     * Projets que l'utilisateur manage
     */
    #[ORM\OneToMany(mappedBy: 'manager', targetEntity: Project::class)]
    private Collection $managedProjects;


    public function __construct()
    {
        $this->projects = new ArrayCollection();
        $this->hourEntries = new ArrayCollection();
        $this->createdHourEntries = new ArrayCollection();
        $this->managedProjects = new ArrayCollection();

        $this->create_at = new \DateTime();
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->addUserInProject($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project)) {
            $project->removeUserInProject($this);
        }

        return $this;
    }

    public function getHourEntries(): Collection
    {
        return $this->hourEntries;
    }

    public function getCreatedHourEntries(): Collection
    {
        return $this->createdHourEntries;
    }

    public function getManagedProjects(): Collection
    {
        return $this->managedProjects;
    }

    public function addManagedProject(Project $project): static
    {
        if (!$this->managedProjects->contains($project)) {
            $this->managedProjects->add($project);
            $project->setManager($this);
        }

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | GETTERS / SETTERS
    |--------------------------------------------------------------------------
    */

    public function getId(): ?int
    {
        return $this->id;
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

    public function getHiredDate(): ?DateTimeInterface
    {
        return $this->hired_date;
    }

    public function setHiredDate(DateTimeInterface $hired_date): static
    {
        $this->hired_date = $hired_date;
        return $this;
    }

    public function getContractEndDate(): ?DateTimeInterface
    {
        return $this->contract_end_date;
    }

    public function setContractEndDate(?DateTimeInterface $contract_end_date): static
    {
        $this->contract_end_date = $contract_end_date;
        return $this;
    }

    public function getCreateAt(): ?DateTimeInterface
    {
        return $this->create_at;
    }

    public function setCreateAt(DateTimeInterface $create_at): static
    {
        $this->create_at = $create_at;
        return $this;
    }

    public function getLastLogin(): ?DateTimeInterface
    {
        return $this->last_login;
    }

    public function setLastLogin(?DateTimeInterface $last_login): static
    {
        $this->last_login = $last_login;
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

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;
        return $this;
    }

    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deleted_at;
    }

    public function setDeletedAt(?DateTimeInterface $deleted_at): static
    {
        $this->deleted_at = $deleted_at;
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

    /*
    |--------------------------------------------------------------------------
    | SECURITY
    |--------------------------------------------------------------------------
    */

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        if ($this->role && $this->role->getLabel()) {
            $label = strtoupper($this->role->getLabel());
            $roles[] = 'ROLE_' . str_replace(' ', '_', $label);
        }

        return array_unique($roles);
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function eraseCredentials(): void
    {
    }
}