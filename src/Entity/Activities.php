<?php

namespace App\Entity;

use App\Repository\ActivitiesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivitiesRepository::class)]
class Activities
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $label = null;

    #[ORM\Column(length: 20)]
    private ?string $color = null;

    #[ORM\Column]
    private ?bool $is_developing = null;

    #[ORM\Column]
    private ?bool $need_project = null;

    /**
     * @var Collection<int, HourEntry>
     */
    #[ORM\OneToMany(targetEntity: HourEntry::class, mappedBy: 'activities')]
    private Collection $hourEntries;

    public function __construct()
    {
        $this->hourEntries = new ArrayCollection();
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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function isDeveloping(): ?bool
    {
        return $this->is_developing;
    }

    public function setIsDeveloping(bool $is_developing): static
    {
        $this->is_developing = $is_developing;

        return $this;
    }

    public function isNeedProject(): ?bool
    {
        return $this->need_project;
    }

    public function setNeedProject(bool $need_project): static
    {
        $this->need_project = $need_project;

        return $this;
    }

    /**
     * @return Collection<int, HourEntry>
     */
    public function getHourEntries(): Collection
    {
        return $this->hourEntries;
    }

    public function addHourEntry(HourEntry $hourEntry): static
    {
        if (!$this->hourEntries->contains($hourEntry)) {
            $this->hourEntries->add($hourEntry);
            $hourEntry->setActivities($this);
        }

        return $this;
    }

    public function removeHourEntry(HourEntry $hourEntry): static
    {
        if ($this->hourEntries->removeElement($hourEntry)) {
            // set the owning side to null (unless already changed)
            if ($hourEntry->getActivities() === $this) {
                $hourEntry->setActivities(null);
            }
        }

        return $this;
    }
}
