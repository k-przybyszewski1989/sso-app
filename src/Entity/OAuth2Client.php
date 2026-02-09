<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GrantType;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'oauth2_client')]
#[ORM\Index(columns: ['client_id'], name: 'idx_oauth2_client_client_id')]
#[ORM\Index(columns: ['active'], name: 'idx_oauth2_client_active')]
class OAuth2Client implements PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 80, unique: true)]
    private string $clientId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $clientSecretHash;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: 'json')]
    private array $redirectUris = [];

    /**
     * @var array<GrantType>
     */
    #[ORM\Column(type: 'grant_type_array')]
    private array $grantTypes = [];

    /**
     * @var array<string>
     */
    #[ORM\Column(type: 'json')]
    private array $allowedScopes = [];

    #[ORM\Column(type: 'boolean')]
    private bool $confidential = true;

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, AccessToken>
     */
    #[ORM\OneToMany(targetEntity: AccessToken::class, mappedBy: 'client', cascade: ['remove'])]
    private Collection $accessTokens;

    /**
     * @var Collection<int, RefreshToken>
     */
    #[ORM\OneToMany(targetEntity: RefreshToken::class, mappedBy: 'client', cascade: ['remove'])]
    private Collection $refreshTokens;

    /**
     * @var Collection<int, AuthorizationCode>
     */
    #[ORM\OneToMany(targetEntity: AuthorizationCode::class, mappedBy: 'client', cascade: ['remove'])]
    private Collection $authorizationCodes;

    public function __construct(
        string $clientId,
        string $clientSecretHash,
        string $name,
    ) {
        $this->clientId = $clientId;
        $this->clientSecretHash = $clientSecretHash;
        $this->name = $name;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->accessTokens = new ArrayCollection();
        $this->refreshTokens = new ArrayCollection();
        $this->authorizationCodes = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getClientSecretHash(): string
    {
        return $this->clientSecretHash;
    }

    public function setClientSecretHash(string $clientSecretHash): void
    {
        $this->clientSecretHash = $clientSecretHash;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @return array<string>
     */
    public function getRedirectUris(): array
    {
        return $this->redirectUris;
    }

    /**
     * @param array<string> $redirectUris
     */
    public function setRedirectUris(array $redirectUris): void
    {
        $this->redirectUris = $redirectUris;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @return array<GrantType>
     */
    public function getGrantTypes(): array
    {
        return $this->grantTypes;
    }

    /**
     * @param array<GrantType> $grantTypes
     */
    public function setGrantTypes(array $grantTypes): void
    {
        $this->grantTypes = $grantTypes;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @return array<string>
     */
    public function getAllowedScopes(): array
    {
        return $this->allowedScopes;
    }

    /**
     * @param array<string> $allowedScopes
     */
    public function setAllowedScopes(array $allowedScopes): void
    {
        $this->allowedScopes = $allowedScopes;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isConfidential(): bool
    {
        return $this->confidential;
    }

    public function setConfidential(bool $confidential): void
    {
        $this->confidential = $confidential;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, AccessToken>
     */
    public function getAccessTokens(): Collection
    {
        return $this->accessTokens;
    }

    /**
     * @return Collection<int, RefreshToken>
     */
    public function getRefreshTokens(): Collection
    {
        return $this->refreshTokens;
    }

    /**
     * @return Collection<int, AuthorizationCode>
     */
    public function getAuthorizationCodes(): Collection
    {
        return $this->authorizationCodes;
    }

    /** {@inheritDoc} */
    public function getPassword(): string
    {
        return $this->clientSecretHash;
    }
}
