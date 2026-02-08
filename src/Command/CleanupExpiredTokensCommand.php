<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\AccessTokenRepositoryInterface;
use App\Repository\AuthorizationCodeRepositoryInterface;
use App\Repository\RefreshTokenRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'oauth2:cleanup-expired-tokens',
    description: 'Cleanup expired OAuth2 tokens (access tokens, refresh tokens, and authorization codes)',
)]
final class CleanupExpiredTokensCommand extends Command
{
    public function __construct(
        private readonly AccessTokenRepositoryInterface $accessTokenRepository,
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly AuthorizationCodeRepositoryInterface $authorizationCodeRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Cleaning up expired OAuth2 tokens');

        // Delete expired access tokens
        $io->section('Access Tokens');
        $deletedAccessTokens = $this->accessTokenRepository->deleteExpired();
        $io->success(sprintf('Deleted %d expired access token(s)', $deletedAccessTokens));

        // Delete expired refresh tokens
        $io->section('Refresh Tokens');
        $deletedRefreshTokens = $this->refreshTokenRepository->deleteExpired();
        $io->success(sprintf('Deleted %d expired refresh token(s)', $deletedRefreshTokens));

        // Delete expired authorization codes
        $io->section('Authorization Codes');
        $deletedAuthCodes = $this->authorizationCodeRepository->deleteExpired();
        $io->success(sprintf('Deleted %d expired authorization code(s)', $deletedAuthCodes));

        // Summary
        $totalDeleted = $deletedAccessTokens + $deletedRefreshTokens + $deletedAuthCodes;

        $io->newLine();
        $io->success(sprintf('Cleanup completed! Total deleted: %d token(s)', $totalDeleted));

        return Command::SUCCESS;
    }
}
