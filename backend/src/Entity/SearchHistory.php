<?php

namespace App\Entity;

use App\Repository\SearchHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SearchHistoryRepository::class)]
#[ORM\Table(name: 'search_history')]
#[ORM\Index(columns: ['created_at'], name: 'idx_sh_date')]
#[ORM\HasLifecycleCallbacks]
class SearchHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 200)]
    private ?string $searchQuery = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $resultCount = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getSearchQuery(): ?string { return $this->searchQuery; }
    public function setSearchQuery(string $searchQuery): static { $this->searchQuery = $searchQuery; return $this; }
    public function getResultCount(): ?int { return $this->resultCount; }
    public function setResultCount(?int $resultCount): static { $this->resultCount = $resultCount; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
}
