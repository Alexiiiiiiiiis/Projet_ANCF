<?php

namespace App\Entity;

use App\Repository\FavoriteStopRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FavoriteStopRepository::class)]
#[ORM\Table(name: 'favorite_stop')]
#[ORM\UniqueConstraint(name: 'uq_fav_user_stop', columns: ['user_id', 'stop_id'])]
#[ORM\HasLifecycleCallbacks]
class FavoriteStop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'favoriteStops')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $stopId = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    private ?string $stopName = null;

    // Pas de NotBlank : IDFM ne rattache aucune ligne aux arrêts renvoyés par la recherche
    // de proximité, le frontend envoie alors une chaîne vide. L'exiger rendait impossible la
    // mise en favori d'un arrêt trouvé « à proximité ».
    #[ORM\Column(length: 20)]
    private ?string $lineCode = null;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: ['METRO', 'RER', 'TRAM', 'BUS'])]
    private ?string $transportType = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $addedAt = null;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->addedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getStopId(): ?string
    {
        return $this->stopId;
    }

    public function setStopId(string $stopId): static
    {
        $this->stopId = $stopId;

        return $this;
    }

    public function getStopName(): ?string
    {
        return $this->stopName;
    }

    public function setStopName(string $stopName): static
    {
        $this->stopName = $stopName;

        return $this;
    }

    public function getLineCode(): ?string
    {
        return $this->lineCode;
    }

    public function setLineCode(string $lineCode): static
    {
        $this->lineCode = $lineCode;

        return $this;
    }

    public function getTransportType(): ?string
    {
        return $this->transportType;
    }

    public function setTransportType(string $transportType): static
    {
        $this->transportType = $transportType;

        return $this;
    }

    public function getAddedAt(): ?\DateTimeImmutable
    {
        return $this->addedAt;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'stopId' => $this->stopId,
            'stopName' => $this->stopName,
            'lineCode' => $this->lineCode,
            'transportType' => $this->transportType,
            'addedAt' => $this->addedAt?->format(\DateTimeInterface::ATOM),
            'sortOrder' => $this->sortOrder,
        ];
    }
}
