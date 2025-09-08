<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250908095404 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_sessions (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, session_id VARCHAR(128) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_used_at DATETIME DEFAULT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', revoked_at DATETIME DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, UNIQUE INDEX UNIQ_7AED7913613FECDF (session_id), INDEX IDX_7AED7913A76ED395 (user_id), INDEX idx_user_expires (user_id, expires_at), INDEX idx_session_id (session_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_sessions ADD CONSTRAINT FK_7AED7913A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_sessions DROP FOREIGN KEY FK_7AED7913A76ED395');
        $this->addSql('DROP TABLE user_sessions');
    }
}
