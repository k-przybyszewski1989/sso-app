<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use DateTimeImmutable;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed default OAuth2 scopes.
 */
final class Version20260207161927 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed default OAuth2 scopes: openid, profile, email, offline_access';
    }

    public function up(Schema $schema): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        // Insert default scopes
        $this->addSql(
            "INSERT INTO scope (identifier, description, is_default, created_at) VALUES
            ('openid', 'OpenID Connect scope for user authentication', 1, :now1),
            ('profile', 'Access to user profile information (username, etc.)', 1, :now2),
            ('email', 'Access to user email address', 1, :now3),
            ('offline_access', 'Allows requesting refresh tokens for offline access', 0, :now4)",
            [
                'now1' => $now,
                'now2' => $now,
                'now3' => $now,
                'now4' => $now,
            ]
        );
    }

    public function down(Schema $schema): void
    {
        // Remove default scopes
        $this->addSql("DELETE FROM scope WHERE identifier IN ('openid', 'profile', 'email', 'offline_access')");
    }
}
