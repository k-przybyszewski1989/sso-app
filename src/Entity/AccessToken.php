<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'access_token')]
#[ORM\UniqueConstraint(name: 'UNIQ_B6A2DD685F37A13B', columns: ['token'])]
#[ORM\Index(columns: ['expires_at'], name: 'idx_access_token_expires_at')]
#[ORM\Index(columns: ['user_id', 'client_id'], name: 'idx_access_token_user_client')]
class AccessToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $token;

    #[ORM\ManyToOne(targetEntity: OAuth2Client::class, inversedBy: 'accessTokens')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private OAuth2Client $client;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'accessTokens')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: 'json')]
    private array $scopes = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'boolean')]
    private bool $revoked = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    public function __construct(
        string $token,
        OAuth2Client $client,
        DateTimeImmutable $expiresAt,
    ) {
        $this->token = $token;
        $this->client = $client;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getClient(): OAuth2Client
    {
        return $this->client;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    /**
     * @return array<string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * @param array<string> $scopes
     */
    public function setScopes(array $scopes): void
    {
        $this->scopes = $scopes;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function setRevoked(bool $revoked): void
    {
        $this->revoked = $revoked;
        if ($revoked) {
            $this->revokedAt = new DateTimeImmutable();
        }
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->revoked;
    }
}
