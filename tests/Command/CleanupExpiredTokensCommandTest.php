<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\AccessToken;
use App\Entity\AuthorizationCode;
use App\Entity\OAuth2Client;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\AccessTokenRepositoryInterface;
use App\Repository\AuthorizationCodeRepositoryInterface;
use App\Repository\RefreshTokenRepositoryInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CleanupExpiredTokensCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AccessTokenRepositoryInterface $accessTokenRepository;
    private RefreshTokenRepositoryInterface $refreshTokenRepository;
    private AuthorizationCodeRepositoryInterface $authorizationCodeRepository;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->accessTokenRepository = $container->get(AccessTokenRepositoryInterface::class);
        $this->refreshTokenRepository = $container->get(RefreshTokenRepositoryInterface::class);
        $this->authorizationCodeRepository = $container->get(AuthorizationCodeRepositoryInterface::class);

        $this->entityManager->beginTransaction();

        $kernel = self::$kernel;
        assert(null !== $kernel);
        $application = new Application($kernel);
        $command = $application->find('oauth2:cleanup-expired-tokens');
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
        $this->entityManager->clear();
        $this->entityManager->close();

        parent::tearDown();
    }

    public function testCommandDeletesExpiredAccessTokens(): void
    {
        $client = new OAuth2Client('client_cleanup', 'secret', 'Test Client');
        $this->entityManager->persist($client);

        $expiredToken = new AccessToken('expired_access', $client, new DateTimeImmutable('-1 hour'));
        $this->accessTokenRepository->save($expiredToken);
        $this->entityManager->flush();

        $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Deleted 1 expired access token(s)', $output);
        $this->assertNull($this->accessTokenRepository->findByToken('expired_access'));
    }

    public function testCommandDeletesExpiredRefreshTokens(): void
    {
        $client = new OAuth2Client('client_refresh', 'secret', 'Test Client');
        $this->entityManager->persist($client);

        $user = new User('refresh@example.com', 'refreshuser', 'password');
        $this->entityManager->persist($user);

        $expiredRefresh = new RefreshToken('expired_refresh', $client, $user, new DateTimeImmutable('-1 hour'));
        $this->refreshTokenRepository->save($expiredRefresh);
        $this->entityManager->flush();

        $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Deleted 1 expired refresh token(s)', $output);
        $this->assertNull($this->refreshTokenRepository->findByToken('expired_refresh'));
    }

    public function testCommandDeletesExpiredAuthorizationCodes(): void
    {
        $client = new OAuth2Client('client_authcode', 'secret', 'Test Client');
        $this->entityManager->persist($client);

        $user = new User('authcode@example.com', 'authcodeuser', 'password');
        $this->entityManager->persist($user);

        $expiredCode = new AuthorizationCode(
            'expired_code',
            $client,
            $user,
            'http://example.com/callback',
            new DateTimeImmutable('-1 hour')
        );
        $this->authorizationCodeRepository->save($expiredCode);
        $this->entityManager->flush();

        $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Deleted 1 expired authorization code(s)', $output);
        $this->assertNull($this->authorizationCodeRepository->findByCode('expired_code'));
    }

    public function testCommandDoesNotDeleteValidTokens(): void
    {
        $client = new OAuth2Client('client_valid', 'secret', 'Test Client');
        $this->entityManager->persist($client);

        $user = new User('valid@example.com', 'validuser', 'password');
        $this->entityManager->persist($user);

        // Create expired tokens
        $expiredAccess = new AccessToken('expired_access_mixed', $client, new DateTimeImmutable('-1 hour'));
        $this->accessTokenRepository->save($expiredAccess);

        $expiredRefresh = new RefreshToken('expired_refresh_mixed', $client, $user, new DateTimeImmutable('-1 hour'));
        $this->refreshTokenRepository->save($expiredRefresh);

        $expiredCode = new AuthorizationCode(
            'expired_code_mixed',
            $client,
            $user,
            'http://example.com/callback',
            new DateTimeImmutable('-1 hour')
        );
        $this->authorizationCodeRepository->save($expiredCode);

        // Create valid tokens
        $validAccess = new AccessToken('valid_access', $client, new DateTimeImmutable('+1 hour'));
        $this->accessTokenRepository->save($validAccess);

        $validRefresh = new RefreshToken('valid_refresh', $client, $user, new DateTimeImmutable('+1 hour'));
        $this->refreshTokenRepository->save($validRefresh);

        $validCode = new AuthorizationCode(
            'valid_code',
            $client,
            $user,
            'http://example.com/callback',
            new DateTimeImmutable('+1 hour')
        );
        $this->authorizationCodeRepository->save($validCode);

        $this->entityManager->flush();

        $this->commandTester->execute([]);

        // Verify expired tokens deleted
        $this->assertNull($this->accessTokenRepository->findByToken('expired_access_mixed'));
        $this->assertNull($this->refreshTokenRepository->findByToken('expired_refresh_mixed'));
        $this->assertNull($this->authorizationCodeRepository->findByCode('expired_code_mixed'));

        // Verify valid tokens still exist
        $this->assertNotNull($this->accessTokenRepository->findByToken('valid_access'));
        $this->assertNotNull($this->refreshTokenRepository->findByToken('valid_refresh'));
        $this->assertNotNull($this->authorizationCodeRepository->findByCode('valid_code'));
    }

    public function testCommandOutputShowsCorrectCounts(): void
    {
        $client = new OAuth2Client('client_counts', 'secret', 'Test Client');
        $this->entityManager->persist($client);

        $user = new User('counts@example.com', 'countsuser', 'password');
        $this->entityManager->persist($user);

        // Create 2 expired access tokens
        $expiredAccess1 = new AccessToken('expired_1', $client, new DateTimeImmutable('-1 hour'));
        $expiredAccess2 = new AccessToken('expired_2', $client, new DateTimeImmutable('-2 hours'));
        $this->accessTokenRepository->save($expiredAccess1);
        $this->accessTokenRepository->save($expiredAccess2);

        // Create 3 expired refresh tokens
        $expiredRefresh1 = new RefreshToken('refresh_1', $client, $user, new DateTimeImmutable('-1 hour'));
        $expiredRefresh2 = new RefreshToken('refresh_2', $client, $user, new DateTimeImmutable('-2 hours'));
        $expiredRefresh3 = new RefreshToken('refresh_3', $client, $user, new DateTimeImmutable('-3 hours'));
        $this->refreshTokenRepository->save($expiredRefresh1);
        $this->refreshTokenRepository->save($expiredRefresh2);
        $this->refreshTokenRepository->save($expiredRefresh3);

        // Create 1 expired authorization code
        $expiredCode = new AuthorizationCode(
            'code_1',
            $client,
            $user,
            'http://example.com/callback',
            new DateTimeImmutable('-1 hour')
        );
        $this->authorizationCodeRepository->save($expiredCode);

        $this->entityManager->flush();

        $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Deleted 2 expired access token(s)', $output);
        $this->assertStringContainsString('Deleted 3 expired refresh token(s)', $output);
        $this->assertStringContainsString('Deleted 1 expired authorization code(s)', $output);
        $this->assertStringContainsString('Total deleted: 6 token(s)', $output);
    }

    public function testCommandSucceedsWhenNoExpiredTokensExist(): void
    {
        $this->commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Deleted 0 expired access token(s)', $output);
        $this->assertStringContainsString('Deleted 0 expired refresh token(s)', $output);
        $this->assertStringContainsString('Deleted 0 expired authorization code(s)', $output);
        $this->assertStringContainsString('Total deleted: 0 token(s)', $output);
    }
}
