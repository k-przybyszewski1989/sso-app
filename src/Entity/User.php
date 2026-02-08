<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'user')]
#[ORM\Index(columns: ['email'], name: 'idx_user_email')]
#[ORM\Index(columns: ['username'], name: 'idx_user_username')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $username;

    #[ORM\Column(type: 'string')]
    private string $password;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $lastLoginAt = null;

    /**
     * @var Collection<int, AccessToken>
     */
    #[ORM\OneToMany(targetEntity: AccessToken::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $accessTokens;

    /**
     * @var Collection<int, RefreshToken>
     */
    #[ORM\OneToMany(targetEntity: RefreshToken::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $refreshTokens;

    /**
     * @var Collection<int, AuthorizationCode>
     */
    #[ORM\OneToMany(targetEntity: AuthorizationCode::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $authorizationCodes;

    public function __construct(
        string $email,
        string $username,
        string $password,
    ) {
        $this->email = $email;
        $this->username = $username;
        $this->password = $password;
        $this->createdAt = new DateTimeImmutable();
        $this->accessTokens = new ArrayCollection();
        $this->refreshTokens = new ArrayCollection();
        $this->authorizationCodes = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    /**
     * {@inheritDoc}
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param array<string> $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(DateTimeImmutable $lastLoginAt): void
    {
        $this->lastLoginAt = $lastLoginAt;
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

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    /**
     * {@inheritDoc}
     *
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        assert('' !== $this->email);

        return $this->email;
    }
}
