<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207151859 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE access_token (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(255) NOT NULL, scopes JSON NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, revoked TINYINT NOT NULL, revoked_at DATETIME DEFAULT NULL, client_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_B6A2DD6819EB6921 (client_id), INDEX IDX_B6A2DD68A76ED395 (user_id), INDEX idx_access_token_expires_at (expires_at), INDEX idx_access_token_user_client (user_id, client_id), UNIQUE INDEX UNIQ_B6A2DD685F37A13B (token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE authorization_code (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(255) NOT NULL, redirect_uri VARCHAR(500) NOT NULL, scopes JSON NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, used TINYINT NOT NULL, used_at DATETIME DEFAULT NULL, code_challenge VARCHAR(255) DEFAULT NULL, code_challenge_method VARCHAR(10) DEFAULT NULL, client_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_2F33E8B819EB6921 (client_id), INDEX IDX_2F33E8B8A76ED395 (user_id), INDEX idx_authorization_code_expires_at (expires_at), UNIQUE INDEX UNIQ_509FEF5F77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE oauth2_client (id INT AUTO_INCREMENT NOT NULL, client_id VARCHAR(80) NOT NULL, client_secret_hash VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, redirect_uris JSON NOT NULL, grant_types JSON NOT NULL, allowed_scopes JSON NOT NULL, confidential TINYINT NOT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_669FF9C919EB6921 (client_id), INDEX idx_oauth2_client_client_id (client_id), INDEX idx_oauth2_client_active (active), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE refresh_token (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(255) NOT NULL, scopes JSON NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, revoked TINYINT NOT NULL, revoked_at DATETIME DEFAULT NULL, client_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_C74F219519EB6921 (client_id), INDEX IDX_C74F2195A76ED395 (user_id), INDEX idx_refresh_token_expires_at (expires_at), UNIQUE INDEX UNIQ_C74F21955F37A13B (token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE scope (id INT AUTO_INCREMENT NOT NULL, identifier VARCHAR(80) NOT NULL, description LONGTEXT DEFAULT NULL, is_default TINYINT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_AF55D3772B6FCFB2 (identifier), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, username VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, enabled TINYINT NOT NULL, created_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), UNIQUE INDEX UNIQ_8D93D649F85E0677 (username), INDEX idx_user_email (email), INDEX idx_user_username (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE access_token ADD CONSTRAINT FK_B6A2DD6819EB6921 FOREIGN KEY (client_id) REFERENCES oauth2_client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE access_token ADD CONSTRAINT FK_B6A2DD68A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE authorization_code ADD CONSTRAINT FK_2F33E8B819EB6921 FOREIGN KEY (client_id) REFERENCES oauth2_client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE authorization_code ADD CONSTRAINT FK_2F33E8B8A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F219519EB6921 FOREIGN KEY (client_id) REFERENCES oauth2_client (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F2195A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE users');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE users (id CHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'UUID\', email VARCHAR(254) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, password VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_created_at (created_at), UNIQUE INDEX UNIQ_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE access_token DROP FOREIGN KEY FK_B6A2DD6819EB6921');
        $this->addSql('ALTER TABLE access_token DROP FOREIGN KEY FK_B6A2DD68A76ED395');
        $this->addSql('ALTER TABLE authorization_code DROP FOREIGN KEY FK_2F33E8B819EB6921');
        $this->addSql('ALTER TABLE authorization_code DROP FOREIGN KEY FK_2F33E8B8A76ED395');
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_C74F219519EB6921');
        $this->addSql('ALTER TABLE refresh_token DROP FOREIGN KEY FK_C74F2195A76ED395');
        $this->addSql('DROP TABLE access_token');
        $this->addSql('DROP TABLE authorization_code');
        $this->addSql('DROP TABLE oauth2_client');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE scope');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
