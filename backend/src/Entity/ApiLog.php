<?php

namespace App\Entity;

use App\Repository\ApiLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApiLogRepository::class)]
#[ORM\Table(name: 'api_log')]
#[ORM\Index(columns: ['created_at'], name: 'idx_log_date')]
#[ORM\Index(columns: ['status_code'], name: 'idx_log_status')]
#[ORM\HasLifecycleCallbacks]
class ApiLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $endpoint = null;

    #[ORM\Column(length: 10)]
    private ?string $httpMethod = null;

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
    private ?int $statusCode = null;

    #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
    private int $responseTimeMs = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** Renseigne la date de création juste avant l'insertion en base. */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): static
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function getHttpMethod(): ?string
    {
        return $this->httpMethod;
    }

    public function setHttpMethod(string $httpMethod): static
    {
        $this->httpMethod = $httpMethod;

        return $this;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getResponseTimeMs(): int
    {
        return $this->responseTimeMs;
    }

    public function setResponseTimeMs(int $responseTimeMs): static
    {
        $this->responseTimeMs = $responseTimeMs;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Convertit le log en tableau pour la réponse JSON. */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'endpoint' => $this->endpoint,
            'method' => $this->httpMethod,
            'statusCode' => $this->statusCode,
            'responseTimeMs' => $this->responseTimeMs,
            'errorMessage' => $this->errorMessage,
            'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
