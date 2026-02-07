<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'authorization_code')]
#[ORM\UniqueConstraint(name: 'UNIQ_509FEF5F77153098', columns: ['code'])]
#[ORM\Index(columns: ['expires_at'], name: 'idx_authorization_code_expires_at')]
class AuthorizationCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $code;

    #[ORM\ManyToOne(targetEntity: OAuth2Client::class, inversedBy: 'authorizationCodes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private OAuth2Client $client;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'authorizationCodes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 500)]
    private string $redirectUri;

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
    private bool $used = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $usedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $codeChallenge = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $codeChallengeMethod = null;

    public function __construct(
        string $code,
        OAuth2Client $client,
        User $user,
        string $redirectUri,
        DateTimeImmutable $expiresAt,
    ) {
        $this->code = $code;
        $this->client = $client;
        $this->user = $user;
        $this->redirectUri = $redirectUri;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getClient(): OAuth2Client
    {
        return $this->client;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
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

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function setUsed(bool $used): void
    {
        $this->used = $used;
        if ($used) {
            $this->usedAt = new DateTimeImmutable();
        }
    }

    public function getUsedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function getCodeChallenge(): ?string
    {
        return $this->codeChallenge;
    }

    public function setCodeChallenge(?string $codeChallenge): void
    {
        $this->codeChallenge = $codeChallenge;
    }

    public function getCodeChallengeMethod(): ?string
    {
        return $this->codeChallengeMethod;
    }

    public function setCodeChallengeMethod(?string $codeChallengeMethod): void
    {
        $this->codeChallengeMethod = $codeChallengeMethod;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->used;
    }
}
